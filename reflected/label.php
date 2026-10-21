<?php
declare(strict_types=1);
$targetId = 'label';
require dirname(__DIR__) . '/shared/target.php';
?>
    <div class="card"><div class="eyebrow">RLY-2084 / Standard delivery</div><h2>Recipient</h2><input aria-label="Generated recipient" id="recipient" value="<?= str_replace(['<', '>'], ['&lt;', '&gt;'], $payload) ?>"><p class="muted">Northline depot · Bay 06</p><button onclick="document.getElementById('recipient').focus()">Inspect label</button></div>
<?php require dirname(__DIR__) . '/shared/footer.php'; ?>
