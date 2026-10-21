<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (!in_array($remote, ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true) ||
        !preg_match('/^(?:localhost|127\.0\.0\.1|\[::1\])(?::[0-9]{1,5})?$/iD', $host)) {
        http_response_code(403);
        exit('This training lab accepts localhost requests only.');
    }
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('X-Frame-Options: SAMEORIGIN');
    header('Cache-Control: no-store');
}

$scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$rangeBasePath = preg_replace('~/(?:shared|reflected|stored|dom|final)$~', '', $scriptDirectory);
$rangeBasePath = rtrim($rangeBasePath ?? '', '/');

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('XSS_RANGE');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => ($rangeBasePath ?: '') . '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

function range_url(string $path = ''): string
{
    global $rangeBasePath;
    return $rangeBasePath . '/' . ltrim($path, '/');
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function range_challenges(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $challenges = array_merge(
        require dirname(__DIR__) . '/reflected/challenges.php',
        require dirname(__DIR__) . '/stored/challenges.php',
        require dirname(__DIR__) . '/dom/challenges.php',
        require dirname(__DIR__) . '/final/challenges.php'
    );
    uasort($challenges, static fn(array $a, array $b): int => $a['order'] <=> $b['order']);
    return $cached = $challenges;
}

function range_setup(): void
{
    // Old progress accepted client declarations and cannot be treated as verified.
    if (($_SESSION['xss_verification_version'] ?? 0) !== 2) {
        unset($_SESSION['xss_range_progress'], $_SESSION['xss_range_runs'], $_SESSION['xss_final_ctf_stage']);
        $_SESSION['xss_verification_version'] = 2;
    }
    $_SESSION['xss_range_entries'] ??= [];
    $_SESSION['xss_range_progress'] ??= [];
    $_SESSION['xss_range_runs'] ??= [];
    $_SESSION['xss_range_csrf'] ??= bin2hex(random_bytes(32));
}

function range_csrf_token(): string
{
    range_setup();
    return $_SESSION['xss_range_csrf'];
}

function range_check_csrf(): void
{
    $token = is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '';
    if (!hash_equals(range_csrf_token(), $token)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo range_json(['error' => 'Invalid request token. Reload the lab and retry.']);
        exit;
    }
}

function range_solved(): array
{
    return array_values(array_intersect(
        array_keys($_SESSION['xss_range_progress'] ?? []),
        array_keys(range_challenges())
    ));
}

function range_regular_challenge_ids(): array
{
    $challenges = array_filter(
        range_challenges(),
        static fn(array $challenge): bool => $challenge['type'] !== 'Final Mission'
    );
    return array_keys($challenges);
}

function range_final_unlocked(): bool
{
    return array_diff(range_regular_challenge_ids(), range_solved()) === [];
}

function range_entries(string $challenge): array
{
    $entries = $_SESSION['xss_range_entries'][$challenge] ?? [];
    return array_slice(array_reverse($entries), 0, 10);
}

function range_json($value): string
{
    return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
}
