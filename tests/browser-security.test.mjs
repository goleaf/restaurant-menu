import assert from 'node:assert/strict';
import { execFileSync, spawn } from 'node:child_process';
import { once } from 'node:events';
import { realpathSync } from 'node:fs';
import { createRequire } from 'node:module';
import { tmpdir } from 'node:os';
import { basename, dirname, join, sep } from 'node:path';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const require = createRequire(import.meta.url);
const preload = require('./Support/browser-playwright-shutdown.cjs');

test('the browser adapter tightens sandbox and CSP options without changing unrelated options', () => {
    class Chromium {
        launch(progress, options, logger) { return { progress, options, logger }; }
    }
    class Browser {
        newContext(progress, options) { return { progress, options }; }
    }
    const server = { createPlaywright: () => ({ chromium: new Chromium() }), Browser };
    assert.equal(typeof preload.installBrowserSecurity, 'function');
    preload.installBrowserSecurity(server, '1.63.0');
    preload.installBrowserSecurity(server, '1.63.0');
    const launch = new Chromium().launch('progress', { chromiumSandbox: false, headless: true }, 'logger');
    assert.deepEqual(launch, { progress: 'progress', options: { chromiumSandbox: true, headless: true }, logger: 'logger' });
    assert.deepEqual(new Browser().newContext('progress', { bypassCSP: true, locale: 'lt' }), {
        progress: 'progress', options: { bypassCSP: false, locale: 'lt' },
    });
    assert.throws(() => preload.installBrowserSecurity(server, '1.64.0'), /unsupported Playwright version/i);
    assert.throws(() => preload.installBrowserSecurity({ createPlaywright: () => ({ chromium: {} }), Browser }, '1.63.0'), /launch boundary/i);
});

test('the actual Pest server boundary keeps Chromium sandboxed and blocks inline CSP violations', { timeout: 30000 }, async t => {
    const root = dirname(dirname(fileURLToPath(import.meta.url)));
    const preloadPath = join(root, 'tests/Support/browser-playwright-shutdown.cjs');
    const child = spawn(process.execPath, [join(root, 'node_modules/.bin/playwright'), 'run-server', '--host', '127.0.0.1', '--port', '0', '--mode', 'launchServer'], {
        cwd: root,
        env: { ...process.env, RESTAURANT_BROWSER_COORDINATOR: '1', NODE_OPTIONS: `${process.env.NODE_OPTIONS ?? ''} --require ${JSON.stringify(preloadPath)}` },
        stdio: ['ignore', 'pipe', 'pipe'],
    });
    let browser;
    const exited = once(child, 'exit');
    t.after(async () => {
        try {
            await browser?.close();
        } finally {
            child.kill('SIGTERM');
            const timer = setTimeout(() => child.kill('SIGKILL'), 2000);
            await exited;
            clearTimeout(timer);
        }
    });
    let output = '';
    const endpoint = await new Promise((resolve, reject) => {
        child.once('error', reject);
        child.once('exit', () => reject(new Error(`Playwright server exited before listening: ${output}`)));
        const receive = chunk => {
            output += chunk.toString();
            const match = output.match(/Listening on (ws:\/\/[^\s]+)/);
            if (match) resolve(match[1]);
        };
        child.stdout.on('data', receive);
        child.stderr.on('data', receive);
    });
    const url = new URL(endpoint);
    url.searchParams.set('browser', 'chromium');
    url.searchParams.set('launch-options', JSON.stringify({ headless: true, channel: 'chrome', chromiumSandbox: false, bypassCSP: true }));
    browser = await chromium.connect(url.toString());
    const session = await browser.newBrowserCDPSession();
    const { processInfo } = await session.send('SystemInfo.getProcessInfo');
    const browserPid = processInfo.find(process => process.type === 'browser')?.id;
    assert.ok(Number.isSafeInteger(browserPid));
    const command = execFileSync('ps', ['-ww', '-p', String(browserPid), '-o', 'command='], { encoding: 'utf8' }).trim();
    assert.equal(/(?:^|\s)--no-sandbox(?:\s|$)/.test(command), false);
    assert.equal(/(?:^|\s)--disable-setuid-sandbox(?:\s|$)/.test(command), false);
    const profile = command.match(/--user-data-dir=(.+?)(?= --|$)/)?.[1];
    assert.ok(profile && /^playwright_.*_profile-/.test(basename(profile)), 'the browser must use an owned disposable profile');
    assert.ok(realpathSync(profile).startsWith(realpathSync(tmpdir()) + sep));

    const context = await browser.newContext({ bypassCSP: true });
    const page = await context.newPage();
    const cspErrors = [];
    page.on('console', message => { if (message.type() === 'error') cspErrors.push(message.text()); });
    await page.route('http://127.0.0.1/security-fixture/**', route => route.fulfill(route.request().url().endsWith('/allowed.js')
        ? { contentType: 'application/javascript', body: 'window.allowedScriptRan = true;' }
        : {
            contentType: 'text/html',
            headers: { 'Content-Security-Policy': "default-src 'none'; script-src 'self'" },
            body: '<!doctype html><title>Isolated browser security fixture</title><h1>Local CSP probe</h1><script>window.blockedInlineRan = true;</script><script src="/security-fixture/allowed.js"></script>',
        }));
    await page.goto('http://127.0.0.1/security-fixture/');
    assert.equal(await page.title(), 'Isolated browser security fixture');
    assert.equal(await page.evaluate(() => window.allowedScriptRan), true);
    assert.equal(await page.evaluate(() => window.blockedInlineRan), undefined);
    assert.ok(cspErrors.some(message => /Content Security Policy/i.test(message)), 'the browser must actually report the blocked inline script');
    console.log(JSON.stringify({ browserSecurity: { sandbox: true, csp: true, disposableProfile: true, browserVersion: await browser.version() } }));
    await context.close();
});
