<?php
declare(strict_types=1);
$targetId = 'greeting';
require dirname(__DIR__) . '/shared/target.php';
?>
    <div class="card greeting-card"><div class="eyebrow">A note from your courier</div><h2 id="result"></h2><p>Your next delivery is in good hands.</p></div>
    <?php $template = str_replace(['\\', '`', '<', '>', '"', "'"], ['\\\\', '\\`', '\\u003c', '\\u003e', '\\u0022', '\\u0027'], $payload); ?>
    <script>document.getElementById('result').textContent = `Welcome, <?= $template ?>`;</script>
<?php require dirname(__DIR__) . '/shared/footer.php'; ?>
