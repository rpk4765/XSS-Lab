<?php
declare(strict_types=1);
require_once __DIR__ . '/range.php';
range_setup();
$token = is_string($_GET['run'] ?? null) ? $_GET['run'] : '';
$run = $_SESSION['xss_range_runs'][$token] ?? null;
if (!isset($targetId) || !$run || $run['challenge'] !== $targetId || $run['expires'] < time()) {
    http_response_code(410);
    exit('Run expired. Launch the challenge again.');
}
$id = $run['challenge'];
$challenge = range_challenges()[$id];
if ($challenge['type'] === 'Final Mission' && !range_final_unlocked()) {
    http_response_code(403);
    exit('Solve all 12 regular labs before opening a final mission target.');
}
$payload = $run['payload'];
$entries = !empty($run['preview']) ? [] : ($run['entries'] ?? []);
$proof = $run['proof'];
$previewRun = !empty($run['preview']);
$targetStage = (int) ($run['stage'] ?? 0);
session_write_close();
header('Cache-Control: no-store');
// Sandbox also applies when the target is opened directly, outside the workbench.
header("Content-Security-Policy: sandbox allow-scripts; default-src 'none'; script-src 'unsafe-inline'; style-src 'unsafe-inline'; img-src data:; connect-src 'none'; base-uri 'none'; form-action 'none'");
header('X-Content-Type-Options: nosniff');
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($challenge['app']) ?> · XSS Lab</title>
<script>
(() => {
    const run = <?= range_json($token) ?>;
    const proof = <?= range_json($proof) ?>;
    let reported = false;
    Object.defineProperty(window, 'alert', { configurable: false, writable: false, value(value) {
        const message = String(value);
        if (message === '1' && !reported) {
            reported = true;
            parent.postMessage({type: 'relay-execution', run, proof, value: message}, '*');
        } else {
            parent.postMessage({type: 'relay-alert', run, value: message.slice(0, 160)}, '*');
        }
        const show = () => {
            const note = document.createElement('div');
            note.className = 'execution';
            note.textContent = 'alert(' + message.slice(0, 160) + ') executed';
            document.body.prepend(note);
        };
        if (document.body) show(); else document.addEventListener('DOMContentLoaded', show, {once:true});
    }});
})();
</script>
<script>
(() => {
    const run = <?= range_json($token) ?>;
    const reportHeight = () => {
        if (!document.body) return;
        parent.postMessage({type: 'relay-resize', run, height: document.body.scrollHeight + 2}, '*');
    };
    document.addEventListener('DOMContentLoaded', () => {
        new ResizeObserver(reportHeight).observe(document.body);
        reportHeight();
    }, {once: true});
})();
</script>
<style>
*{box-sizing:border-box}body{margin:0;font:15px/1.6 system-ui,sans-serif;color:#dcebe2;background:#0b1114}.bar{padding:14px 24px;border-bottom:1px solid #273a3c;background:#10191d;display:flex;justify-content:space-between;gap:12px}.logo{font-weight:850;letter-spacing:-.6px;color:#e6f4eb}.team{color:#91a5a0;font-size:11px}.content{padding:27px;max-width:850px;margin:auto}h1{font-size:26px;letter-spacing:-.7px;line-height:1.2;margin:8px 0 24px;color:#e9f8ee}h2{font-size:16px;margin:0 0 8px;color:#d9e9df}.eyebrow{font:10px ui-monospace,Consolas,monospace;color:#57d98b;letter-spacing:1.5px;text-transform:uppercase}.card,.message{padding:18px;margin:12px 0;background:#111b1f;border:1px solid #2a3c40;border-radius:8px;overflow-wrap:anywhere}.muted{color:#8fa39d}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.pill{display:inline-block;font-size:10px;padding:3px 8px;border-radius:20px;background:#142d20;color:#72ef98;border:1px solid #2e6440}input,select{width:100%;font:inherit;padding:12px;border:1px solid #34484b;border-radius:6px;background:#0a1114;color:#dcebe2}button,.visit{display:inline-block;padding:10px 15px;color:#07150b;background:#57e982;border:1px solid #57e982;border-radius:6px;font:700 12px ui-monospace,Consolas,monospace;cursor:pointer;text-decoration:none}a{color:#63e791}.execution{padding:10px 24px;background:#14321f;color:#7cff9f;font:700 12px ui-monospace,Consolas,monospace}.blocked{background:#311b18;border-color:#754139}svg{max-width:100%}#result{min-height:30px}@media(max-width:480px){.content{padding:17px}.grid{grid-template-columns:1fr}}
.greeting-card{padding:40px;background:#111b1f;color:#dcebe2}
button{transition:box-shadow 160ms ease,filter 160ms ease}.visit{transition:text-shadow 160ms ease}button:not(:disabled):is(:hover,:focus-visible){box-shadow:0 0 18px 3px #3b82f6aa;filter:brightness(1.1)}a:is(:hover,:focus-visible){text-shadow:0 0 8px #60a5fa,0 0 18px #3b82f6}.visit:is(:hover,:focus-visible){text-shadow:none}a.visit:is(:hover,:focus-visible){text-shadow:0 0 8px #60a5fa,0 0 18px #3b82f6}@media(prefers-reduced-motion:reduce){button,.visit{transition:none}}
</style>
<style>
:root{color-scheme:dark;--target-bg:#080d18;--target-panel:#101827;--target-line:#26364b;--target-blue:#3b82f6;--target-red:#fb4c5d;--target-text:#e6edf7;--target-muted:#91a0b7}
body{color:var(--target-text);background:var(--target-bg)}
.content{color:var(--target-text)}
.eyebrow{color:#60a5fa}
h1,h2{color:#e6edf7}
.card,.message{border-color:var(--target-line);background:var(--target-panel)}
.greeting-card{background:var(--target-panel);color:var(--target-text)}
.muted{color:var(--target-muted)}
.pill{border-color:#a33e4b;background:#291720;color:#ff9aa5}
input,select{border-color:#344968;background:#0a111f;color:var(--target-text)}
input:focus,select:focus{outline:1px solid var(--target-blue)}
button,.visit{border-color:#60a5fa;background:var(--target-blue);color:#071326}
a{color:#93c5fd}
.execution{background:#142748;color:#bfdbfe;border-left:3px solid var(--target-blue)}
.blocked{border-color:#a33e4b;background:#291720;color:#ff9aa5}
button:not(:disabled):is(:hover,:focus-visible){box-shadow:0 0 18px 3px #3b82f6aa;filter:brightness(1.1)}
.final-target{position:relative;background:#10090f}
.final-target::before{position:fixed;z-index:10;inset:0;background:repeating-linear-gradient(0deg,#ff334f08 0,#ff334f08 1px,transparent 1px,transparent 5px),radial-gradient(ellipse at 50% 0,#8b142a20,transparent 70%);content:"";pointer-events:none}
.final-target .content{position:relative}
.final-target .eyebrow{color:#ff7180;text-shadow:0 0 12px #ff334f55}
.final-target h1,.final-target h2{color:#fff0f2}
.final-target .card,.final-target .message{border-color:#51232e;background:linear-gradient(145deg,#1c1017,#140d13)}
.final-target .muted{color:#b9959d}
.final-target .pill{border-color:#8b283b;background:#2a1018;color:#ffadb6}
.final-target input,.final-target select{border-color:#572632;background:#100a10;color:#ffe1e5}
.final-target input:focus,.final-target select:focus{outline:1px solid #ff334f}
.final-target button,.final-target .visit{border-color:#ff7180;background:linear-gradient(180deg,#ff6476,#d92343);color:#fff5f6}
.final-target button{transition:none!important}
.final-target a{color:#ff9aa5}
.final-target .execution{border-left:3px solid #ff334f;background:#2a1018;color:#ffc0c7}
.final-target .blocked{border-color:#a13043;background:#321019;color:#ffc0c7}
.final-target button:disabled{cursor:not-allowed}
.final-target button:not(:disabled):is(:hover,:focus-visible){box-shadow:0 0 18px 3px #ff334fbb;filter:brightness(1.12)}
</style></head><body<?= $id === 'final-relay' ? ' class="final-target"' : '' ?>>
<main class="content"><div class="eyebrow"><?= e($challenge['route']) ?></div><h1><?= e($challenge['app']) ?></h1>
