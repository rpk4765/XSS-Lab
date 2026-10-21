// Independent execution check. No npm packages; uses Node 22+ and local Edge.
'use strict';
const {spawn} = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const crypto = require('node:crypto');
let input = '';
process.stdin.setEncoding('utf8');
process.stdin.on('data', chunk => { input += chunk; });
process.stdin.on('end', async () => {
    let browser, socket, directory;
    const timeout = setTimeout(() => finish(false), 20000);
    let finished = false;
    function finish(ok) {
        if (finished) return;
        finished = true;
        clearTimeout(timeout);
        socket?.close();
        browser?.kill();
        process.stdout.write(JSON.stringify({executed: ok}));
        setTimeout(() => {
            if (directory) { try { fs.rmSync(directory, {recursive: true, force: true}); } catch {} }
            process.exit(ok ? 0 : 1);
        }, 200);
    }
    try {
        const {html, fragment, message} = JSON.parse(input);
        directory = fs.mkdtempSync(path.join(os.tmpdir(), 'relay-verify-'));
        const executable = ['C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', 'C:/Program Files/Google/Chrome/Application/chrome.exe'].find(p => fs.existsSync(p));
        if (!executable) throw Error('No supported local browser.');
        browser = spawn(executable, ['--headless=new', '--disable-gpu', '--disable-site-isolation-trials', '--disable-features=IsolateOrigins,site-per-process', '--no-first-run', '--no-default-browser-check', '--disable-background-networking', '--remote-debugging-port=0', '--remote-debugging-address=127.0.0.1', '--user-data-dir=' + directory, 'about:blank'], {windowsHide: true, stdio: 'ignore'});
        browser.on('error', () => finish(false));
        const portFile = path.join(directory, 'DevToolsActivePort');
        while (!fs.existsSync(portFile)) await new Promise(r => setTimeout(r, 40));
        const [port, endpoint] = fs.readFileSync(portFile, 'utf8').trim().split(/\r?\n/);
        socket = new WebSocket('ws://127.0.0.1:' + port + endpoint);
        await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
        let sequence = 0, session;
        const pending = new Map();
        const contexts = new Set();
        const binding = 'relay_' + crypto.randomBytes(16).toString('hex');
        socket.onmessage = event => {
            const data = JSON.parse(event.data);
            if (data.id) { const callback = pending.get(data.id); if (callback) { pending.delete(data.id); data.error ? callback.reject(Error(data.error.message)) : callback.resolve(data.result); } }
            if (data.method === 'Runtime.bindingCalled' && data.params.name === binding && data.params.payload === '1') finish(true);
            if (data.method === 'Runtime.executionContextCreated' && data.params.context.auxData?.isDefault) contexts.add(data.params.context.id);
            if (data.method === 'Runtime.executionContextDestroyed') contexts.delete(data.params.executionContextId);
            if (data.method === 'Runtime.executionContextsCleared') contexts.clear();
        };
        function send(method, params = {}, target = session) {
            return new Promise((resolve, reject) => { const id = ++sequence; pending.set(id, {resolve, reject}); socket.send(JSON.stringify({id, method, params, ...(target ? {sessionId: target} : {})})); });
        }
        const {targetId} = await send('Target.createTarget', {url: 'about:blank'}, null);
        session = (await send('Target.attachToTarget', {targetId, flatten: true}, null)).sessionId;
        await send('Page.enable');
        await send('Page.bringToFront');
        await send('Runtime.enable');
        await send('Log.enable');
        await send('Runtime.addBinding', {name: binding});
        await send('Page.addScriptToEvaluateOnNewDocument', {source: `(() => {const report = globalThis[${JSON.stringify(binding)}]; delete globalThis[${JSON.stringify(binding)}]; Object.defineProperty(globalThis,'alert',{configurable:false,writable:false,value(v){if(String(v)==='1')report('1');}});})();`});
        const escape = s => s.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
        const deliveryScript = message !== null ? '<script>dispatchEvent(new MessageEvent("message",{data:{type:"relay-preview",html:' + JSON.stringify(message).replace(/</g, '\\u003c') + '}}));</script>' : '';
        const preparedHtml = (fragment ? '<script>location.hash=' + JSON.stringify(fragment) + ';</script>' : '') + html + deliveryScript;
        const frameHtml = '<!doctype html><meta http-equiv="Content-Security-Policy" content="default-src data:; script-src &#39;unsafe-inline&#39;; style-src &#39;unsafe-inline&#39;; img-src data:; connect-src &#39;none&#39;; form-action &#39;none&#39;; base-uri &#39;none&#39;"><iframe sandbox="allow-scripts" srcdoc="' + escape(preparedHtml) + '"></iframe>';
        await send('Page.navigate', {url: 'data:text/html;charset=utf-8,' + encodeURIComponent(frameHtml)});
        // Recreate DOM inputs and ordinary user interactions inside the isolated target.
        for (let attempt = 0; attempt < 8 && !finished; attempt++) {
            await new Promise(r => setTimeout(r, 200));
            for (const contextId of contexts) {
                await send('Runtime.evaluate', {contextId, expression: `(() => { if (!location.href.startsWith('about:srcdoc')) return; document.querySelectorAll('button,input,svg,svg *').forEach(el=>{el.focus?.();el.click?.();el.dispatchEvent(new MouseEvent('mouseover',{bubbles:true}));}); })();`}).catch(() => {});
            }
        }
        finish(false);
    } catch (error) { process.stderr.write(String(error)); finish(false); }
});
