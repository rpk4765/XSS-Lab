<?php
declare(strict_types=1);
$targetId = 'routes';
require dirname(__DIR__) . '/shared/target.php';
?>
    <div class="card"><h2>Destination preview</h2><div id="result"></div></div><div class="card muted">Central hub → East junction → Destination</div>
    <script>document.getElementById('result').innerHTML = new URLSearchParams(location.hash.slice(1)).get('input') || 'Choose a destination';</script>
<?php require dirname(__DIR__) . '/shared/footer.php'; ?>
