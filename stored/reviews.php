<?php
declare(strict_types=1);
$targetId = 'reviews';
require dirname(__DIR__) . '/shared/target.php';
?>
    <div class="eyebrow">Customer view / latest 10 reviews</div><div id="result"></div>
    <script>const reviews = <?= range_json($entries) ?>; const root = document.getElementById('result'); if (!reviews.length) root.textContent = 'No reviews posted yet.'; reviews.forEach(text => { const card=document.createElement('article'); card.className='card'; card.innerHTML=text.replace('<','&lt;').replace('>','&gt;'); root.appendChild(card); });</script>
<?php require dirname(__DIR__) . '/shared/footer.php'; ?>
