import assert from 'node:assert/strict';
import { mkdirSync, mkdtempSync, readFileSync, rmSync, symlinkSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import test from 'node:test';

function put(root, path, data) {
    const target = join(root, path);
    mkdirSync(dirname(target), { recursive: true });
    writeFileSync(target, JSON.stringify(data));
}

function change(root, path, mutate) {
    const data = JSON.parse(readFileSync(join(root, path), 'utf8'));
    mutate(data);
    put(root, path, data);
}

function fixture(t) {
    const root = mkdtempSync(join(tmpdir(), 'restaurant-dependency-integrity-'));
    t.after(() => rmSync(root, { recursive: true, force: true }));
    const composer = { name: 'vendor/library', version: 'v1.2.3', source: { reference: 'source-reference' }, dist: { reference: 'dist-reference' } };
    put(root, 'composer.lock', { packages: [composer], 'packages-dev': [] });
    put(root, 'vendor/composer/installed.json', { packages: [{ ...composer, version: '1.2.3', 'install-path': '../vendor/library' }] });
    put(root, 'vendor/vendor/library/composer.json', { name: composer.name });
    put(root, 'package.json', { dependencies: { library: '^1.0.0' }, engines: { node: '>=24' } });
    put(root, 'package-lock.json', { lockfileVersion: 3, packages: {
        '': { dependencies: { library: '^1.0.0' }, engines: { node: '>=24' } },
        'node_modules/library': { version: '1.0.0', resolved: 'https://registry.npmjs.org/library/-/library-1.0.0.tgz', integrity: 'sha512-fixture' },
    } });
    put(root, 'node_modules/library/package.json', { name: 'library', version: '1.0.0' });
    return root;
}

const check = async root => (await import('./Support/dependency-integrity.mjs')).assertDependencyIntegrity(root);

test('dependency identity records the exact installed graph and stable metadata digest', async t => {
    const root = fixture(t);
    const result = await check(root);
    assert.equal(result.composer.count, 1);
    assert.equal(result.npm.count, 1);
    assert.equal(result.npm.lockedCount, 1);
    assert.equal(result.digest.length, 64);
    assert.equal((await check(root)).digest, result.digest);
});

test('a range-compatible npm package differing from the lock is rejected', async t => {
    const root = fixture(t);
    change(root, 'node_modules/library/package.json', data => { data.version = '1.1.0'; });
    await assert.rejects(() => check(root), /npm.*version.*library/i);
});

test('nested transitive package versions are checked against their exact locked locations', async t => {
    const root = fixture(t);
    change(root, 'package-lock.json', data => {
        data.packages['node_modules/library'].dependencies = { child: '^1.0.0' };
        data.packages['node_modules/library/node_modules/child'] = { version: '1.0.0' };
    });
    put(root, 'node_modules/library/node_modules/child/package.json', { name: 'child', version: '1.1.0' });
    await assert.rejects(() => check(root), /npm.*version.*library\/node_modules\/child/i);
    change(root, 'node_modules/library/node_modules/child/package.json', data => { data.version = '1.0.0'; });
    assert.equal((await check(root)).npm.count, 2);
});

test('Composer rejects missing packages, unexpected packages and changed references', async t => {
    const root = fixture(t);
    const initial = readFileSync(join(root, 'vendor/composer/installed.json'), 'utf8');
    change(root, 'vendor/composer/installed.json', data => { data.packages = []; });
    await assert.rejects(() => check(root), /Composer.*missing/i);
    writeFileSync(join(root, 'vendor/composer/installed.json'), initial);
    change(root, 'vendor/composer/installed.json', data => { data.packages[0].source.reference = 'another-reference'; });
    await assert.rejects(() => check(root), /Composer.*reference/i);
    writeFileSync(join(root, 'vendor/composer/installed.json'), initial);
    change(root, 'vendor/composer/installed.json', data => { data.packages.push({ ...data.packages[0], name: 'other/library' }); });
    await assert.rejects(() => check(root), /Composer.*unexpected/i);
});

test('Composer rejects an installed package location escaping vendor', async t => {
    const root = fixture(t);
    put(root, 'outside/composer.json', { name: 'vendor/library' });
    change(root, 'vendor/composer/installed.json', data => { data.packages[0]['install-path'] = '../../outside'; });
    await assert.rejects(() => check(root), /Composer.*outside.*vendor/i);
});

test('npm manifest declarations and lockfile format must agree', async t => {
    const root = fixture(t);
    change(root, 'package.json', data => { data.dependencies.library = '^2.0.0'; });
    await assert.rejects(() => check(root), /npm.*manifest.*dependencies/i);
    change(root, 'package.json', data => { data.dependencies.library = '^1.0.0'; });
    change(root, 'package-lock.json', data => { data.lockfileVersion = 2; });
    await assert.rejects(() => check(root), /lockfile version 3/i);
});

test('missing required npm packages and extraneous installed packages fail', async t => {
    const root = fixture(t);
    rmSync(join(root, 'node_modules/library'), { recursive: true });
    await assert.rejects(() => check(root), /npm.*missing.*library/i);
    put(root, 'node_modules/library/package.json', { name: 'library', version: '1.0.0' });
    put(root, 'node_modules/extra/package.json', { name: 'extra', version: '1.0.0' });
    await assert.rejects(() => check(root), /npm.*unexpected.*extra/i);
});

test('unavailable optional platform packages and their private children may be absent', async t => {
    const root = fixture(t);
    const differentOs = process.platform === 'linux' ? 'darwin' : 'linux';
    change(root, 'package-lock.json', data => {
        data.packages['node_modules/library'].optionalDependencies = { native: '1.0.0' };
        data.packages['node_modules/native'] = { version: '1.0.0', optional: true, os: [differentOs], dependencies: { child: '1.0.0' } };
        data.packages['node_modules/native/node_modules/child'] = { version: '1.0.0', optional: true };
    });
    const result = await check(root);
    assert.deepEqual(result.npm.excludedOptionalPaths, ['node_modules/native', 'node_modules/native/node_modules/child']);
    assert.equal(result.npm.count, 1);
});

test('applicable optional native packages must actually be installed', async t => {
    const root = fixture(t);
    change(root, 'package-lock.json', data => {
        data.packages['node_modules/library'].optionalDependencies = { native: '1.0.0' };
        data.packages['node_modules/native'] = { version: '1.0.0', optional: true, os: [process.platform], cpu: [process.arch] };
    });
    await assert.rejects(() => check(root), /npm.*missing.*native/i);
});

test('optional binaries for another CPU and explicitly excluded operating systems may be absent', async t => {
    const root = fixture(t);
    const otherCpu = process.arch === 'arm64' ? 'x64' : 'arm64';
    change(root, 'package-lock.json', data => {
        data.packages['node_modules/library'].optionalDependencies = { cpu: '1.0.0', os: '1.0.0' };
        data.packages['node_modules/cpu'] = { version: '1.0.0', optional: true, cpu: [otherCpu] };
        data.packages['node_modules/os'] = { version: '1.0.0', optional: true, os: ['!'+process.platform] };
    });
    assert.deepEqual((await check(root)).npm.excludedOptionalPaths, ['node_modules/cpu', 'node_modules/os']);
});

test('a required transitive dependency cannot hide behind an unrelated optional platform package', async t => {
    const root = fixture(t);
    change(root, 'package-lock.json', data => {
        data.packages['node_modules/library'].dependencies = { required: '1.0.0' };
        data.packages['node_modules/required'] = { version: '1.0.0', optional: true };
    });
    await assert.rejects(() => check(root), /npm.*missing.*required/i);
});

test('optional peers may be absent while required peers and hoisted dependencies remain mandatory', async t => {
    const root = fixture(t);
    change(root, 'package-lock.json', data => {
        data.packages['node_modules/library'].peerDependencies = { optional: '^1.0.0' };
        data.packages['node_modules/library'].peerDependenciesMeta = { optional: { optional: true } };
    });
    assert.equal((await check(root)).npm.count, 1);
    change(root, 'package-lock.json', data => { data.packages['node_modules/library'].peerDependencies.required = '^1.0.0'; });
    await assert.rejects(() => check(root), /npm.*missing.*required/i);
});

test('dependency paths cannot traverse outside node_modules or follow linked package roots', async t => {
    const root = fixture(t);
    change(root, 'package-lock.json', data => { data.packages['node_modules/../outside'] = { version: '1.0.0' }; });
    await assert.rejects(() => check(root), /npm.*path/i);
    change(root, 'package-lock.json', data => { delete data.packages['node_modules/../outside']; });
    rmSync(join(root, 'node_modules/library'), { recursive: true });
    put(root, 'outside/package.json', { name: 'library', version: '1.0.0' });
    symlinkSync(join(root, 'outside'), join(root, 'node_modules/library'));
    await assert.rejects(() => check(root), /npm.*symbolic link/i);
});
