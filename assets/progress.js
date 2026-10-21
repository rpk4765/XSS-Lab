'use strict';

(() => {
    const config = JSON.parse(document.getElementById('rangeProgressConfig').textContent);
    const validIds = new Set(config.allIds);
    const regularIds = config.regularIds;
    const savedIds = new Set();
    const storageKey = 'relay-xss-progress-v1';
    const notice = document.getElementById('progressNotice');

    function showStorageError() {
        if (notice) {
            notice.hidden = false;
            notice.textContent = 'Progress could not be saved on this device. It will last until this PHP session ends.';
        }
    }

    try {
        const raw = localStorage.getItem(storageKey);
        if (raw) {
            const stored = JSON.parse(raw);
            if (!Array.isArray(stored)) {
                throw new TypeError('Saved progress is not a list.');
            }
            // Completion comes from the server session; old local IDs are untrusted.
        }
    } catch {
        showStorageError();
    }

    config.solved.forEach(id => {
        if (validIds.has(id)) savedIds.add(id);
    });

    function persist() {
        try {
            localStorage.setItem(storageKey, JSON.stringify([...savedIds]));
        } catch {
            showStorageError();
        }
    }

    function paint() {
        const visibleSolved = config.scopeIds.filter(id => savedIds.has(id)).length;
        const count = document.getElementById('solvedCount');
        const ring = document.getElementById('progressRing');
        if (count) count.textContent = visibleSolved;
        if (ring) ring.style.strokeDashoffset = 113.1 * (1 - visibleSolved / Math.max(config.scopeIds.length, 1));

        document.querySelectorAll('[data-challenge]').forEach(button => {
            const isSolved = savedIds.has(button.dataset.challenge);
            button.classList.toggle('solved', isSolved);
            const check = button.querySelector('.challenge-check');
            if (check) {
                check.textContent = isSolved ? '✓' : '○';
                check.setAttribute('aria-label', isSolved ? 'Solved' : 'Unsolved');
            }
        });

        const finalUnlocked = regularIds.every(id => savedIds.has(id));
        document.querySelectorAll('[data-final-nav]').forEach(link => {
            link.classList.toggle('is-locked', !finalUnlocked);
            link.setAttribute('aria-disabled', 'false');
            link.tabIndex = 0;
            const indicator = link.querySelector('.final-lock-indicator');
            if (indicator) indicator.textContent = finalUnlocked ? 'OPEN' : 'LOCKED';
            if (link.classList.contains('final-mission-link')) {
                link.firstChild.textContent = finalUnlocked ? 'Start the final mission ' : 'View locked final mission ';
                const arrow = link.querySelector('span[aria-hidden="true"]');
                if (arrow) arrow.textContent = finalUnlocked ? '→' : '×';
            }
            link.title = finalUnlocked ? 'Open the final mission' : 'View the mission — solve all 12 labs to play';
        });

        const lockScreen = document.getElementById('finalLockScreen');
        const workspace = document.getElementById('challengeWorkspace');
        const finalCtf = document.getElementById('finalCtf');
        if (config.requiresFinalUnlock && lockScreen && workspace) {
            lockScreen.hidden = finalUnlocked;
            workspace.hidden = !finalUnlocked;
            const progress = document.getElementById('finalLockProgress');
            if (progress) {
                const completed = regularIds.filter(id => savedIds.has(id)).length;
                progress.textContent = completed + ' / ' + regularIds.length + ' targets complete';
            }
        }
        if (config.requiresFinalUnlock && lockScreen && finalCtf) {
            lockScreen.hidden = finalUnlocked;
            finalCtf.hidden = !finalUnlocked;
            const progress = document.getElementById('finalLockProgress');
            if (progress) {
                const completed = regularIds.filter(id => savedIds.has(id)).length;
                progress.textContent = completed + ' / ' + regularIds.length + ' targets complete';
            }
        }
    }

    async function sync() {
        const response = await fetch(config.api, {
            method: 'POST',
            credentials: 'same-origin',
            body: new URLSearchParams({
                action: 'sync_progress',
                csrf: config.csrf,
                solved: JSON.stringify(regularIds.filter(id => savedIds.has(id)))
            })
        });
        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch {
            throw new Error('Saved progress could not be synced. Reload the lab and retry.');
        }
        if (!response.ok) {
            throw new Error(data.error || 'Saved progress could not be synced. Retry.');
        }
        savedIds.clear();
        data.solved.forEach(id => {
            if (validIds.has(id)) savedIds.add(id);
        });
        persist();
        paint();
        return data;
    }

    persist();
    paint();

    window.xssProgress = {
        has(id) {
            return savedIds.has(id);
        },
        mark(id) {
            if (validIds.has(id)) savedIds.add(id);
            persist();
            paint();
        },
        clear(id) {
            savedIds.delete(id);
            persist();
            paint();
        },
        merge(ids) {
            savedIds.clear();
            ids.forEach(id => {
                if (validIds.has(id)) savedIds.add(id);
            });
            persist();
            paint();
        },
        sync,
        get finalUnlocked() {
            return regularIds.every(id => savedIds.has(id));
        },
    };
})();
