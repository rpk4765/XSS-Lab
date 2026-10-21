<?php
declare(strict_types=1);
$targetId = 'archive';
require dirname(__DIR__) . '/shared/target.php';
?>
    <div class="card"><h2>Record lookup</h2><div id="result" class="muted">Looking up reference…</div></div>
    <?php $archived = str_replace(['\\', "'"], ['\\\\', "\\'"], $payload); ?>
    <script>const reference = '<?= $archived ?>'; document.getElementById('result').textContent = reference || 'Enter a record reference';</script>
<?php require dirname(__DIR__) . '/shared/footer.php'; ?>
