<?php
declare(strict_types=1);
$targetId = 'dispatch';
require dirname(__DIR__) . '/shared/target.php';
?>
    <div class="card">Results for <strong><?= $payload === '' ? 'all deliveries' : $payload ?></strong></div>
    <div class="grid"><article class="card"><span class="pill">In transit</span><h2>Northline parcel</h2><div class="muted">RLY-1048 · Dock 4 · 14:30</div></article><article class="card"><span class="pill">Delivered</span><h2>Harbour collection</h2><div class="muted">RLY-1051 · Dock 2 · 09:15</div></article></div>
<?php require dirname(__DIR__) . '/shared/footer.php'; ?>
