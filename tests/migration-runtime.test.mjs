import assert from 'node:assert/strict';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import { runVerificationProcess } from './Support/verification-process.mjs';
import * as platform from './Support/platform-verification.mjs';

test('platform rejection retains native and installed diagnostics before blocking application gates', async () => {
    assert.equal(typeof platform.runPlatformPreflight, 'function');
    const recorded = [];
    const rejected = new Set(['platform-lock', 'platform-installed']);
    await assert.rejects(async () => {
        await platform.runPlatformPreflight({
            php: args => ['php', ...args], composer: args => ['composer', ...args],
            run: async (name, command) => {
                const result = await runVerificationProcess([process.execPath, '-e', `process.exit(${rejected.has(name) ? 1 : 0})`], { env: {}, timeout: 3000 });
                recorded.push({ name, command, exitCode: result.code });
                if (result.code !== 0) throw new Error(`${name} failed with exit ${result.code}`);
            },
        });
        recorded.push({ name: 'application-gates' });
    }, error => error instanceof AggregateError && error.errors.length === 2
        && /platform-lock failed/.test(error.message) && /platform-installed failed/.test(error.message));
    assert.deepEqual(recorded.map(step => step.name), ['runtime-capabilities', 'composer-manifest', 'platform-lock', 'platform-installed']);
    assert.deepEqual(recorded.map(step => step.exitCode), [0, 0, 1, 1]);
    assert.ok(recorded[2].command.includes('--lock'));
    assert.ok(!recorded[3].command.includes('--lock'));
});

test('native failure or timeout cannot become a successful platform preflight', async () => {
    assert.equal(typeof platform.runPlatformPreflight, 'function');
    const checks = [];
    await assert.rejects(platform.runPlatformPreflight({
        php: args => args, composer: args => args,
        run: async name => {
            checks.push(name);
            if (name === 'runtime-capabilities') throw new Error('runtime-capabilities timed out');
        },
    }), /runtime-capabilities timed out/);
    assert.equal(checks.length, 4);
    await platform.runPlatformPreflight({ php: args => args, composer: args => args, run: async () => {} });
});

test('interrupted preflight stops without starting more diagnostic children', async () => {
    for (const exitCode of [130, 143]) {
        const interruption = Object.assign(new Error('Interrupted'), { exitCode });
        const started = [];
        await assert.rejects(platform.runPlatformPreflight({
            php: args => args, composer: args => args,
            run: async name => { started.push(name); throw interruption; },
        }), error => error === interruption);
        assert.deepEqual(started, ['runtime-capabilities']);
    }
});

test('verification children run in the selected disposable workspace', async t => {
    const cwd = mkdtempSync(join(tmpdir(), 'restaurant-verification-cwd-'));
    t.after(() => rmSync(cwd, { recursive: true, force: true }));
    const result = await runVerificationProcess([process.execPath, '-e', 'console.log(require("node:fs").realpathSync(process.cwd()))'], { cwd, env: process.env, timeout: 5000 });
    const { realpathSync } = await import('node:fs');
    assert.equal(result.code, 0);
    assert.equal(result.output.trim(), realpathSync(cwd));
});

test('migration entrypoint requires explicit runtimes and rejects unknown options before gates', async () => {
    const missing = await runVerificationProcess([process.execPath, 'tests/verify-migration.mjs'], { env: {}, timeout: 3000 });
    assert.notEqual(missing.code, 0);
    assert.match(missing.output, /--php.*--composer/);
    const unknown = await runVerificationProcess([process.execPath, 'tests/verify-migration.mjs', '--ignore-platform-reqs'], { env: {}, timeout: 3000 });
    assert.notEqual(unknown.code, 0);
    assert.match(unknown.output, /Unknown option/);
});

test('coverage extension configuration is scoped to its own child environment', async t => {
    const helpers = await import('./Support/platform-verification.mjs');
    assert.equal(typeof helpers.coverageEnvironment, 'function');
    const { writeFileSync, readFileSync, existsSync } = await import('node:fs');
    const root = mkdtempSync(join(tmpdir(), 'restaurant-coverage-config-'));
    t.after(() => rmSync(root, { recursive: true, force: true }));
    const extension = join(root, 'xdebug.so');
    writeFileSync(extension, 'fixture');
    const environment = { APP_ENV: 'testing', PATH: '/usr/bin' };
    const child = helpers.coverageEnvironment(environment, root, extension);
    assert.equal(environment.PHP_INI_SCAN_DIR, undefined);
    assert.equal(child.XDEBUG_MODE, 'coverage');
    assert.match(child.PHP_INI_SCAN_DIR, /^:/);
    assert.match(readFileSync(join(root, 'coverage-ini/xdebug.ini'), 'utf8'), /zend_extension=".*xdebug\.so"/);
    assert.ok(existsSync(extension));
    assert.throws(() => helpers.coverageEnvironment(environment, root, '/missing/xdebug.so'), /ENOENT/);
});
