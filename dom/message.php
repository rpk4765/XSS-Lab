<?php
declare(strict_types=1);
$targetId = 'message';
require dirname(__DIR__) . '/shared/target.php';
?>
    <div class="card"><span class="pill">Receiver online</span><h2>Network noticeboard</h2><div id="result" class="muted">Waiting for a broadcast…</div></div>
    <script>window.addEventListener('message', event => { if (event.data && event.data.type === 'relay-preview' && typeof event.data.html === 'string') document.getElementById('result').innerHTML = event.data.html; });</script>
<?php require dirname(__DIR__) . '/shared/footer.php'; ?>
