import assert from 'node:assert/strict';
import { cpSync, existsSync, mkdirSync, mkdtempSync, readFileSync, writeFileSync, rmSync, statSync, utimesSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import { generateStyles, applicationStyles } from '../resources/build/styles.js';
import { compile as compileTailwind } from 'tailwindcss';

function isolatedStyles(t) {
    const root = mkdtempSync(join(tmpdir(), 'restaurant-style-test-'));
    t.after(() => rmSync(root, { recursive: true, force: true }));
    cpSync('resources/scss', join(root, 'resources/scss'), { recursive: true });
    return root;
}

const bridgePath = (root) => join(root, 'resources/css/generated-theme.css');
const partialPath = (root, entry) => join(root, `resources/views/generated/styles/${entry}.blade.php`);

test('Tailwind retains composition aliases even when no utility references their palette', async (t) => {
    const root = isolatedStyles(t);
    generateStyles(root);
    const compiler = await compileTailwind(readFileSync(bridgePath(root), 'utf8'));
    const css = compiler.build([]);
    for (const name of ['color-red-50', 'color-zinc-950', 'color-sky-800', 'radius-md', 'radius-lg']) {
        const variable = name.startsWith('color-') ? name.slice(6) : name;
        assert.ok(css.includes(`--${name}: var(--rm-${variable})`), name);
    }
});

test('generation is deterministic, checks staleness and exports canonical thresholds', (t) => {
    const root = isolatedStyles(t);
    const dependencies = generateStyles(root);
    assert.ok(dependencies.includes(join(root, 'resources/scss/settings/_tokens.scss')));
    assert.equal(new Set(dependencies).size, dependencies.length);
    assert.ok(dependencies.every(path => existsSync(path)));
    const bridge = bridgePath(root);
    const initial = readFileSync(bridge, 'utf8');
    assert.match(initial, /--color-canvas: var\(--rm-canvas\)/);
    assert.match(initial, /--breakpoint-xs: 30rem/);
    generateStyles(root, { check: true });
    writeFileSync(bridge, 'stale');
    assert.throws(() => generateStyles(root, { check: true }), /Generated styles are stale:/);
    assert.equal(readFileSync(bridge, 'utf8'), 'stale');
    generateStyles(root);
    assert.equal(readFileSync(bridge, 'utf8'), initial);
    for (const entry of ['emergency', 'pdf-qr', 'pdf-report']) {
        const css = readFileSync(partialPath(root, entry), 'utf8');
        assert.doesNotMatch(css, /var\(|@(?:theme|apply|source)|@font-face|url\(https?:/);
    }
});

test('check mode reports missing artifacts without creating output directories', (t) => {
    const root = isolatedStyles(t);
    assert.throws(() => generateStyles(root, { check: true }), /Generated styles are stale:/);
    assert.equal(existsSync(join(root, 'resources/css')), false);
    assert.equal(existsSync(join(root, 'resources/views')), false);

    generateStyles(root, {});
    const partial = partialPath(root, 'pdf-report');
    rmSync(partial);
    assert.throws(() => generateStyles(root, { check: true }), (error) => {
        assert.equal(error.message, `Generated styles are stale: ${partial}`);
        return true;
    });
    assert.equal(existsSync(partial), false);
});

test('unchanged generation and checks preserve artifact modification times', (t) => {
    const root = isolatedStyles(t);
    generateStyles(root);
    const files = [bridgePath(root), ...['emergency', 'pdf-qr', 'pdf-report'].map(entry => partialPath(root, entry))];
    const timestamp = new Date('2000-01-01T00:00:00Z');
    for (const path of files) utimesSync(path, timestamp, timestamp);
    const before = files.map(path => ({ content: readFileSync(path, 'utf8'), modified: statSync(path).mtimeMs }));
    generateStyles(root);
    generateStyles(root, { check: true });
    for (const [index, path] of files.entries()) {
        assert.equal(readFileSync(path, 'utf8'), before[index].content);
        assert.equal(statSync(path).mtimeMs, before[index].modified);
    }
});

test('unreadable artifact errors propagate without overwriting the obstructing path', (t) => {
    const root = isolatedStyles(t);
    const bridge = bridgePath(root);
    mkdirSync(bridge, { recursive: true });
    assert.throws(() => generateStyles(root), (error) => {
        assert.equal(error.code, 'EISDIR');
        return true;
    });
    assert.equal(statSync(bridge).isDirectory(), true);
    assert.equal(existsSync(partialPath(root, 'emergency')), false);
});

for (const fragment of ['</style', '<?', '{{']) {
    test(`inline CSS generation rejects the unsafe ${fragment} boundary`, (t) => {
        const root = isolatedStyles(t);
        writeFileSync(join(root, 'resources/scss/emergency.scss'), `.unsafe { content: '${fragment}'; }\n`);
        assert.throws(() => generateStyles(root), /Unsafe generated stylesheet: emergency/);
        assert.equal(existsSync(partialPath(root, 'emergency')), false);
    });
}

test('Vite watches only generated Sass dependencies and refreshes its graph during HMR', (t) => {
    const root = isolatedStyles(t);
    const plugin = applicationStyles();
    const watched = [];
    const buildContext = { addWatchFile: path => watched.push(path) };
    assert.equal(plugin.name, 'restaurant-application-styles');
    assert.equal(plugin.enforce, 'pre');
    plugin.buildStart.call(buildContext);
    assert.deepEqual(watched, []);

    plugin.configResolved({ root });
    plugin.buildStart.call(buildContext);
    assert.ok(watched.includes(join(root, 'resources/scss/settings/_breakpoints.scss')));
    assert.ok(watched.includes(join(root, 'resources/scss/base/_emergency.scss')));
    assert.equal(watched.includes(join(root, 'resources/scss/app.scss')), false);
    assert.equal(new Set(watched).size, watched.length);
    assert.ok(watched.every(path => path.startsWith(join(root, 'resources/scss/')) && existsSync(path)));

    writeFileSync(bridgePath(root), 'stale');
    for (const file of ['resources/js/app.js', 'resources/scss-other/theme.scss']) {
        plugin.handleHotUpdate({ file: join(root, file) });
        assert.equal(readFileSync(bridgePath(root), 'utf8'), 'stale');
    }

    const addedPartial = join(root, 'resources/scss/base/_hmr-test.scss');
    writeFileSync(addedPartial, '.hmr-test { color: rebeccapurple; }\n');
    const entry = join(root, 'resources/scss/emergency.scss');
    writeFileSync(entry, `${readFileSync(entry, 'utf8')}\n@use 'base/hmr-test';\n`);
    plugin.handleHotUpdate({ file: entry });
    assert.match(readFileSync(partialPath(root, 'emergency'), 'utf8'), /\.hmr-test\s*\{/);
    assert.match(readFileSync(bridgePath(root), 'utf8'), /--color-canvas: var\(--rm-canvas\)/);

    watched.length = 0;
    plugin.buildStart.call(buildContext);
    assert.ok(watched.includes(addedPartial));
    assert.equal(new Set(watched).size, watched.length);
    generateStyles(root, { check: true });
});
