<?php
declare(strict_types=1);
$targetId = 'directory';
require dirname(__DIR__) . '/shared/target.php';
?>
    <div class="eyebrow">Partner cards / latest 10 updates</div>
    <?php if (!$entries): ?><div class="card muted">No partner contact cards yet.</div><?php endif; ?>
    <?php foreach ($entries as $body): ?><article class="card"><h2>Northline partner</h2><p class="muted">Independent courier · City network</p><button class="visit" onclick="const note='<?= htmlspecialchars($body, ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8') ?>'; this.nextElementSibling.textContent=note;">Open contact card</button><p class="contact-note"></p></article><?php endforeach; ?>
<?php require dirname(__DIR__) . '/shared/footer.php'; ?>
