<?php
declare(strict_types=1);
$targetId = 'inbox';
require dirname(__DIR__) . '/shared/target.php';
?>
    <div class="eyebrow">Staff inbox / latest 10 messages</div>
    <?php if (!$entries): ?><div class="card muted">No messages waiting.</div><?php endif; ?>
    <?php foreach ($entries as $body): ?><article class="message"><h2>Customer message</h2><div><?= $body ?></div></article><?php endforeach; ?>
<?php require dirname(__DIR__) . '/shared/footer.php'; ?>
