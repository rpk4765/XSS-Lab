<?php
declare(strict_types=1);
$targetId = 'depots';
require dirname(__DIR__) . '/shared/target.php';
?>
    <div class="card"><h2>Available depots</h2>
    <script>const depot = new URLSearchParams(location.hash.slice(1)).get('input') || 'Central hub'; document.write('<select aria-label="Depot"><option>' + depot + '</option><option>East junction</option></select>');</script>
    <p class="muted">Collections available 08:00–18:00.</p></div>
<?php require dirname(__DIR__) . '/shared/footer.php'; ?>
