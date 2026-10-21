'use strict';

(() => {
    const configElement = document.getElementById('rangeConfig');
    if (!configElement) return;
    const config = JSON.parse(configElement.textContent);
    if (!config.previewMode && !window.xssProgress.finalUnlocked) return;

    const challengeId = Object.keys(config.challenges)[0];
    const challenge = config.challenges[challengeId];
    const frame = document.getElementById('targetFrame');
    const input = document.getElementById('payload');
    const form = document.getElementById('challengeForm');
    const runButton = document.getElementById('runButton');
    const resetButton = document.getElementById('resetButton');
    const feedback = document.getElementById('feedback');
    const runState = document.getElementById('runState');
    const dialog = document.getElementById('executionDialog');
    const stages = [
        {
            title: 'Night gateway',
            route: 'relay / dispatch terminal',
            field: 'Dispatch envelope (JSON)',
            goal: 'Recover the gateway protocol and reach the operator terminal. Trigger alert(1) after the envelope passes inspection.',
            clue: 'NIGHT LOG 01: The wire and the operator do not see the same representation. Recover the protocol fragment inside the target.',
        },
        {
            title: 'Broken archive patch',
            route: 'relay / three-copy archive',
            field: 'Handoff note',
            goal: 'Investigate the stored archive and its legacy patch. Trigger alert(1) without HTML delimiters or line separators.',
            clue: 'ARCHIVE NOTE 02: The patch was reviewed one character at a time. Review how characters interact instead.',
        },
        {
            title: 'Dead-letter recovery',
            route: 'relay / restricted console',
            field: 'Transport envelope (JSON)',
            goal: 'Reconstruct the transport protocol, supply a valid integrity stamp, and trigger alert(1) from the recovered packet.',
            clue: 'FINAL TRACE 03: A valid transport packet is not necessarily a safe recovered document. Recover the contract inside the target.',
        },
    ];
    let completedStage = 0;
    let activeStage = config.initialStage;
    let activeRun = null;
    let generation = 0;
    let complete = false;

    async function request(action, fields = {}) {
        const response = await fetch(config.api, {
            method: 'POST',
            credentials: 'same-origin',
            body: new URLSearchParams({action, challenge: challengeId, csrf: config.csrf, ...fields}),
        });
        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch {
            throw new Error('Session expired or server unavailable. Reload the CTF and retry.');
        }
        if (!response.ok) throw new Error(data.error || 'Request failed. Please retry.');
        return data;
    }

    function showDialog(title, value, context = challenge.app) {
        document.getElementById('executionTitle').textContent = title;
        document.getElementById('executionContext').textContent = context;
        document.getElementById('executionValue').textContent = String(value).slice(0, 160);
        if (!dialog.open) dialog.showModal();
    }

    function showFlagCipherDialog() {
        showDialog(
            'Encrypted archive recovered',
            'SYNT{ynfg_unaqbss}',
            'Decode hint: ROT13 shifts each letter 13 places (A ↔ N, B ↔ O). Keep braces and underscores unchanged, then submit the decoded flag.'
        );
    }

    function celebrateVictory() {
        // Reuse the accessible modal and keep transient effects out of saved progress.
        document.getElementById('victoryConfetti')?.remove();
        dialog.classList.add('is-victory');
        showDialog(
            'MISSION ACCOMPLISHED',
            'ALL 3 STAGES CLEARED — FLAG ACCEPTED',
            config.previewMode
                ? 'Relay restored! Preview victory — progress was not saved.'
                : 'Relay restored! You cracked every stage and recovered the final flag.'
        );
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        const confetti = document.createElement('div');
        confetti.id = 'victoryConfetti';
        confetti.setAttribute('aria-hidden', 'true');
        const colors = ['#ff6476', '#fbbf24', '#60a5fa', '#a7f3d0', '#ffffff'];
        for (let index = 0; index < 64; index++) {
            const piece = document.createElement('i');
            piece.style.setProperty('--x', Math.random() * 100 + '%');
            piece.style.setProperty('--delay', Math.random() * 0.65 + 's');
            piece.style.setProperty('--drift', (Math.random() - 0.5) * 220 + 'px');
            piece.style.setProperty('--color', colors[index % colors.length]);
            confetti.append(piece);
        }
        // Inside the modal so the effect appears in the browser's top layer.
        dialog.append(confetti);
        setTimeout(() => confetti.remove(), 4500);
    }

    dialog.addEventListener('close', () => {
        dialog.classList.remove('is-victory');
        document.getElementById('victoryConfetti')?.remove();
    });

    function decodeRot13(value) {
        return value.replace(/[a-z]/gi, letter => {
            const base = letter <= 'Z' ? 65 : 97;
            return String.fromCharCode((letter.charCodeAt(0) - base + 13) % 26 + base);
        });
    }

    function paintStages() {
        document.querySelectorAll('[data-stage]').forEach(button => {
            const number = Number(button.dataset.stage);
            const done = number <= completedStage;
            const available = number <= completedStage + 1;
            button.disabled = !available;
            button.classList.toggle('is-complete', done);
            button.classList.toggle('is-active', number === activeStage);
            button.setAttribute('aria-current', number === activeStage ? 'step' : 'false');
            button.querySelector('.ctf-stage-state').textContent = done ? 'DONE' : (available ? 'OPEN' : 'LOCKED');
        });
        document.getElementById('missionStatus').textContent = complete ? 'CTF complete' : (config.previewMode ? 'Preview' : 'Stage ' + String(activeStage).padStart(2, '0'));
    }

    function setLocation(stage) {
        activeStage = stage;
        updateFlagFormVisibility();
        const details = stages[stage - 1];
        generation++;
        activeRun = null;
        frame.onload = null;
        frame.src = 'about:blank';
        input.value = '';
        document.getElementById('ctfStageKicker').textContent = 'STAGE 0' + stage + ' / 03';
        document.getElementById('ctfStageTitle').textContent = details.title;
        document.getElementById('missionGoal').textContent = details.goal;
        document.getElementById('ctfClue').textContent = details.clue;
        document.getElementById('targetRoute').textContent = details.route;
        document.getElementById('payloadLabel').textContent = details.field;
        runButton.textContent = config.previewMode ? 'Run preview' : 'Run stage';
        paintStages();
        const url = new URL(location.href);
        url.searchParams.set('stage', String(stage));
        if (config.previewMode) url.searchParams.set('preview', '1');
        else url.searchParams.delete('preview');
        history.replaceState(null, '', url);
    }

    async function launch() {
        const version = ++generation;
        activeRun = null;
        runButton.disabled = resetButton.disabled = true;
        runState.textContent = 'Preparing sandbox…';
        feedback.textContent = config.previewMode ? 'Preview only. No input or progress will be saved.' : 'Launching local challenge target…';
        feedback.className = 'range-feedback';
        try {
            const result = await request(config.previewMode ? 'preview_launch' : 'launch', {
                stage: String(activeStage),
                payload: input.value,
            });
            if (version !== generation) return;
            activeRun = {
                id: challengeId,
                token: result.run,
                stage: activeStage,
                pending: false,
                preview: config.previewMode,
            };
            frame.onload = () => {
                if (!activeRun || activeRun.token !== result.run) return;
                runState.textContent = config.previewMode ? 'Preview target live' : 'Sandbox target live';
            };
            frame.src = new URL(result.url, location.origin).toString();
            frame.title = 'Stage ' + activeStage + ' local XSS challenge target';
            feedback.textContent = config.previewMode
                ? 'Preview loaded. Test the local page; completion will not be recorded.'
                : 'Target ready. Follow the clue and trigger alert(1) in the sandbox.';
        } catch (error) {
            if (version !== generation) return;
            feedback.textContent = error.message;
            feedback.className = 'range-feedback error';
            runState.textContent = 'Launch blocked';
        } finally {
            if (version === generation) runButton.disabled = resetButton.disabled = false;
        }
    }

    document.querySelectorAll('[data-stage]').forEach(button => {
        button.addEventListener('click', () => {
            const nextStage = Number(button.dataset.stage);
            if (nextStage > completedStage + 1) return;
            if (config.previewMode && completedStage === 3) clearFlagSubmission();
            setLocation(nextStage);
            if (completedStage === 3 && nextStage === 3) {
                revealFlagForm(flagFormMessage());
            }
            launch();
        });
    });

    form.addEventListener('submit', event => {
        event.preventDefault();
        launch();
    });

    resetButton.addEventListener('click', async () => {
        if (config.previewMode) {
            completedStage = 0;
            document.getElementById('ctfReward').hidden = true;
            clearFlagSubmission();
            setLocation(1);
            await launch();
            return;
        }
        const version = ++generation;
        runButton.disabled = resetButton.disabled = true;
        try {
            const result = await request('reset');
            if (version !== generation) return;
            completedStage = 0;
            complete = false;
            window.xssProgress.clear(challengeId);
            window.xssProgress.merge(result.solved);
            setLocation(1);
            feedback.textContent = 'CTF restarted. Begin again with the first clue.';
            await launch();
        } catch (error) {
            feedback.textContent = error.message;
            feedback.className = 'range-feedback error';
        } finally {
            runButton.disabled = resetButton.disabled = false;
        }
    });

    const flagForm = document.getElementById('flagForm');
    flagForm?.addEventListener('submit', async event => {
        event.preventDefault();
        const submit = flagForm.querySelector('button[type="submit"]');
        submit.disabled = true;
        try {
            if (config.previewMode) {
                if (document.getElementById('flagInput').value.trim() !== decodeRot13('SYNT{ynfg_unaqbss}')) {
                    throw new Error('Incorrect flag. Check the preview reward and try again.');
                }
                document.getElementById('flagFeedback').textContent = 'Preview flag accepted. Preview completion was not saved.';
                celebrateVictory();
                return;
            }
            const result = await request('submit_flag', {flag: document.getElementById('flagInput').value});
            complete = result.complete;
            window.xssProgress.merge(result.solved);
            window.xssProgress.mark(challengeId);
            paintStages();
            document.getElementById('flagFeedback').textContent = 'Flag accepted. CTF complete!';
            feedback.textContent = 'CTF complete — flag accepted.';
            feedback.className = 'range-feedback success';
            celebrateVictory();
        } catch (error) {
            document.getElementById('flagFeedback').textContent = error.message;
            document.getElementById('flagFeedback').className = 'range-feedback error';
        } finally {
            submit.disabled = false;
        }
    });

    window.addEventListener('message', async event => {
        const data = event.data;
        if (event.source !== frame.contentWindow || event.origin !== 'null' ||
            !activeRun || !data || data.run !== activeRun.token) return;
        if (data.type === 'relay-resize' && Number.isInteger(data.height) && data.height > 0 && data.height <= 12000) {
            frame.style.height = data.height + 'px';
            return;
        }
        if (data.type === 'relay-alert') return;
        if (data.type === 'relay-hint' && typeof data.answer === 'string' && data.answer.length <= 8) {
            const url = new URL(frame.src);
            url.searchParams.set('hint_answer', data.answer);
            frame.src = url.href;
            return;
        }
        if (data.type !== 'relay-execution' || data.value !== '1' ||
            typeof data.proof !== 'string' || activeRun.pending) return;

        const run = activeRun;
        run.pending = true;
        if (run.preview) {
            completedStage = Math.max(completedStage, run.stage);
            if (completedStage === 3) {
                showFlagCipherDialog();
                revealFlagForm('Preview complete — enter the flag to test submission. Preview progress is not saved.');
                feedback.textContent = 'All three preview stages completed. No flag or progress was saved.';
                feedback.className = 'range-feedback success';
                paintStages();
                return;
            }
            feedback.textContent = 'Preview stage ' + String(completedStage).padStart(2, '0') + ' complete. The next preview stage is unlocked.';
            feedback.className = 'range-feedback success';
            setLocation(completedStage + 1);
            await launch();
            return;
        }
        try {
            runState.textContent = 'Verifying XSS in an independent browser…';
            const result = await request('solve', {run: run.token, proof: data.proof});
            if (activeRun !== run) return;
            completedStage = result.completedStage;
            paintStages();
            if (result.ctfReady) {
                showFlagCipherDialog();
                revealFlagForm('Three stages complete. Submit the flag below to finish the CTF.');
                feedback.textContent = 'All three stages complete. Submit the flag below.';
                feedback.className = 'range-feedback success';
                return;
            }
            feedback.textContent = 'Stage 0' + completedStage + ' complete. The next stage is unlocked.';
            feedback.className = 'range-feedback success';
            setLocation(completedStage + 1);
            await launch();
        } catch (error) {
            if (activeRun !== run) return;
            run.pending = false;
            feedback.textContent = error.message;
            feedback.className = 'range-feedback error';
        }
    });

    async function initialize() {
        if (!config.previewMode) {
            const {finalState: result} = await window.xssProgress.sync();
            completedStage = result.completedStage;
            complete = result.complete;
            if (complete) {
                window.xssProgress.mark(challengeId);
                feedback.textContent = 'CTF complete — flag accepted.';
                feedback.className = 'range-feedback success';
            }
            if (completedStage === 3 && !complete && result.flagCipher) {
                showFlagCipherDialog();
            }
        }
        const startStage = config.previewMode
            ? config.initialStage
            : Math.min(completedStage + 1, 3);
        setLocation(startStage);
        if (completedStage === 3) revealFlagForm(flagFormMessage());
        if (!complete) await launch();
        else {
            runState.textContent = 'CTF complete';
        }
    }

    function revealFlagForm(message) {
        if (!updateFlagFormVisibility()) return;
        const panel = document.getElementById('ctfReward');
        document.getElementById('ctfRewardMessage').textContent = message;
        clearFlagSubmission();
        panel.scrollIntoView({behavior: 'smooth', block: 'center'});
    }

    function updateFlagFormVisibility() {
        const panel = document.getElementById('ctfReward');
        const visible = activeStage === 3 && completedStage === 3;
        panel.hidden = !visible;
        return visible;
    }

    function flagFormMessage() {
        if (config.previewMode) {
            return 'Preview complete — enter the flag to test submission. Preview progress is not saved.';
        }
        return complete
            ? 'CTF already complete. You can revisit all three stages.'
            : 'Three stages complete. Submit the flag below to finish the CTF.';
    }

    function clearFlagSubmission() {
        document.getElementById('flagInput').value = '';
        document.getElementById('flagFeedback').textContent = '';
        document.getElementById('flagFeedback').className = '';
        const submit = flagForm?.querySelector('button[type="submit"]');
        if (submit) submit.disabled = false;
    }

    initialize().catch(error => {
        feedback.textContent = error.message;
        feedback.className = 'range-feedback error';
        runState.textContent = 'CTF unavailable';
    });
})();
