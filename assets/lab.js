'use strict';

(() => {
    const config = JSON.parse(document.getElementById('rangeConfig').textContent);
    if (config.requiresFinalUnlock && !window.xssProgress.finalUnlocked) return;
    const ids = Object.keys(config.challenges);
    const frame = document.getElementById('targetFrame');
    const input = document.getElementById('payload');
    const runButton = document.getElementById('runButton');
    const visitorButton = document.getElementById('visitorButton');
    const resetButton = document.getElementById('resetButton');
    const feedback = document.getElementById('feedback');
    const state = document.getElementById('runState');
    const drafts = new Map();
    let solved = new Set(config.solved);
    let activeId;
    let activeRun = null;
    let generation = 0;
    ids.forEach(id => {
        if (window.xssProgress.has(id)) solved.add(id);
    });

    function showExecutionPopup(run, value) {
        if (run.popupShown) return;
        run.popupShown = true;
        const dialog = document.getElementById('executionDialog');
        document.getElementById('executionContext').textContent = config.challenges[run.id].app;
        document.getElementById('executionValue').textContent = String(value).slice(0, 160);
        if (!dialog.open) dialog.showModal();
    }

    async function request(action, challenge, fields = {}) {
        const response = await fetch(config.api, {
            method: 'POST',
            credentials: 'same-origin',
            body: new URLSearchParams({action, challenge, csrf: config.csrf, ...fields})
        });
        const text = await response.text();
        let data;
        try { data = JSON.parse(text); } catch {
            throw new Error('Session expired or server unavailable. Reload the lab and retry.');
        }
        if (!response.ok) throw new Error(data.error || 'Request failed. Please retry.');
        return data;
    }

    function activity(message) {
        const item = document.createElement('li');
        const time = document.createElement('time');
        time.textContent = new Date().toLocaleTimeString([], {hour: '2-digit', minute: '2-digit', second: '2-digit'});
        const text = document.createElement('span');
        text.textContent = message;
        item.append(time, text);
        const log = document.getElementById('activityLog');
        log.prepend(item);
        while (log.children.length > 5) log.lastElementChild.remove();
    }

    function busy(value) {
        runButton.disabled = visitorButton.disabled = resetButton.disabled = value;
    }

    function updateProgress() {
        document.getElementById('solvedCount').textContent = solved.size;
        document.getElementById('progressRing').style.strokeDashoffset = 113.1 * (1 - solved.size / ids.length);
        document.querySelectorAll('[data-challenge]').forEach(button => {
            const done = solved.has(button.dataset.challenge);
            button.classList.toggle('solved', done);
            button.classList.toggle('active', button.dataset.challenge === activeId);
            button.setAttribute('aria-current', button.dataset.challenge === activeId ? 'step' : 'false');
            const check = button.querySelector('.challenge-check');
            check.textContent = done ? '✓' : '○';
            check.setAttribute('aria-label', done ? 'Solved' : 'Unsolved');
        });
        const status = document.getElementById('missionStatus');
        status.textContent = solved.has(activeId) ? '✓ Solved' : 'Not solved';
        status.classList.toggle('is-solved', solved.has(activeId));
        document.getElementById('defensePanel').hidden = !solved.has(activeId);
    }

    async function launch() {
        const id = activeId;
        const version = ++generation;
        const challenge = config.challenges[id];
        const value = input.value;
        busy(true);
        activeRun = null;
        state.textContent = 'Loading target…';
        feedback.textContent = 'Opening ' + challenge.app + '…';
        feedback.className = 'range-feedback';
        try {
            if (config.previewMode) {
                const result = await request('preview_launch', id, {payload: value});
                if (version !== generation) return;
                activeRun = {id, token: result.run, pending: false, preview: true};
                frame.onload = () => {
                    if (!activeRun || activeRun.token !== result.run) return;
                    state.textContent = 'Preview target live';
                };
                frame.src = new URL(result.url, location.origin).toString();
                frame.title = challenge.app + ' — read-only preview target';
                feedback.textContent = 'Preview opened. Input and completion are not saved.';
                feedback.className = 'range-feedback';
                activity('Opened the final mission in read-only preview mode.');
                return;
            }
            if (challenge.type === 'Final Mission') {
                feedback.textContent = 'Checking completed targets…';
                await window.xssProgress.sync();
                if (!window.xssProgress.finalUnlocked) {
                    throw new Error('Solve all 12 Reflected, Stored, and DOM targets to unlock the final mission.');
                }
            }
            const result = await request('launch', id, {payload: value});
            if (version !== generation) return;
            activeRun = {id, token: result.run, pending: false};
            frame.onload = () => {
                if (!activeRun || activeRun.token !== result.run) return;
                if (id === 'message') {
                    frame.contentWindow.postMessage({type: 'relay-preview', html: value}, '*');
                }
                state.textContent = 'Target live';
            };
            const url = new URL(result.url, location.origin);
            if (challenge.client && id !== 'message') {
                url.hash = new URLSearchParams({input: value}).toString();
            }
            frame.src = url.toString();
            frame.title = challenge.app + ' — challenge target';
            feedback.textContent = challenge.stored ? 'Visitor view opened. Saved entries loaded.' : 'Target running. Waiting for alert(1).';
            activity(challenge.stored ? 'Opened visitor view with saved entries.' : 'Started a new target run.');
        } catch (error) {
            if (version !== generation) return;
            feedback.textContent = error.message;
            feedback.className = 'range-feedback error';
            state.textContent = 'Run failed';
        } finally {
            if (version === generation) busy(false);
        }
    }

    function select(id) {
        if (!config.challenges[id]) id = ids[0];
        if (activeId) drafts.set(activeId, input.value);
        generation++;
        activeRun = null;
        activeId = id;
        frame.onload = null;
        frame.src = 'about:blank';
        const challenge = config.challenges[id];
        document.getElementById('missionTitle').textContent = challenge.title;
        document.getElementById('missionType').textContent = challenge.type;
        document.getElementById('missionLevel').textContent = challenge.level;
        document.getElementById('missionGoal').textContent = config.previewMode
            ? 'Enter a handoff note and run the preview. Follow it through the reflected search, stored handoff, and DOM preview. Preview data is not saved.'
            : challenge.goal;
        document.getElementById('defenseText').textContent = challenge.defense;
        document.getElementById('targetRoute').textContent = challenge.route;
        document.getElementById('payloadLabel').textContent = challenge.field;
        runButton.textContent = config.previewMode ? 'Run preview' : challenge.button;
        visitorButton.hidden = !challenge.stored || config.previewMode;
        visitorButton.textContent = challenge.view || 'Open visitor view';
        resetButton.textContent = config.previewMode ? 'Clear preview' : 'Reset challenge';
        input.value = drafts.get(id) || '';
        document.getElementById('activityLog').replaceChildren();
        const url = new URL(location.href);
        const params = new URLSearchParams({challenge: id});
        if (config.previewMode) params.set('preview', '1');
        url.search = params.toString();
        url.hash = '';
        history.replaceState(null, '', url);
        updateProgress();
        launch();
    }

    document.querySelectorAll('[data-challenge]').forEach(button => {
        button.addEventListener('click', () => select(button.dataset.challenge));
    });

    document.getElementById('challengeForm').addEventListener('submit', async event => {
        event.preventDefault();
        if (config.previewMode) return launch();
        if (!config.challenges[activeId].stored) return launch();
        const id = activeId;
        const version = generation;
        busy(true);
        try {
            const result = await request('save', id, {payload: input.value});
            if (version !== generation) return;
            feedback.textContent = 'Saved. Open the visitor view to test your entry.';
            feedback.className = 'range-feedback';
            state.textContent = result.entries + ' saved entries in view';
            activity('Entry saved to this browser session. Visitor view has not been refreshed.');
        } catch (error) {
            if (version === generation) {
                feedback.textContent = error.message;
                feedback.className = 'range-feedback error';
            }
        } finally {
            if (version === generation) busy(false);
        }
    });

    visitorButton.addEventListener('click', launch);
    resetButton.addEventListener('click', async () => {
        if (config.previewMode) {
            input.value = '';
            drafts.delete(activeId);
            activeRun = null;
            frame.src = 'about:blank';
            updateProgress();
            await launch();
            return;
        }
        const id = activeId;
        const version = ++generation;
        activeRun = null;
        frame.src = 'about:blank';
        busy(true);
        try {
            const result = await request('reset', id);
            if (version !== generation) return;
            window.xssProgress.clear(id);
            window.xssProgress.merge(result.solved);
            solved = new Set(ids.filter(id => window.xssProgress.has(id)));
            drafts.delete(id);
            input.value = '';
            updateProgress();
            activity('Challenge reset. Your entries and completion were cleared.');
            await launch();
        } catch (error) {
            if (version === generation) {
                feedback.textContent = error.message;
                feedback.className = 'range-feedback error';
            }
        } finally {
            if (version === generation) busy(false);
        }
    });

    window.addEventListener('message', async event => {
        const data = event.data;
        // Sandboxed targets have opaque origins. Bind reports to the current window and run.
        if (event.source !== frame.contentWindow || event.origin !== 'null' ||
            !activeRun || !data || data.run !== activeRun.token) return;
        if (data.type === 'relay-resize' && Number.isInteger(data.height) && data.height > 0 && data.height <= 12000) {
            frame.style.height = data.height + 'px';
            return;
        }
        if (data.type === 'relay-alert') {
            activity('Captured alert(' + String(data.value).slice(0, 100) + '). Expected alert(1).');
            if (typeof data.value === 'string') showExecutionPopup(activeRun, data.value);
            return;
        }
        if (data.type !== 'relay-execution' || data.value !== '1' ||
            typeof data.proof !== 'string' || activeRun.pending) return;
        const run = activeRun;
        run.pending = true;
        // An in-page modal stays visible even when the browser suppresses alerts.
        showExecutionPopup(run, data.value);
        if (run.preview) {
            feedback.textContent = 'Preview only — no progress was saved.';
            feedback.className = 'range-feedback';
            return;
        }
        try {
            state.textContent = 'Verifying XSS in an independent browser…';
            const result = await request('solve', run.id, {run: run.token, proof: data.proof});
            if (activeRun !== run) return;
            window.xssProgress.merge(result.solved);
            window.xssProgress.mark(run.id);
            solved = new Set(ids.filter(id => window.xssProgress.has(id)));
            updateProgress();
            feedback.textContent = 'Challenge solved — alert(1) executed in the target.';
            feedback.className = 'range-feedback success';
            activity('JavaScript execution confirmed. Completion saved.');
            state.textContent = 'Signal captured';
        } catch (error) {
            if (activeRun !== run) return;
            run.pending = false;
            feedback.textContent = error.message;
            feedback.className = 'range-feedback error';
        }
    });

    const params = new URLSearchParams(location.search);
    const legacy = location.hash === '#stored' ? 'inbox' : (location.hash.startsWith('#dom') ? 'routes' : 'dispatch');
    select(params.get('challenge') || legacy);
})();
