import assert from 'node:assert/strict';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import { runVerificationProcess } from './Support/verification-process.mjs';

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
