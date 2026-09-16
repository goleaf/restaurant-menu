import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, readFileSync, rmSync, symlinkSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { brotliCompressSync, constants, gzipSync } from 'node:zlib';
import test from 'node:test';

const cli = fileURLToPath(new URL('./frontend-assets.mjs', import.meta.url));

function fixture(t, mutate = () => {}) {
    const root = mkdtempSync(join(tmpdir(), 'restaurant-assets-test-'));
    t.after(() => rmSync(root, { recursive: true, force: true }));
    const build = join(root, 'build');
    const manifest = {
        'app.js': { file: 'assets/app.js', isEntry: true, imports: ['shared.js'], dynamicImports: ['editor.js'], css: ['assets/app.css'] },
        'guest.js': { file: 'assets/guest.js', isEntry: true, imports: ['shared.js'], css: ['assets/app.css'] },
        'shared.js': { file: 'assets/shared.js', css: ['assets/fonts.css'], assets: ['assets/local.woff2'] },
        'editor.js': { file: 'assets/editor.js', isDynamicEntry: true },
        'font.woff2': { file: 'assets/local.woff2' },
    };
    const budget = {
        version: 1,
        entries: {},
        scenarios: { workspace: { entries: ['app.js', 'guest.js'], limits: {} } },
        totals: {},
    };
    const files = {
        'assets/app.js': 'import "./shared.js"; console.log("application");',
        'assets/guest.js': 'import "./shared.js"; console.log("guest");',
        'assets/shared.js': 'export const shared = true;',
        'assets/editor.js': 'export const editor = "loaded on demand";',
        'assets/app.css': 'body { color: #123456; }',
        'assets/fonts.css': '@font-face { font-family: Local; src: url("./local.woff2"); }',
        'assets/local.woff2': Buffer.from([0, 1, 2, 3, 255]),
    };
    mutate({ root, build, manifest, budget, files });
    for (const [name, contents] of Object.entries(files)) {
        mkdirSync(dirname(join(build, name)), { recursive: true });
        writeFileSync(join(build, name), contents);
    }
    const manifestPath = join(build, 'manifest.json');
    const budgetPath = join(root, 'budget.json');
    writeFileSync(manifestPath, JSON.stringify(manifest));
    writeFileSync(budgetPath, JSON.stringify(budget));
    return {
        root, build, manifestPath, budgetPath,
        run: (...args) => spawnSync(process.execPath, [cli, '--manifest', manifestPath, '--budget', budgetPath, ...args], { encoding: 'utf8' }),
    };
}

test('reports deterministic compression and deduplicated static scenario dependencies', (t) => {
    const current = fixture(t);
    const first = current.run('--json');
    assert.equal(first.status, 0, first.stderr);
    assert.equal(current.run('--json').stdout, first.stdout);
    const report = JSON.parse(first.stdout);
    assert.equal(report.passed, true);
    assert.deepEqual(report.compression, { gzipLevel: 9, brotliQuality: 11 });
    assert.equal(report.totals.css.count, 2);
    assert.equal(report.totals.js.count, 4);
    assert.equal(report.totals.fonts.count, 1);
    assert.equal(report.totals.all.count, 7);
    assert.deepEqual(report.scenarios.workspace.files, [
        'assets/app.css', 'assets/app.js', 'assets/fonts.css', 'assets/guest.js', 'assets/local.woff2', 'assets/shared.js',
    ]);
    const bytes = readFileSync(join(current.build, 'assets/app.js'));
    assert.equal(report.entries['app.js'].raw, bytes.length);
    assert.equal(report.entries['app.js'].gzip, gzipSync(bytes, { level: 9 }).length);
    assert.equal(report.entries['app.js'].brotli, brotliCompressSync(bytes, { params: { [constants.BROTLI_PARAM_QUALITY]: 11 } }).length);
    assert.equal(report.scenarios.workspace.raw, report.totals.all.raw - report.assets['assets/editor.js'].raw);
    assert.match(current.run().stdout, /CSS.*raw.*gzip.*brotli/i);
});

test('enforces entry, scenario and total limits with JSON failure details', (t) => {
    const current = fixture(t, ({ budget }) => {
        budget.entries['app.js'] = { raw: 0 };
        budget.scenarios.workspace.limits = { gzip: 0 };
        budget.totals.fonts = { raw: 0 };
    });
    const result = current.run('--json');
    assert.equal(result.status, 1);
    const report = JSON.parse(result.stdout);
    assert.equal(report.passed, false);
    assert.equal(report.violations.length, 3);
    assert.match(result.stderr, /entry app\.js raw/);
    assert.match(result.stderr, /scenario workspace gzip/);
    assert.match(result.stderr, /total fonts raw/);
});

for (const [name, mutate, message] of [
    ['missing file', ({ files }) => delete files['assets/shared.js'], /Missing asset.*shared\.js/],
    ['missing CSS file', ({ files }) => delete files['assets/app.css'], /Missing asset.*app\.css/],
    ['missing font file', ({ files }) => delete files['assets/local.woff2'], /Missing asset.*local\.woff2/],
    ['missing import', ({ manifest }) => manifest['app.js'].imports.push('missing.js'), /Missing manifest import.*missing\.js/],
    ['missing dynamic import', ({ manifest }) => manifest['app.js'].dynamicImports.push('missing.js'), /Missing manifest import.*missing\.js/],
    ['dependency cycle', ({ manifest }) => manifest['shared.js'].imports = ['app.js'], /Import cycle.*app\.js.*shared\.js/],
    ['path escape', ({ manifest }) => manifest['app.js'].file = '../outside.js', /Unsafe asset path/],
    ['absolute path', ({ manifest }) => manifest['app.js'].file = '/tmp/outside.js', /Unsafe asset path/],
    ['encoded escape', ({ manifest }) => manifest['app.js'].file = 'assets/%2e%2e/%2e%2e/outside.js', /Unsafe asset path/],
    ['CSS font escape', ({ files }) => files['assets/fonts.css'] = '@font-face { src: url("../../outside.woff2"); }', /Unsafe font URL/],
    ['external font URL', ({ files }) => files['assets/fonts.css'] = '@font-face { src: url("https://example.test/font.woff2"); }', /Unsafe font URL/],
    ['duplicate font URL', ({ files }) => files['assets/app.css'] += '@font-face { src: url("./local.woff2?v=1"); }', /Duplicate font URL.*local\.woff2/],
    ['duplicate font in one stylesheet', ({ files }) => files['assets/fonts.css'] += '@font-face { src: url("./local.woff2"); }', /Duplicate font URL/],
    ['missing scenario entry', ({ budget }) => budget.scenarios.workspace.entries = ['missing.js'], /Unknown scenario entry.*missing\.js/],
    ['missing budget entry', ({ budget }) => budget.entries['missing.js'] = { raw: 1 }, /Unknown budget entry.*missing\.js/],
    ['invalid budget', ({ budget }) => budget.totals.css = { gzip: -1 }, /Invalid byte ceiling/],
    ['misspelled budget metric', ({ budget }) => budget.totals.css = { gziip: 100 }, /Unknown.*gziip/],
    ['unsafe asset base', ({ budget }) => budget.assetBase = 'https://example.test/', /Invalid assetBase/],
]) {
    test(`rejects ${name}`, (t) => {
        const result = fixture(t, mutate).run('--json');
        assert.equal(result.status, 1);
        assert.match(result.stderr, message);
    });
}

test('rejects symlinks escaping the manifest asset directory', (t) => {
    const current = fixture(t);
    const outside = join(current.root, 'outside.js');
    writeFileSync(outside, 'outside');
    rmSync(join(current.build, 'assets/app.js'));
    symlinkSync(outside, join(current.build, 'assets/app.js'));
    const result = current.run();
    assert.equal(result.status, 1);
    assert.match(result.stderr, /Unsafe asset path/);
});

test('includes a font discovered only through CSS and measures aliased shared files once', (t) => {
    const current = fixture(t, ({ manifest }) => {
        delete manifest['font.woff2'];
        delete manifest['shared.js'].assets;
        manifest['font-styles.css'] = { file: 'assets/fonts.css', isEntry: true };
    });
    const result = current.run('--json');
    assert.equal(result.status, 0, result.stderr);
    const report = JSON.parse(result.stdout);
    assert.equal(report.totals.fonts.count, 1);
    assert.equal(report.totals.all.count, 7);
    assert.ok(report.scenarios.workspace.files.includes('assets/local.woff2'));
});

test('accepts exact budget boundaries', (t) => {
    const current = fixture(t);
    const baseline = JSON.parse(current.run('--json').stdout);
    const budget = JSON.parse(readFileSync(current.budgetPath, 'utf8'));
    budget.entries['app.js'] = { raw: baseline.entries['app.js'].raw, gzip: baseline.entries['app.js'].gzip };
    budget.scenarios.workspace.limits = { raw: baseline.scenarios.workspace.raw, gzip: baseline.scenarios.workspace.gzip };
    budget.totals.all = { raw: baseline.totals.all.raw, gzip: baseline.totals.all.gzip };
    writeFileSync(current.budgetPath, JSON.stringify(budget));
    const result = current.run('--json');
    assert.equal(result.status, 0, result.stderr);
    assert.equal(JSON.parse(result.stdout).passed, true);
});

test('supports Vite default .vite manifest location', (t) => {
    const current = fixture(t);
    const nestedManifest = join(current.build, '.vite/manifest.json');
    mkdirSync(dirname(nestedManifest));
    writeFileSync(nestedManifest, readFileSync(current.manifestPath));
    const result = spawnSync(process.execPath, [cli, '--manifest', nestedManifest, '--budget', current.budgetPath, '--json'], { encoding: 'utf8' });
    assert.equal(result.status, 0, result.stderr);
    assert.equal(JSON.parse(result.stdout).totals.fonts.count, 1);
});

for (const assetBase of ['/build/', '/restaurant/build/']) {
    test(`resolves fonts under the declared public asset base ${assetBase}`, (t) => {
        const current = fixture(t, ({ budget, files }) => {
            budget.assetBase = assetBase;
            files['assets/fonts.css'] = `@font-face { src: url("${assetBase}assets/local.woff2"); }`;
        });
        const result = current.run('--json');
        assert.equal(result.status, 0, result.stderr);
        assert.equal(JSON.parse(result.stdout).totals.fonts.count, 1);
    });
}

test('rejects font paths outside the public asset base', (t) => {
    const current = fixture(t, ({ files }) => {
        files['assets/fonts.css'] = '@font-face { src: url("/private/local.woff2"); }';
    });
    const result = current.run();
    assert.equal(result.status, 1);
    assert.match(result.stderr, /Unsafe font URL/);
});
