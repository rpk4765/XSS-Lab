<?php
declare(strict_types=1);

function range_verify_execution(array $run, string $token, array $challenge): bool
{
    // Render the exact session snapshot without accepting a browser-supplied proof as execution.
    $originalGet = $_GET;
    $_GET = ['run' => $token];
    ob_start();
    try {
        require dirname(__DIR__) . '/' . $challenge['target'];
        $html = ob_get_contents();
    } finally {
        ob_end_clean();
        $_GET = $originalGet;
    }
    $input = range_json([
        'html' => $html,
        'fragment' => !empty($challenge['client']) && $run['challenge'] !== 'message' ? 'input=' . rawurlencode($run['payload']) : null,
        'message' => $run['challenge'] === 'message' ? $run['payload'] : null,
    ]);
    $lock = fopen(sys_get_temp_dir() . '/relay-xss-verification.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        if ($lock) fclose($lock);
        return false;
    }
    try {
        $process = proc_open(['C:/Program Files/nodejs/node.exe', __DIR__ . '/verify-xss.cjs'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, __DIR__, null, ['bypass_shell' => true, 'create_no_window' => true]);
        if (!is_resource($process)) return false;
        fwrite($pipes[0], $input);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $status = proc_close($process);
        return $status === 0 && (json_decode($output, true)['executed'] ?? false) === true;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
