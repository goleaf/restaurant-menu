// Pest sends SIGTERM twice before proc_close; a surviving Playwright listener can keep it waiting.
if (process.env.RESTAURANT_BROWSER_COORDINATOR === '1' && process.argv.slice(1).includes('run-server')) {
    process.once('SIGTERM', () => {
        setTimeout(() => process.exit(143), 1000).unref();
    });
}
