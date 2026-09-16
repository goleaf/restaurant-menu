import assert from 'node:assert/strict';
import { accessSync, chmodSync, constants, existsSync, mkdirSync, mkdtempSync, readFileSync, realpathSync, rmSync, symlinkSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import test from 'node:test';
import { composerCommand, createSourceSnapshot, createVerificationEnvironment, phpCommand, resolveJavaScriptRuntime, resolvePhpRuntime, sourceInventory } from './Support/platform-verification.mjs';

test('JavaScript preflight verifies the selected npm CLI against manifest engines', async t => {
    const root = fixture(t);
    const npmBinary = put(root, 'npm-cli.js', 'console.log("12.0.2");');
    const engines = { node: `>=${process.versions.node} <${Number(process.versions.node.split('.')[0])+1}`, npm: '>=12.0.2 <13' };
    const runtime = await resolveJavaScriptRuntime({ npmBinary, engines });
    assert.equal(runtime.nodeVersion, process.versions.node);
    assert.equal(runtime.nodeBinary, realpathSync(process.execPath));
    assert.equal(runtime.npmVersion, '12.0.2');
    assert.equal(runtime.npmBinary, realpathSync(npmBinary));
    put(root, 'npm-cli.js', 'console.log("11.19.0");');
    await assert.rejects(resolveJavaScriptRuntime({ npmBinary, engines }), /npm 11\.19\.0.*>=12\.0\.2 <13/);
    put(root, 'npm-cli.js', 'console.log("13.0.0");');
    await assert.rejects(resolveJavaScriptRuntime({ npmBinary, engines }), /npm 13\.0\.0/);
    put(root, 'npm-cli.js', 'console.log("12.0.2-beta.1");');
    await assert.rejects(resolveJavaScriptRuntime({ npmBinary, engines }), /invalid npm version/i);
    put(root, 'npm-cli.js', 'process.exit(2);');
    await assert.rejects(resolveJavaScriptRuntime({ npmBinary, engines }), /npm.*failed/i);
    await assert.rejects(resolveJavaScriptRuntime({ npmBinary, engines: { ...engines, node: '>=99.0.0 <100' } }), /Node.*99/);
    await assert.rejects(resolveJavaScriptRuntime({ npmBinary, engines: { ...engines, npm: '*' } }), /engine range/i);
});

function fixture(t) {
    const root = mkdtempSync(join(tmpdir(), 'restaurant-platform-test-'));
    t.after(() => rmSync(root, { recursive: true, force: true }));
    return root;
}

function put(root, path, content = path) {
    const target = join(root, path);
    mkdirSync(dirname(target), { recursive: true });
    writeFileSync(target, content);
    return target;
}

function executable(root, version = '8.6.0-dev', versionId = 80600) {
    const binary = put(root, 'PHP with spaces', '#!/bin/sh\nif [ "$1" = "-r" ]; then\n  printf \'%s\\n\' '+JSON.stringify(JSON.stringify({ version, versionId, sapi: 'cli', binary: join(root, 'PHP with spaces'), extensions: ['pdo_sqlite', 'sqlite3'], iniFiles: [] }))+'\nelse\n  printf \'Composer version 2.9.0 2026-01-01\\n\'\nfi\n');
    chmodSync(binary, 0o700);
    return { binary, composerBinary: put(root, 'composer.phar', '<?php') };
}

test('explicit PHP runtime selection verifies identity and keeps argv paths intact', async t => {
    const root = fixture(t);
    const paths = executable(root);
    const runtime = await resolvePhpRuntime({ label: 'experimental', ...paths, expectedVersion: '8.6', allowPrerelease: true });
    assert.equal(runtime.version, '8.6.0-dev');
    assert.equal(runtime.phpBinary, realpathSync(paths.binary));
    assert.equal(runtime.composerVersion, '2.9.0');
    assert.deepEqual(phpCommand(runtime, ['vendor/bin/pest', '--compact']), [realpathSync(paths.binary), 'vendor/bin/pest', '--compact']);
    assert.deepEqual(composerCommand(runtime, ['check-platform-reqs']), [realpathSync(paths.binary), realpathSync(paths.composerBinary), 'check-platform-reqs']);
});

test('runtime selection rejects missing, wrong, nonstable and misleading PHP executables', async t => {
    const root = fixture(t);
    const paths = executable(root);
    await assert.rejects(async () => resolvePhpRuntime({ label: 'stable', ...paths, expectedVersion: '8.6' }), /prerelease/i);
    await assert.rejects(async () => resolvePhpRuntime({ label: 'experimental', ...paths, expectedVersion: '8.5', allowPrerelease: true }), /expected PHP 8\.5/i);
    await assert.rejects(async () => resolvePhpRuntime({ label: 'stable', ...paths, binary: 'php', expectedVersion: '8.5' }), /absolute/i);
    await assert.rejects(async () => resolvePhpRuntime({ label: 'stable', ...paths, binary: join(root, 'missing'), expectedVersion: '8.5' }), /ENOENT/);
    await assert.rejects(async () => resolvePhpRuntime({ label: 'stable', ...paths, expectedVersion: 'latest' }), /major\.minor/i);
    chmodSync(paths.binary, 0o600);
    await assert.rejects(async () => resolvePhpRuntime({ label: 'experimental', ...paths, expectedVersion: '8.6', allowPrerelease: true }), /EACCES/);
});

test('platform checks cannot be bypassed through Composer arguments or inherited environment', async t => {
    const root = fixture(t);
    const paths = executable(root, '8.5.10', 80510);
    const runtime = await resolvePhpRuntime({ label: 'stable', ...paths, expectedVersion: '8.5' });
    for (const flag of ['--ignore-platform-reqs', '--ignore-platform-req=php', '--ignore-platform-req']) {
        assert.throws(() => composerCommand(runtime, ['install', flag]), /platform/i);
    }
    await assert.rejects(async () => resolvePhpRuntime({ label: 'stable', ...paths, expectedVersion: '8.5', env: { COMPOSER_IGNORE_PLATFORM_REQS: '1' } }), /platform/i);
    assert.throws(() => phpCommand(runtime, ['artisan\0bad']), /argument/i);
});

test('source inventory includes untracked source and lock bytes while excluding runtime data and credentials', t => {
    const root = fixture(t);
    const source = join(root, 'source');
    for (const path of ['composer.json', 'composer.lock', 'package-lock.json', '.env.example', 'app/New.php', 'storage/app/public/.htaccess', 'packages/local/source.php']) put(source, path);
    for (const path of ['.env', '.env.testing', 'auth.json', '.npmrc', 'secret.pem', 'database/database.sqlite', 'database/database.sqlite-wal', 'storage/app/private/receipt.txt', 'vendor/autoload.php', 'node_modules/package/index.js', '.git/config', 'bootstrap/cache/config.php', 'public/build/manifest.json', 'tests/Browser/Screenshots/test.png', 'owned-artifacts/log.txt']) put(source, path, 'private');
    symlinkSync(join(source, 'storage/app'), join(source, 'public/storage'));
    const inventory = sourceInventory(source, { exclude: ['owned-artifacts'] });
    assert.deepEqual(inventory.files.map(file => file.path), ['.env.example', 'app/New.php', 'composer.json', 'composer.lock', 'package-lock.json', 'packages/local/source.php', 'storage/app/public/.htaccess']);
    assert.equal(inventory.digest.length, 64);
    assert.equal(inventory.locks['composer.lock'], inventory.files.find(file => file.path === 'composer.lock').sha256);
    put(source, '.env', 'changed-secret');
    assert.equal(sourceInventory(source, { exclude: ['owned-artifacts'] }).digest, inventory.digest);
    put(source, 'app/New.php', 'changed source');
    assert.notEqual(sourceInventory(source, { exclude: ['owned-artifacts'] }).digest, inventory.digest);
    assert.notEqual(sourceInventory(source, { exclude: ['owned-artifacts'] }).digest, sourceInventory(source).digest);
});

test('source snapshots copy only audited regular files without following links or overwriting a destination', t => {
    const root = fixture(t);
    const source = join(root, 'source');
    put(source, 'app/Action.php', 'source');
    put(source, '.env', 'private');
    const destination = join(root, 'snapshot');
    const inventory = createSourceSnapshot({ sourceRoot: source, destination });
    assert.equal(readFileSync(join(destination, 'app/Action.php'), 'utf8'), 'source');
    assert.equal(readFileSync(join(destination, '.env'), 'utf8'), '');
    assert.equal(readFileSync(join(source, '.env'), 'utf8'), 'private');
    assert.equal(sourceInventory(destination).digest, inventory.digest);
    assert.throws(() => createSourceSnapshot({ sourceRoot: source, destination }), /exists/i);
    assert.throws(() => createSourceSnapshot({ sourceRoot: source, destination: join(source, 'nested') }), /outside/i);
    symlinkSync(join(source, '.env'), join(source, 'app/linked.php'));
    assert.throws(() => sourceInventory(source), /symbolic link/i);
    assert.throws(() => createSourceSnapshot({ sourceRoot: source, destination: join(root, 'unsafe') }), /symbolic link/i);
    assert.equal(existsSync(join(root, 'unsafe')), false);
});

test('source inventories reject traversal exclusions and unsafe path names', t => {
    const root = fixture(t);
    put(root, 'app/Action.php');
    assert.throws(() => sourceInventory(root, { exclude: ['../outside'] }), /exclusion/i);
    put(root, 'app/bad\nname.php');
    assert.throws(() => sourceInventory(root), /path/i);
});

test('each runtime receives owned storage caches credentials and a PHP PATH shim', async t => {
    const root = fixture(t);
    const paths = executable(root, '8.5.10', 80510);
    const runtime = await resolvePhpRuntime({ label: 'stable', ...paths, expectedVersion: '8.5' });
    const baseEnv = { PATH: '/usr/bin:/bin', HOME: '/user/home', APP_KEY: 'private-key', DB_DATABASE: '/working/database.sqlite', DB_URL: 'private-url', MAIL_MAILER: 'smtp', MAIL_PASSWORD: 'private-password', AWS_SECRET_ACCESS_KEY: 'secret', PHP_BINARY: '/wrong/php', NODE_OPTIONS: '--require /unsafe/preload', COMPOSER_AUTH: 'secret', APP_CONFIG_CACHE: '/working/config.php' };
    const one = createVerificationEnvironment({ runtime, artifacts: join(root, 'lane-one'), baseEnv });
    const two = createVerificationEnvironment({ runtime, artifacts: join(root, 'lane-two'), baseEnv });
    assert.equal(one.PHP_BINARY, runtime.phpBinary);
    assert.equal(one.RESTAURANT_EXPECTED_PHP_VERSION, runtime.version);
    assert.equal(one.RESTAURANT_EXPECTED_PHP_BINARY, runtime.phpBinary);
    assert.equal(one.DB_DATABASE, ':memory:');
    assert.equal(one.APP_URL, 'http://localhost');
    assert.equal(one.DB_URL, '');
    assert.equal(one.MAIL_MAILER, 'array');
    assert.equal(one.SESSION_DRIVER, 'array');
    assert.equal(one.QUEUE_CONNECTION, 'sync');
    assert.equal(one.APP_DEBUG, 'false');
    assert.equal(one.MAIL_PASSWORD, undefined);
    assert.equal(one.AWS_SECRET_ACCESS_KEY, undefined);
    assert.equal(one.COMPOSER_AUTH, undefined);
    assert.equal(one.NODE_OPTIONS, undefined);
    assert.match(one.APP_KEY, /^base64:[A-Za-z0-9+/]{43}=$/);
    assert.notEqual(one.APP_KEY, two.APP_KEY);
    assert.notEqual(one.LARAVEL_STORAGE_PATH, two.LARAVEL_STORAGE_PATH);
    for (const key of ['APP_CONFIG_CACHE', 'APP_ROUTES_CACHE', 'APP_EVENTS_CACHE', 'APP_PACKAGES_CACHE', 'APP_SERVICES_CACHE', 'VIEW_COMPILED_PATH']) assert.ok(one[key].startsWith(join(root, 'lane-one')+'/'));
    assert.equal(realpathSync(join(one.PATH.split(':')[0], 'php')), runtime.phpBinary);
    accessSync(join(one.PATH.split(':')[0], 'php'), constants.X_OK);
    assert.equal(baseEnv.PHP_BINARY, '/wrong/php');
    assert.throws(() => createVerificationEnvironment({ runtime, artifacts: join(root, 'lane-one'), baseEnv }), /exists/i);
});
