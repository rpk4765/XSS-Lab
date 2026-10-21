<?php
declare(strict_types=1);
$targetId = 'badge';
require dirname(__DIR__) . '/shared/target.php';
?>
    <?php
    preg_match_all('/<\s*\/?\s*([a-z0-9:-]+)/i', $payload, $tags);
    $blocked = array_diff(array_map('strtolower', $tags[1]), ['svg', 'circle', 'rect', 'text', 'animate', 'set']);
    $rejected = $blocked || preg_match('/\bon(?:load|error)\s*=/i', $payload);
    ?>
    <?php if ($rejected): ?><div class="card blocked">Badge rejected by the studio filter.</div>
    <?php else: ?><div class="card" style="min-height:180px"><div class="eyebrow">Print preview</div><?= $payload ?: '<p class="muted">Your courier badge will appear here.</p>' ?></div><?php endif; ?>
<?php require dirname(__DIR__) . '/shared/footer.php'; ?>
