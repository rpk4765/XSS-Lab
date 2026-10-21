<?php
declare(strict_types=1);
require_once __DIR__ . '/range.php';
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-src 'self'; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'self'");
range_setup();
$isStory = !empty($rangeStory);
$challenges = range_challenges();
if (isset($rangeType)) {
    $challenges = array_filter($challenges, static fn(array $challenge): bool => $challenge['type'] === $rangeType);
}
$allChallengeIds = array_keys(range_challenges());
$regularChallengeIds = range_regular_challenge_ids();
$solved = array_values(array_intersect(range_solved(), $isStory ? $allChallengeIds : array_keys($challenges)));
$sessionSolved = range_solved();
$scopeIds = $isStory ? $allChallengeIds : array_keys($challenges);
$requiresFinalUnlock = ($rangeType ?? '') === 'Final Mission';
$finalUnlocked = range_final_unlocked();
$previewMode = $requiresFinalUnlock && $finalUnlocked && ($_GET['preview'] ?? '') === '1';
$canOpenFinal = $finalUnlocked;
$initialFinalStage = $previewMode ? 1 : (filter_var($_GET['stage'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 3]]) ?: 1);
$heading = $isStory ? 'The Night Route' : ($requiresFinalUnlock ? 'dundo to maanu' : (isset($rangeType) ? $rangeType . ' XSS' : 'XSS'));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($heading) ?> — XSS Lab</title>
<link rel="stylesheet" href="<?= e(range_url('assets/lab.css?v=' . filemtime(dirname(__DIR__) . '/assets/lab.css'))) ?>">
</head>
<body class="normal-page<?= $requiresFinalUnlock ? ' final-page' : '' ?>">
<header class="standalone-top">
    <a class="standalone-brand" href="<?= e(range_url('index.php')) ?>"><span class="brand-mark" aria-hidden="true">&lt;/&gt;</span><span>XSS Lab<small>Cross-site scripting practice</small></span></a>
    <span class="standalone-state"><i aria-hidden="true"></i> LOCAL PRACTICE</span>
</header>
<main class="container">
<div class="range-shell">

    <header class="range-header">
        <div><div class="range-kicker"><span class="live-dot"></span> <?= $isStory ? 'INCIDENT FILE / 00' : ($requiresFinalUnlock ? 'FINAL CHALLENGE / 04' : 'YOUR PRACTICE WORKSPACE') ?></div><div class="range-title-row"><?php if ($requiresFinalUnlock): ?><a class="final-back-link" href="<?= e(range_url('story.php')) ?>" aria-label="Back to lab" title="Back to lab"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5m7 7-7-7 7-7"/></svg></a><?php endif; ?><h1><?= e($heading) ?> <small><?= $isStory ? 'A Relay Dispatch XSS incident' : ($requiresFinalUnlock ? 'Local three-stage XSS CTF' : 'Learn by testing in a safe local range') ?></small></h1></div><p><?= $isStory ? 'A short story, a simple XSS guide, and 13 local practice targets.' : ($requiresFinalUnlock ? 'Find the input, trace the sinks, and submit the final flag.' : count($challenges) . ' hands-on targets · completion signal') ?><?= $isStory || $requiresFinalUnlock ? '' : ' <code>alert(1)</code>' ?></p></div>
        <div class="range-total"><strong id="solvedCount"><?= count($solved) ?></strong><span>/ <?= $isStory ? count($allChallengeIds) : count($challenges) ?><br>targets solved</span><svg viewBox="0 0 44 44" aria-hidden="true"><circle cx="22" cy="22" r="18"/><circle id="progressRing" cx="22" cy="22" r="18"<?= $isStory ? ' style="stroke-dashoffset:' . (113.1 * (1 - count($solved) / max(count($allChallengeIds), 1))) . '"' : '' ?>/></svg></div>
    </header>
    <?php if (!$requiresFinalUnlock): ?>
    <nav class="type-nav" aria-label="XSS types">
        <?php foreach ([
            ['story.php', '00 - Story', 'story'],
            ['reflected/index.php', '01 - Reflected', 'Reflected'],
            ['stored/index.php', '02 - Stored', 'Stored'],
            ['dom/index.php', '03 - DOM', 'DOM'],
            ['final/index.php', '04 - Final', 'Final Mission'],
        ] as [$path, $label, $section]): $isFinal = $section === 'Final Mission'; ?>
        <a href="<?= e(range_url($path)) ?>"<?= $isFinal ? ' data-final-nav aria-disabled="false" class="' . ($finalUnlocked ? '' : 'is-locked') . '"' : '' ?><?= (($isStory && $section === 'story') || ($rangeType ?? '') === $section) ? ' aria-current="page"' : '' ?>><span><?= e($label) ?></span><?php if ($isFinal): ?><span class="final-lock-indicator"><?= $finalUnlocked ? 'OPEN' : 'LOCKED' ?></span><?php endif; ?></a>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>
    <?php if ($requiresFinalUnlock): ?>
    <section id="finalLockScreen" class="final-lock-screen"<?= $canOpenFinal ? ' hidden' : '' ?> aria-labelledby="finalLockTitle">
        <span class="story-label">FINAL CHALLENGE · LOCKED</span>
        <h2 id="finalLockTitle">Complete all 12 targets first</h2>
        <p>Solve every Reflected, Stored, and DOM target to unlock the final mission.</p>
        <p class="final-lock-progress" id="finalLockProgress"><?= count(array_intersect($regularChallengeIds, $sessionSolved)) ?> / <?= count($regularChallengeIds) ?> targets complete</p>
        <a class="final-mission-link" href="<?= e(range_url('story.php')) ?>">Back to the lab <span aria-hidden="true">→</span></a>
        <p>The three-stage mission is visible here, but its targets, hints and flag submission stay sealed until every regular lab is verified.</p>
        <ol><li>Night gateway — locked</li><li>Broken archive patch — locked</li><li>Dead-letter recovery — locked</li></ol>
    </section>
    <?php endif; ?>
    <?php if ($isStory): ?>
    <article class="story-page">
        <section class="story-cover">
            <div class="story-cover-copy">
                <span class="story-label"><span class="story-live-dot"></span> RELAY COURIER NETWORK · LOCAL INCIDENT REPORT</span>
                <h2>The last package on the night route</h2>
                <p>It was a normal night at the courier office. Then the website started treating messages like instructions.</p>
            </div>
            <div class="story-stamp" aria-label="Fictional local training scenario"><span>TRAINING</span><strong>RLY<br>00:47</strong><small>NO REAL USERS</small></div>
        </section>

        <section class="story-section story-narrative" aria-labelledby="storyIncidentTitle">
            <div class="story-section-heading"><span>THE INCIDENT</span><span>21:47 · NIGHT SHIFT</span></div>
            <h2 id="storyIncidentTitle">A message appeared on the page. The browser took it as a command.</h2>
            <p>Relay’s night dispatcher looked for a late parcel in <strong>Lost in dispatch</strong>. The search showed its result on the page. Next came a <strong>Handle with care</strong> label, an <strong>Off the record</strong> campaign note, and an <strong>A personal touch</strong> welcome card. Each page used data typed by a user.</p>
            <p>More clues were waiting. The <strong>After hours</strong> inbox held support messages. <strong>The visiting card</strong> showed partner notes. <strong>Second opinion</strong> showed customer reviews. The <strong>Wrong turn</strong> route and <strong>Out of the box</strong> depot pages used data from the web address. An <strong>Incoming signal</strong> showed a live message. The <strong>Stamp of approval</strong> badge and <strong>End of the line</strong> archive record held the final clues.</p>
            <p>The problem was simple: some pages used user input in the wrong way. The browser read that input as HTML or JavaScript, not as plain text. This is the basic idea behind XSS.</p>
            <div class="story-quote"><span>INCIDENT NOTE</span><p>“Keep data as data. If a page treats it as code, the browser may run it.”</p></div>
        </section>

        <section class="story-section xss-explainer" aria-labelledby="xssExplainTitle">
            <div class="story-section-heading"><span>XSS IN PLAIN ENGLISH</span><span>THE BROWSER IS THE SCENE</span></div>
            <h2 id="xssExplainTitle">What is Cross-Site Scripting (XSS)?</h2>
            <p>XSS is a web bug. It happens when a page shows user input in an unsafe way. The browser may run that input as code instead of showing it as text. This can affect people who visit the page. In this lab, you practise only on local test pages.</p>
            <div class="story-types">
                <article class="story-type-card"><span class="story-type-number">01</span><h3>Reflected</h3><p>The page shows your input right away, such as a search result or parcel label.</p><a href="<?= e(range_url('reflected/index.php')) ?>">Open 6 reflected targets <span aria-hidden="true">→</span></a></article>
                <article class="story-type-card"><span class="story-type-number">02</span><h3>Stored</h3><p>The page saves your input and shows it later, such as in a message or review.</p><a href="<?= e(range_url('stored/index.php')) ?>">Open 3 stored targets <span aria-hidden="true">→</span></a></article>
                <article class="story-type-card"><span class="story-type-number">03</span><h3>DOM-based</h3><p>Page code reads data, often from a web address, and uses it to change the page.</p><a href="<?= e(range_url('dom/index.php')) ?>">Open 3 DOM targets <span aria-hidden="true">→</span></a></article>
            </div>
        </section>

        <section class="story-section how-to-play" aria-labelledby="howToPlayTitle">
            <div class="story-section-heading"><span>FIELD GUIDE</span><span>HOW THIS LAB WORKS</span></div>
            <h2 id="howToPlayTitle">How to investigate the incident</h2>
            <ol>
                <li><strong>01 · Pick a type.</strong> Choose Reflected, Stored, or DOM above. Each section has its own challenges.</li>
                <li><strong>02 · Read the goal.</strong> It tells you what to try. Enter your test in the box.</li>
                <li><strong>03 · Check the result.</strong> A challenge is solved when <code>alert(1)</code> runs in the test page. Typing the words alone does not count.</li>
                <li><strong>04 · Try again.</strong> Use Reset to clear the current challenge. Your progress is saved in this browser.</li>
            </ol>
            <p class="story-safety"><strong>Stay safe:</strong> These practice pages are made to have bugs. They run in a local test area. They do not test real websites or real users. Use this lab on your own computer only.</p>
        </section>

        <section class="story-section glossary" aria-labelledby="glossaryTitle">
            <div class="story-section-heading"><span>QUICK GLOSSARY</span><span>PLAIN ENGLISH</span></div>
            <h2 id="glossaryTitle">A few useful words</h2>
            <dl class="glossary-grid">
                <div><dt>Input</dt><dd>Text a person types or sends to a page.</dd></div>
                <div><dt>Browser</dt><dd>The app you use to open websites, such as Chrome or Firefox.</dd></div>
                <div><dt>HTML</dt><dd>The building blocks used to make a web page.</dd></div>
                <div><dt>JavaScript</dt><dd>Code that can make a web page respond and change.</dd></div>
                <div><dt>DOM</dt><dd>The browser’s live view of a page and its parts.</dd></div>
                <div><dt>Sandbox</dt><dd>A fenced-off area that limits what a test page can reach.</dd></div>
            </dl>
        </section>

        <section class="story-section final-mission-card" aria-labelledby="finalMissionTitle">
            <div class="story-section-heading"><span>FINAL CTF</span><span>3 STAGES · AFTER THE 12 TARGETS</span></div>
            <h2 id="finalMissionTitle">dundo to maanu</h2>
            <p>Find the input, trace the reflected, stored, and DOM paths, then beat the final handoff and submit the flag.</p>
            <a class="final-mission-link<?= $finalUnlocked ? '' : ' is-locked' ?>" data-final-nav href="<?= e(range_url('final/index.php')) ?>" aria-disabled="false"><?= $finalUnlocked ? 'Start the final mission' : 'Locked — solve all 12 targets first' ?> <span aria-hidden="true"><?= $finalUnlocked ? '→' : '×' ?></span></a>
        </section>

    </article>
    </div>
    <?php elseif (!$requiresFinalUnlock || $canOpenFinal): ?>
    <?php if ($previewMode): ?><p class="final-preview-banner" role="status">PREVIEW MODE · You can inspect and test this mission, but your input and progress will not be saved.</p><?php endif; ?>
    <?php if ($requiresFinalUnlock): ?>
    <section class="final-ctf" id="finalCtf"<?= $canOpenFinal ? '' : ' hidden' ?>>
        <header class="ctf-heading">
            <div><span class="ctf-eyebrow">HOSTILE NETWORK · RED ZONE</span><h2>Three-stage challenge</h2><p>Follow the clues. Trace each input path. Beat the final handoff.</p><div class="ctf-alert" role="status"><span aria-hidden="true">!</span> TRACE ACTIVE <i aria-hidden="true"></i> THREAT LEVEL: CRITICAL</div></div>
            <span class="ctf-live"><i></i><?= $previewMode ? 'PREVIEW' : 'SANDBOX ACTIVE' ?></span>
        </header>
        <nav class="ctf-stages" aria-label="CTF stages">
            <?php foreach ([1 => 'Night gateway', 2 => 'Broken archive patch', 3 => 'Dead-letter recovery'] as $stageNumber => $stageTitle): ?>
            <button type="button" class="ctf-stage-button" data-stage="<?= $stageNumber ?>" aria-current="<?= $stageNumber === $initialFinalStage ? 'step' : 'false' ?>">
                <span class="ctf-stage-number">0<?= $stageNumber ?></span><span><?= e($stageTitle) ?></span><span class="ctf-stage-state">LOCKED</span>
            </button>
            <?php endforeach; ?>
        </nav>
        <section class="ctf-mission" aria-labelledby="ctfStageTitle">
            <header class="ctf-mission-header"><div><span class="ctf-stage-kicker" id="ctfStageKicker">STAGE 01 / 03</span><h3 id="ctfStageTitle">Find the input</h3></div><span id="missionStatus" class="mission-status" role="status">Ready</span></header>
            <p class="ctf-objective" id="missionGoal">Read the clue, find the input that reaches the page, and make the sandbox report alert(1).</p>
            <div class="ctf-clue" id="ctfClue"></div>
            <div class="target-workspace">
                <header class="target-bar"><div class="window-dots" aria-hidden="true"><i></i><i></i><i></i></div><span id="targetRoute">relay / stage 01</span><span class="target-local">LOCAL</span></header>
                <form id="challengeForm" class="target-controls">
                    <label for="payload" id="payloadLabel">Route note</label>
                    <textarea id="payload" name="payload" rows="3" maxlength="6000" autocomplete="off" spellcheck="false" placeholder="Enter a test value"></textarea>
                    <div class="target-actions"><button class="primary-action" id="runButton" type="submit">Run stage</button><button class="reset-action" id="resetButton" type="button"><?= $previewMode ? 'Clear preview' : 'Restart CTF' ?></button><span id="runState" role="status" aria-live="polite">Preparing CTF…</span></div>
                </form>
                <iframe id="targetFrame" title="Local CTF challenge target" sandbox="allow-scripts" scrolling="no" referrerpolicy="no-referrer"></iframe>
            </div>
            <div class="range-feedback" id="feedback" role="status" aria-live="polite">Follow the clues and test only inside this local sandbox.</div>
        </section>
        <section id="ctfReward" class="ctf-reward" hidden>
            <h3 id="ctfRewardMessage">Three stages complete. Submit the flag to finish.</h3>
            <form id="flagForm" class="flag-form">
                <label for="flagInput">Submit the flag</label>
                <div><input id="flagInput" name="flag" type="text" autocomplete="off" spellcheck="false" placeholder="FLAG{...}" required><button class="primary-action" type="submit">Submit flag</button></div>
                <p id="flagFeedback" role="status" aria-live="polite"></p>
            </form>
        </section>
    </section>
    <?php else: ?>
    <div class="range-layout" id="challengeWorkspace"<?= $requiresFinalUnlock && !$canOpenFinal ? ' hidden' : '' ?>>
        <aside class="challenge-sidebar">
            <div class="sidebar-heading"><span>CHALLENGES</span><span>01 — <?= sprintf('%02d', count($challenges)) ?></span></div>
            <nav aria-label="XSS challenges" id="challengeList">
                <?php $number = 0; foreach ($challenges as $id => $challenge): $number++; ?>
                <button type="button" class="challenge-button" data-challenge="<?= e($id) ?>">
                    <span class="challenge-number"><?= sprintf('%02d', $number) ?></span>
                    <span class="challenge-name"><?= e($challenge['title']) ?><small><?= e($challenge['type']) ?> <span>·</span> <?= e($challenge['level']) ?></small></span>
                    <span class="challenge-check" aria-label="Unsolved">○</span>
                </button>
                <?php endforeach; ?>
            </nav>
            <div class="sidebar-footer"><span class="live-dot"></span> Personal workspace</div>
        </aside>
        <section class="mission" aria-labelledby="missionTitle">
            <header class="mission-header">
                <div><div class="mission-badges"><span id="missionType">Reflected</span><span id="missionLevel">Starter</span></div><h2 id="missionTitle">Lost in dispatch</h2></div>
                <span id="missionStatus" class="mission-status" role="status">Not solved</span>
            </header>
            <div class="mission-objective"><span>OBJECTIVE</span><p id="missionGoal">Make the search results execute alert(1).</p></div>
            <details id="defensePanel" class="defense-panel" hidden><summary>Defense mode: how to prevent this issue</summary><p id="defenseText"></p></details>
            <div class="target-workspace">
                <header class="target-bar"><div class="window-dots" aria-hidden="true"><i></i><i></i><i></i></div><span id="targetRoute">dispatch / search</span><span class="target-local">LOCAL</span></header>
                <form id="challengeForm" class="target-controls">
                    <label for="payload" id="payloadLabel">Search deliveries</label>
                    <textarea id="payload" name="payload" rows="3" maxlength="6000" autocomplete="off" spellcheck="false" placeholder="Enter a value to test"></textarea>
                    <div class="target-actions"><button class="primary-action" id="runButton" type="submit">Search</button><button class="secondary-action" id="visitorButton" type="button" hidden>Open visitor view</button><button class="reset-action" id="resetButton" type="button">Reset challenge</button><span id="runState" role="status" aria-live="polite">Starting target…</span></div>
                </form>
                <iframe id="targetFrame" title="Relay target application" sandbox="allow-scripts" scrolling="no" referrerpolicy="no-referrer"></iframe>
            </div>
            <div class="range-feedback" id="feedback" role="status" aria-live="polite">Ready.</div>
            <section class="activity-panel" aria-label="Run activity"><div class="activity-heading"><span>ACTIVITY</span><span id="activityState">THIS SESSION</span></div><ol id="activityLog"></ol></section>
        </section>
    </div>
    <?php endif; ?>
</div>
<dialog id="executionDialog" class="execution-dialog" aria-labelledby="executionTitle" aria-describedby="executionValue">
    <h2 id="executionTitle"><?= $requiresFinalUnlock ? 'Signal captured' : 'JavaScript alert' ?></h2>
    <p id="executionContext"></p>
    <pre id="executionValue"></pre>
    <form method="dialog"><button class="primary-action" autofocus>Close</button></form>
</dialog>
<script type="application/json" id="rangeConfig"><?= range_json(['challenges' => $challenges, 'solved' => $solved, 'csrf' => range_csrf_token(), 'api' => range_url('shared/api.php'), 'previewMode' => $previewMode, 'initialStage' => $initialFinalStage]) ?></script>
    <?php endif; ?>
<div id="progressNotice" class="progress-notice" role="status" hidden></div>
<script type="application/json" id="rangeProgressConfig"><?= range_json(['allIds' => $allChallengeIds, 'regularIds' => $regularChallengeIds, 'scopeIds' => $scopeIds, 'solved' => $sessionSolved, 'requiresFinalUnlock' => $requiresFinalUnlock && !$previewMode, 'api' => range_url('shared/api.php'), 'csrf' => range_csrf_token()]) ?></script>
<script src="<?= e(range_url('assets/progress.js?v=' . filemtime(dirname(__DIR__) . '/assets/progress.js'))) ?>" defer></script>
<?php if (!$isStory && (!$requiresFinalUnlock || $canOpenFinal)): ?>
<?php if ($requiresFinalUnlock): ?>
<script src="<?= e(range_url('assets/final-ctf.js?v=' . filemtime(dirname(__DIR__) . '/assets/final-ctf.js'))) ?>" defer></script>
<?php else: ?>
<script src="<?= e(range_url('assets/lab.js?v=' . filemtime(dirname(__DIR__) . '/assets/lab.js'))) ?>" defer></script>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/footer.php'; ?>
