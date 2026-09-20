const secured = Symbol.for('restaurant.browser-security');

// Pest has no launch-options hook, and run-server filters chromiumSandbox unless unsafe mode is enabled.
// This version-pinned, process-local adapter tightens the server boundary without enabling unsafe mode.
function installBrowserSecurity(server, version) {
    if (version !== '1.63.0') throw new Error('Unsupported Playwright version for the browser security adapter.');
    const runtime = server.createPlaywright({ sdkLanguage: 'javascript', isServer: true });
    const chromium = Object.getPrototypeOf(runtime.chromium);
    const browser = server.Browser?.prototype;
    if (typeof chromium?.launch !== 'function' || chromium.launch.length !== 3
        || typeof browser?.newContext !== 'function' || browser.newContext.length !== 2) {
        throw new Error('Unsupported Playwright launch boundary for the browser security adapter.');
    }
    if (!chromium[secured]) {
        const launch = chromium.launch;
        chromium.launch = function (progress, options, logger) {
            return launch.call(this, progress, { ...options, chromiumSandbox: true }, logger);
        };
        Object.defineProperty(chromium, secured, { value: true });
    }
    if (!browser[secured]) {
        const newContext = browser.newContext;
        browser.newContext = function (progress, options) {
            return newContext.call(this, progress, { ...options, bypassCSP: false });
        };
        Object.defineProperty(browser, secured, { value: true });
    }
}

module.exports = { installBrowserSecurity };

// Pest sends SIGTERM twice before proc_close; a surviving Playwright listener can keep it waiting.
if (process.env.RESTAURANT_BROWSER_COORDINATOR === '1' && process.argv.slice(1).includes('run-server')) {
    const { existsSync, realpathSync } = require('node:fs');
    const argument = process.argv[1] ?? '';
    const entry = existsSync(argument) ? realpathSync(argument) : '';
    if (/[\\/]playwright(?:-core)?[\\/]cli\.js$/.test(entry)) {
        const { createRequire } = require('node:module');
        const playwrightRequire = createRequire(entry);
        installBrowserSecurity(
            playwrightRequire('playwright-core/lib/coreBundle').server,
            playwrightRequire('playwright-core/package.json').version,
        );
    }
    process.once('SIGTERM', () => {
        setTimeout(() => process.exit(143), 1000).unref();
    });
}
