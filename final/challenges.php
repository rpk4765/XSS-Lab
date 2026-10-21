<?php
declare(strict_types=1);
return [
    'final-relay' => [
        'title' => 'dundo to maanu',
        'app' => 'Relay challenge terminal',
        'type' => 'Final Mission',
        'level' => 'Final',
        'field' => 'Enter a test value',
        'button' => 'Run stage',
        'goal' => 'Complete the three local CTF stages. Trigger the sandbox signal on each stage, then submit the final flag.',
        'defense' => 'This is a deliberately vulnerable local-only CTF target. In real applications, encode output for its HTML context and use textContent instead of innerHTML.',
        'route' => 'relay network / challenge zone',
        'stored' => false,
        'target' => 'final/mission.php',
        'order' => 13,
    ],
];
