import { runVerificationProcess } from './Support/verification-process.mjs';
import { copyFileSync, mkdirSync, mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const artifacts = mkdtempSync(join(tmpdir(), 'restaurant-migration-verification-'));
const storage = join(artifacts, 'storage');
for (const directory of ['app/public', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs', 'cache']) {
    mkdirSync(join(storage, directory), { recursive: true });
}
copyFileSync('storage/app/public/.htaccess', join(storage, 'app/public/.htaccess'));
const environment = {
    ...process.env, APP_ENV: 'testing', APP_DEBUG: 'false', DB_CONNECTION: 'sqlite', DB_DATABASE: ':memory:', DB_URL: '',
    CACHE_STORE: 'array', SESSION_DRIVER: 'array', QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'array',
    LARAVEL_STORAGE_PATH: storage, VIEW_COMPILED_PATH: join(storage, 'framework/views'),
    APP_CONFIG_CACHE: join(storage, 'cache/config.php'), APP_ROUTES_CACHE: join(storage, 'cache/routes.php'),
    APP_EVENTS_CACHE: join(storage, 'cache/events.php'), PAO_DISABLE: '1', COMPOSER_PROCESS_TIMEOUT: '0',
};
const results = [];
async function run(name, command, { timeout = 600_000, env = {} } = {}) {
    console.log(`\n[${name}] ${command.join(' ')}`);
    const started = Date.now();
    const result = await runVerificationProcess(command, {
        env: { ...environment, ...env }, timeout, onOutput: chunk => process.stdout.write(chunk),
    });
    writeFileSync(join(artifacts, `${name}.log`), result.output);
    results.push({ name, exitCode: result.code, timeout: result.timedOut, milliseconds: Date.now() - started });
    writeFileSync(join(artifacts, 'summary.json'), JSON.stringify(results, null, 2) + '\n');
    if (result.code !== 0) throw new Error(`${name} failed with exit ${result.code}`);
}

console.log(`Migration artifacts: ${artifacts}`);
try {
    await run('composer-manifest', ['composer', 'validate', '--strict', '--no-check-publish']);
    await run('architecture', ['npm', 'run', 'test:architecture']);
    await run('scss-lint', ['npm', 'run', 'lint:styles']);
    await run('js-lint', ['npm', 'run', 'lint:js']);
    await run('php-format', ['vendor/bin/pint', '--dirty', '--format', 'agent']);
    await run('static-analysis', ['composer', 'analyse']);
    await run('production-build', ['npm', 'run', 'build']);
    await run('generated-styles', ['npm', 'run', 'styles:check']);
    const discovery = join(artifacts, 'backend-discovery.xml');
    const junit = join(artifacts, 'backend.xml');
    await run('backend-discovery', ['php', 'vendor/bin/pest', '--testsuite=Unit,Feature', '--list-tests-xml', discovery]);
    await run('backend', ['php', 'vendor/bin/pest', '--testsuite=Unit,Feature', '--compact', '--fail-on-skipped', '--fail-on-incomplete', '--fail-on-risky', '--fail-on-warning', '--log-junit', junit]);
    const expected = (readFileSync(discovery, 'utf8').match(/<testMethod\s/g) ?? []).length;
    const actual = (readFileSync(junit, 'utf8').match(/<testcase\s/g) ?? []).length;
    if (expected === 0 || expected !== actual) throw new Error(`Backend discovery mismatch: ${expected} discovered, ${actual} executed`);
    await run('js-tests-coverage', ['npm', 'run', 'test:js:coverage']);
    await run('php-coverage', ['composer', 'test:coverage', '--', '-d', 'memory_limit=4G', '--coverage-clover', join(artifacts, 'php-coverage.xml'), '--fail-on-skipped', '--fail-on-incomplete', '--fail-on-risky', '--fail-on-warning'], { timeout: 1_800_000 });
    await run('browser', ['composer', 'test:browser'], { timeout: 3_600_000 });
    await run('translations-audit', ['php', 'artisan', 'translations:audit', '--no-interaction']);
    await run('translations-scan', ['php', 'artisan', 'translations:scan', '--no-interaction']);
    await run('asset-budgets', ['npm', 'run', 'build:check']);
    console.log(`Migration verification passed: ${expected} backend tests and all recorded stages. ${artifacts}`);
} catch (error) {
    console.error(`${error.message}\nMigration verification incomplete. Artifacts: ${artifacts}`);
    process.exitCode = 1;
}
