<?php
declare(strict_types=1);
$targetId = 'tracker';
require dirname(__DIR__) . '/shared/target.php';
?>
    <div class="card"><h2 id="result">Loading campaign…</h2><span class="pill">Tracking active</span><p class="muted">24 visits · 8 bookings · updated just now</p></div>
    <script>const campaign = '<?= htmlspecialchars($payload, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>'; document.getElementById('result').textContent = campaign || 'No campaign selected';</script>
<?php require dirname(__DIR__) . '/shared/footer.php'; ?>
