<?php
declare(strict_types=1);

// Hint answers are checked on the server; no unlocked hint is sent before success.
function final_hint_task(int $stage, string $token, string $code, callable $renderHint): void
{
    $answer = is_string($_GET['hint_answer'] ?? null) ? strtoupper(trim($_GET['hint_answer'])) : '';
    if (hash_equals($code, $answer)) {
        echo '<details open><summary>Recovered archive / unlocked</summary>';
        $renderHint();
        echo '</details>';
        return;
    }
    ?>
    <section class="card">
    <?php if ($stage === 2): ?>
        <h2>Side task: follow the archive dependencies</h2>
        <p>Start at ROOT. Each record points to the next record. Follow the links until END and join the seals in traversal order. Unreachable records are decoys.</p>
        <table><thead><tr><th>Record</th><th>Next</th><th>Seal</th></tr></thead><tbody>
        <tr><td>LOCK</td><td>TAIL</td><td><?= e(substr($code, 4, 2)) ?></td></tr>
        <tr><td>ECHO</td><td>END</td><td>FF</td></tr>
        <tr><td>ROOT</td><td>FORK</td><td><?= e(substr($code, 0, 2)) ?></td></tr>
        <tr><td>TAIL</td><td>END</td><td><?= e(substr($code, 6, 2)) ?></td></tr>
        <tr><td>FORK</td><td>LOCK</td><td><?= e(substr($code, 2, 2)) ?></td></tr>
        </tbody></table>
    <?php else: ?>
        <?php
        $encoded = [];
        foreach (str_split($code, 2) as $byte) {
            $masked = hexdec($byte) ^ 0x5A;
            $encoded[] = strtoupper(str_pad(dechex((($masked << 1) | ($masked >> 7)) & 255), 2, '0', STR_PAD_LEFT));
        }
        ?>
        <h2>Side task: recover the diagnostic bytes</h2>
        <p>Each original byte was XORed with hexadecimal 5A, then rotated left by one bit within eight bits. Reverse those operations for each captured byte, preserving byte order. Join the original bytes as eight hexadecimal characters.</p>
        <p>Captured bytes: <code id="diagnosticBytes"><?= e(implode(' ', $encoded)) ?></code></p>
        <p class="muted">Rotation wraps the outgoing high bit back into the low bit. This diagnostic uses its own encoding, separate from the mission transport.</p>
    <?php endif; ?>
    <label for="hintAnswer">Recovered archive key</label>
    <input id="hintAnswer" maxlength="8" autocomplete="off" spellcheck="false" placeholder="Eight hexadecimal characters">
    <button type="button" id="unlockHint">Unlock stage hint</button>
    <p role="status"><?= $answer !== '' ? 'Key rejected. Recheck the recovery steps.' : 'Optional task: unlocks only this hint. Each new run has a fresh key.' ?></p>
    </section>
    <script>
    (() => {
        const unlock = () => parent.postMessage({type: 'relay-hint', run: <?= range_json($token) ?>, answer: document.getElementById('hintAnswer').value.trim().toUpperCase()}, '*');
        document.getElementById('unlockHint').addEventListener('click', unlock);
        document.getElementById('hintAnswer').addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); unlock(); } });
    })();
    </script>
    <?php
}
