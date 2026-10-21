<?php
declare(strict_types=1);
require_once __DIR__ . '/range.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function range_reply(array $data, int $status = 200): void
{
    http_response_code($status);
    echo range_json($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    range_reply(['error' => 'Use POST.'], 405);
}
range_check_csrf();
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 32768) {
    range_reply(['error' => 'Request too large.'], 413);
}
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $expectedOrigin = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '');
    if ($_SERVER['HTTP_ORIGIN'] !== $expectedOrigin) range_reply(['error' => 'Invalid request origin.'], 403);
}
$action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
$id = is_string($_POST['challenge'] ?? null) ? $_POST['challenge'] : '';
$challenges = range_challenges();
range_setup();

if ($action === 'sync_progress') {
    $submittedIds = $_POST['solved'] ?? '';
    if (!is_string($submittedIds) || strlen($submittedIds) > 4096) {
        range_reply(['error' => 'Saved progress must be a list of challenge IDs.'], 400);
    }
    $decodedIds = json_decode($submittedIds, true);
    if (!is_array($decodedIds) || array_filter($decodedIds, static fn($value): bool => !is_string($value))) {
        range_reply(['error' => 'Saved progress must be a list of challenge IDs.'], 400);
    }
    // Browser storage is a display cache, never evidence of a solved target.
    $completedStage = (int) ($_SESSION['xss_final_ctf_stage'] ?? 0);
    $complete = !empty($_SESSION['xss_range_progress']['final-relay']);
    range_reply([
        'solved' => range_solved(),
        'finalState' => [
            'completedStage' => $completedStage,
            'complete' => $complete,
            'flagCipher' => $completedStage === 3 && !$complete ? 'SYNT{ynfg_unaqbss}' : null,
        ],
    ]);
}

if (!isset($challenges[$id])) {
    range_reply(['error' => 'Unknown challenge.'], 400);
}
$challenge = $challenges[$id];
if ($challenge['type'] === 'Final Mission' && !range_final_unlocked()) {
    range_reply(['error' => 'Solve all 12 Reflected, Stored, and DOM targets to unlock the final mission.'], 403);
}
if ($action === 'preview_launch' && $challenge['type'] !== 'Final Mission') {
    range_reply(['error' => 'Preview runs are only available for the final mission.'], 400);
}
if ($challenge['type'] === 'Final Mission' && $action === 'ctf_state') {
    $completedStage = (int) ($_SESSION['xss_final_ctf_stage'] ?? 0);
    $complete = !empty($_SESSION['xss_range_progress'][$id]);
    range_reply([
        'completedStage' => $completedStage,
        'complete' => $complete,
        'flagCipher' => $completedStage === 3 && !$complete ? 'SYNT{ynfg_unaqbss}' : null,
    ]);
}
if ($challenge['type'] === 'Final Mission' && $action === 'submit_flag') {
    if ((int) ($_SESSION['xss_final_ctf_stage'] ?? 0) !== 3) {
        range_reply(['error' => 'Complete all three CTF stages before submitting the flag.'], 409);
    }
    $flag = is_string($_POST['flag'] ?? null) ? trim($_POST['flag']) : '';
    if (!hash_equals('FLAG{last_handoff}', $flag)) {
        range_reply(['error' => 'That flag is not correct yet. Check the final stage result and try again.'], 400);
    }
    $_SESSION['xss_range_progress'][$id] = true;
    range_reply(['complete' => true, 'solved' => range_solved()]);
}
$stage = null;
if ($challenge['type'] === 'Final Mission' && in_array($action, ['launch', 'preview_launch'], true)) {
    $stageValue = $_POST['stage'] ?? null;
    if (!is_string($stageValue) || !ctype_digit($stageValue)) {
        range_reply(['error' => 'Choose a valid CTF stage.'], 400);
    }
    $stage = (int) $stageValue;
    if ($stage < 1 || $stage > 3) {
        range_reply(['error' => 'Choose a valid CTF stage.'], 400);
    }
    if ($action === 'launch' && $stage > (int) ($_SESSION['xss_final_ctf_stage'] ?? 0) + 1) {
        range_reply(['error' => 'Complete the previous CTF stage first.'], 403);
    }
}
foreach ($_SESSION['xss_range_runs'] as $token => $run) {
    if ($run['expires'] < time()) {
        unset($_SESSION['xss_range_runs'][$token]);
    }
}

try {
    if ($action === 'launch' || $action === 'preview_launch' || $action === 'save') {
        $payload = $_POST['payload'] ?? '';
        if (!is_string($payload) || strlen($payload) > 6000 || preg_match('//u', $payload) !== 1) {
            range_reply(['error' => 'Input must be text, at most 6,000 bytes.'], 400);
        }
        if ($action === 'save') {
            if (empty($challenge['stored']) || trim($payload) === '') {
                range_reply(['error' => 'Enter a value to save.'], 400);
            }
            $_SESSION['xss_range_entries'][$id] ??= [];
            $_SESSION['xss_range_entries'][$id][] = $payload;
            $_SESSION['xss_range_entries'][$id] = array_slice($_SESSION['xss_range_entries'][$id], -50);
            range_reply(['saved' => true, 'entries' => count(range_entries($id))]);
        }
        if ($challenge['type'] === 'Final Mission' && $action === 'launch' && $stage === 2 && trim($payload) !== '') {
            $_SESSION['xss_range_entries'][$id] ??= [];
            $_SESSION['xss_range_entries'][$id][] = $payload;
            $_SESSION['xss_range_entries'][$id] = array_slice($_SESSION['xss_range_entries'][$id], -50);
        }
        while (count($_SESSION['xss_range_runs']) >= 24) {
            unset($_SESSION['xss_range_runs'][array_key_first($_SESSION['xss_range_runs'])]);
        }
        $token = bin2hex(random_bytes(24));
        $_SESSION['xss_range_runs'][$token] = [
            'challenge' => $id,
            'payload' => $payload,
            'entries' => range_entries($id),
            'hint_key' => strtoupper(bin2hex(random_bytes(4))),
            'proof' => bin2hex(random_bytes(24)),
            'expires' => time() + 1800,
            'preview' => $action === 'preview_launch',
            'stage' => $stage,
        ];
        range_reply(['run' => $token, 'url' => range_url($challenge['target'] . '?run=' . $token)]);
    }

    if ($action === 'solve') {
        $token = is_string($_POST['run'] ?? null) ? $_POST['run'] : '';
        $proof = is_string($_POST['proof'] ?? null) ? $_POST['proof'] : '';
        $run = $_SESSION['xss_range_runs'][$token] ?? null;
        if (!$run || $run['challenge'] !== $id || $run['expires'] < time() || !hash_equals($run['proof'], $proof)) {
            range_reply(['error' => 'This run is no longer active. Run the challenge again.'], 409);
        }
        if (!empty($run['preview'])) {
            range_reply(['error' => 'Preview runs cannot change challenge progress.'], 403);
        }
        require_once __DIR__ . '/verify.php';
        if (!range_verify_execution($run, $token, $challenge)) {
            if (session_status() !== PHP_SESSION_ACTIVE) session_start();
            range_reply(['error' => 'XSS execution was not independently confirmed. Run a working alert(1) input again.'], 422);
        }
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        // A concurrent reset or launch may have invalidated the snapshot during verification.
        if (!isset($_SESSION['xss_range_runs'][$token]) || $_SESSION['xss_range_runs'][$token] !== $run) {
            range_reply(['error' => 'Run changed during verification. Launch again.'], 409);
        }
        if ($challenge['type'] === 'Final Mission') {
            $stage = (int) ($run['stage'] ?? 0);
            $completedStage = (int) ($_SESSION['xss_final_ctf_stage'] ?? 0);
            if ($stage > $completedStage + 1) {
                range_reply(['error' => 'Complete the previous CTF stage first.'], 409);
            }
            if ($stage === $completedStage + 1) {
                $_SESSION['xss_final_ctf_stage'] = $stage;
            }
            $completedStage = max($completedStage, $stage);
            range_reply([
                'solved' => range_solved(),
                'completedStage' => $completedStage,
                'ctfReady' => $completedStage === 3,
                'flagCipher' => $completedStage === 3 ? 'SYNT{ynfg_unaqbss}' : null,
            ]);
        }
        $_SESSION['xss_range_progress'][$id] = true;
        range_reply(['solved' => range_solved()]);
    }

    if ($action === 'reset') {
        unset($_SESSION['xss_range_entries'][$id], $_SESSION['xss_range_progress'][$id]);
        if ($challenge['type'] === 'Final Mission') {
            unset($_SESSION['xss_final_ctf_stage']);
        } else {
            unset($_SESSION['xss_final_ctf_stage'], $_SESSION['xss_range_progress']['final-relay']);
        }
        foreach ($_SESSION['xss_range_runs'] as $token => $run) {
            if ($run['challenge'] === $id || ($challenge['type'] !== 'Final Mission' && $run['challenge'] === 'final-relay')) {
                unset($_SESSION['xss_range_runs'][$token]);
            }
        }
        range_reply(['solved' => range_solved()]);
    }
    range_reply(['error' => 'Unknown action.'], 400);
} catch (Throwable $error) {
    error_log('XSS range: ' . $error->getMessage());
    range_reply(['error' => 'Could not save this change. Check the local PHP session storage and retry.'], 500);
}
