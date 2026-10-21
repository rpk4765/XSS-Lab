<?php
declare(strict_types=1);
return [
    'routes' => ['title' => 'Wrong turn', 'app' => 'Route finder', 'type' => 'DOM', 'level' => 'Starter', 'field' => 'Destination', 'button' => 'Find route', 'goal' => 'Make the route preview execute alert(1).', 'defense' => 'Use textContent to show a destination. Build extra page elements with createElement instead of inserting an HTML string.', 'route' => 'network / routes', 'client' => true, 'target' => 'dom/routes.php', 'order' => 7],
    'depots' => ['title' => 'Out of the box', 'app' => 'Depot selector', 'type' => 'DOM', 'level' => 'Core', 'field' => 'Preferred depot', 'button' => 'Find depot', 'goal' => 'Execute alert(1) from the depot selection view.', 'defense' => 'Avoid document.write for dynamic data. Create the needed elements and set their text with textContent.', 'route' => 'network / depots', 'client' => true, 'target' => 'dom/depots.php', 'order' => 8],
    'message' => ['title' => 'Incoming signal', 'app' => 'Live notice receiver', 'type' => 'DOM', 'level' => 'Core', 'field' => 'Broadcast content', 'button' => 'Send broadcast', 'goal' => 'Execute alert(1) inside the receiving display.', 'defense' => 'Check the message sender and origin, then display its contents with textContent instead of innerHTML.', 'route' => 'broadcast / receiver', 'client' => true, 'target' => 'dom/message.php', 'order' => 11],
];
