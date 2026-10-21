<?php
declare(strict_types=1);
$targetId = 'final-relay';
require dirname(__DIR__) . '/shared/target.php';
require __DIR__ . '/hint-task.php';
$stageNote = $previewRun ? $payload : ($entries[0] ?? $payload);
?>
<?php if ($targetStage === 1): ?>
<?php
$hintCode = substr($run['hint_key'], 0, 6);
$hintAnswer = is_string($_GET['hint_answer'] ?? null) ? strtoupper(trim($_GET['hint_answer'])) : '';
$hintUnlocked = hash_equals($hintCode, $hintAnswer);
?>
<section class="card"><h2>Dispatch protocol / revision 7</h2><p>The operator manual is sealed. Recover its routing code from the shift records to inspect the protocol hint.</p>
<?php if ($hintUnlocked): ?>
<details open><summary>Recovered protocol fragment / unlocked</summary><pre>{"lane":"night","note":"arrival%20pending"}</pre><p class="muted">Gateway: JSON &rarr; lane check &rarr; wire inspection &rarr; URI unpack &rarr; operator card. Wire inspection rejects literal angle brackets in the note. The old terminal still understands markup.</p></details>
<?php else: ?>
<section class="card"><h2>Side task: reconstruct the routing code</h2><p>Keep only ACK records for depot N7. A CANCEL removes its matching ticket. Sort the remaining tickets by sequence, lowest first, then join their fragments without spaces.</p>
<table><thead><tr><th>Seq</th><th>Depot</th><th>Ticket</th><th>Event</th><th>Fragment</th></tr></thead><tbody>
<tr><td>30</td><td>N7</td><td>R31</td><td>ACK</td><td><?= e(substr($hintCode, 4, 2)) ?></td></tr>
<tr><td>05</td><td>N7</td><td>R08</td><td>ACK</td><td>FF</td></tr>
<tr><td>20</td><td>N7</td><td>R22</td><td>ACK</td><td><?= e(substr($hintCode, 2, 2)) ?></td></tr>
<tr><td>10</td><td>S4</td><td>R16</td><td>ACK</td><td>00</td></tr>
<tr><td>10</td><td>N7</td><td>R14</td><td>ACK</td><td><?= e(substr($hintCode, 0, 2)) ?></td></tr>
<tr><td>40</td><td>N7</td><td>R08</td><td>CANCEL</td><td>--</td></tr>
</tbody></table>
<label for="hintAnswer">Recovered routing code</label><input id="hintAnswer" maxlength="6" autocomplete="off" spellcheck="false" placeholder="Six hexadecimal characters"><button type="button" id="unlockHint">Unlock protocol hint</button>
<p role="status"><?= $hintAnswer !== '' ? 'Code rejected. Recheck the depot, cancelled ticket, and sequence.' : 'Optional task: unlocks a hint, not stage completion. Each new run has a fresh code.' ?></p>
</section>
<script>
(() => {
    const unlock = () => {
        parent.postMessage({type: 'relay-hint', run: <?= range_json($token) ?>, answer: document.getElementById('hintAnswer').value.trim().toUpperCase()}, '*');
    };
    document.getElementById('unlockHint').addEventListener('click', unlock);
    document.getElementById('hintAnswer').addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); unlock(); } });
})();
</script>
<?php endif; ?></section>
<section class="card"><h2>Gateway receipt</h2><div id="receipt">Waiting for a valid envelope.</div></section>
<script>
(() => {
    const receipt = document.getElementById('receipt');
    if (<?= range_json($payload) ?>.trim() === '') { receipt.textContent = 'Enter a dispatch envelope to begin.'; return; }
    try {
        const envelope = JSON.parse(<?= range_json($payload) ?>);
        if (!envelope || envelope.lane !== 'night' || typeof envelope.note !== 'string') throw Error('Envelope refused: lane or note is invalid.');
        if (/[<>]/.test(envelope.note)) throw Error('Wire inspection refused the note.');
        receipt.innerHTML = decodeURIComponent(envelope.note);
    } catch (error) { receipt.textContent = error.message; }
})();
</script>
<?php elseif ($targetStage === 2): ?>
<section class="card"><h2>Legacy shift archive</h2><p>Every launch stores a new note. Recover the sealed maintenance record by tracing the archive dependencies.</p>
<?php final_hint_task(2, $token, $run['hint_key'], static function (): void { ?>
<p class="muted">Patch order: reject HTML delimiters and line breaks → prefix each apostrophe with a backslash → embed in the legacy script. Existing backslashes are preserved. The visible search copy uses HTML encoding.</p>
<?php }); ?>
<h2>Search copy</h2><p><?= e($stageNote ?: 'No archive note.') ?></p><h2>Operator copy</h2><div id="handoffPreview">Archive not loaded.</div></section>
<?php if (preg_match('/[<>\r\n\x{2028}\x{2029}]/u', $stageNote) !== 0): ?>
<p class="card blocked">Archive refused HTML delimiters or line separators.</p>
<?php else: $legacyNote = str_replace("'", "\\'", $stageNote); ?>
<script>const handoffNote = '<?= $legacyNote ?>'; document.getElementById('handoffPreview').textContent = handoffNote || 'No archive note.';</script>
<?php endif; ?>
<?php elseif ($targetStage === 3): ?>
<section class="card"><h2>Dead-letter recovery console</h2><p>The transport archive retained a packet and its integrity stamp. Restore a packet the operator can render.</p>
<?php final_hint_task(3, $token, $run['hint_key'], static function (): void { ?>
<pre>{"packet":"BASE64","stamp":"0000"}</pre>
<p class="muted">Outbound path: UTF-8 bytes → XOR with repeating ASCII key <code>relay</code> (key starts at index zero) → reverse byte order → standard Base64.</p>
<p class="muted">Stamp: sum each original unsigned byte multiplied by its one-based position, modulo 65536; four lowercase hexadecimal digits. Inbound recovery checks the stamp before rendering the restored note.</p>
<?php }); ?>
<p>Raw archive: <code><?= e($payload ?: 'No packet received.') ?></code></p></section>
<section class="card"><h2>Recovery result</h2><div id="lastHandoff">Waiting for transport packet.</div></section>
<script>
(() => {
    const result = document.getElementById('lastHandoff');
    if (<?= range_json($payload) ?>.trim() === '') { result.textContent = 'Enter a transport envelope to begin.'; return; }
    try {
        const envelope = JSON.parse(<?= range_json($payload) ?>);
        if (!envelope || typeof envelope.packet !== 'string' || typeof envelope.stamp !== 'string' || !/^[0-9a-f]{4}$/.test(envelope.stamp)) throw Error('Invalid packet envelope.');
        if (!/^(?:[A-Za-z0-9+/]{4})*(?:[A-Za-z0-9+/]{2}==|[A-Za-z0-9+/]{3}=)?$/.test(envelope.packet)) throw Error('Invalid transport alphabet.');
        const key = 'relay';
        const packed = Array.from(atob(envelope.packet), char => char.charCodeAt(0)).reverse();
        const bytes = Uint8Array.from(packed, (byte, index) => byte ^ key.charCodeAt(index % key.length));
        const stamp = bytes.reduce((sum, byte, index) => (sum + byte * (index + 1)) % 65536, 0).toString(16).padStart(4, '0');
        if (stamp !== envelope.stamp) throw Error('Integrity stamp mismatch.');
        result.innerHTML = new TextDecoder('utf-8', {fatal: true}).decode(bytes);
    } catch (error) { result.textContent = error.message; }
})();
</script>
<?php else: ?>
<section class="card"><h2>Stage unavailable</h2><p>Return to the CTF board and launch an unlocked stage.</p></section>
<?php endif; ?>
<?php require dirname(__DIR__) . '/shared/footer.php'; ?>
