import assert from 'node:assert/strict';
import { readFile, readdir } from 'node:fs/promises';
import { join } from 'node:path';
import test from 'node:test';
import { compile } from 'sass-embedded';
import postcss from 'postcss';

const compileStyles = (entry) => compile(`resources/scss/${entry}.scss`, {
    style: 'expanded',
    fatalDeprecations: ['import', 'global-builtin', 'color-functions'],
}).css;

function declarations(css, selector) {
    const values = {};
    const normalize = (value) => value.replace(/(['"])([\w-]+)\1/g, '$2');
    postcss.parse(css).walkRules((rule) => {
        if (normalize(rule.selector) !== normalize(selector) && !rule.selectors.some(value => normalize(value) === normalize(selector))) {
            return;
        }
        for (const declaration of rule.nodes.filter((node) => node.type === 'decl')) {
            values[declaration.prop] = declaration.value;
        }
    });
    return values;
}

test('all five delivery entries compile with Sass alone', () => {
    for (const entry of ['app', 'qr-print', 'pdf-qr', 'pdf-report', 'emergency']) {
        const css = compileStyles(entry);
        assert.ok(css.length > 100, entry);
        assert.doesNotMatch(css, /@(?:theme|source|utility|apply|reference|use|forward)\b/, entry);
    }
});

test('local font subsets remain available relative to their Sass module', async () => {
    const source = await readFile('resources/scss/base/_fonts.scss', 'utf8');
    const urls = [...source.matchAll(/url\(([^)]+)\)/g)];
    assert.equal(urls.length, 3);
    for (const [, url] of urls) {
        const font = await readFile(join('resources/scss/base', url));
        assert.equal(font.subarray(0, 4).toString(), 'wOF2');
    }
    assert.equal((compileStyles('app').match(/@font-face/g) ?? []).length, 3);
});

test('application themes resolve every product custom property without framework styles', () => {
    const css = compileStyles('app');
    const light = declarations(css, ':root, :host');
    const dark = declarations(css, '.dark');
    assert.equal(light['--rm-canvas'], 'oklch(0.985 0.002 85)');
    assert.equal(dark['--rm-canvas'], 'oklch(0.15 0.008 45)');
    assert.equal(light['--rm-spacing-touch'], '2.75rem');
    assert.equal(light['--rm-spacing-operational-touch'], '3.5rem');
    for (const [, variable] of css.matchAll(/var\((--[\w-]+)/g)) {
        if (variable.startsWith('--rm-')) {
            assert.ok(Object.hasOwn(light, variable), `Unresolved application variable: ${variable}`);
        }
    }
    for (const name of Object.keys(dark).filter(name => name.startsWith('--rm-'))) {
        assert.ok(Object.hasOwn(light, name), `Dark token has no light value: ${name}`);
    }
});

test('repeated product compositions preserve controls, palette roles and responsive print geometry', async () => {
    const css = compileStyles('app');
    assert.equal(declarations(css, '.rm-pagination__control')['min-height'], 'var(--rm-spacing-touch)');
    assert.equal(declarations(css, '.rm-pagination__control:focus-visible')['box-shadow'], '0 0 0 2px var(--rm-focus)');
    assert.equal(declarations(css, '.rm-pagination__disabled').opacity, '60%');
    assert.equal(declarations(css, '.rm-pagination__current')['background-color'], 'var(--rm-brand-700)');
    assert.equal(declarations(css, '.rm-structure-avatar').width, '3rem');
    assert.equal(declarations(css, '.rm-structure-avatar:where(.dark, .dark *)')['background-color'], 'var(--color-zinc-950)');
    assert.equal(declarations(css, '.rm-draft-notice--error').color, 'var(--color-red-700)');
    assert.equal(declarations(css, '.rm-draft-notice--rejected').color, 'var(--color-red-800)');
    assert.equal(declarations(css, '.rm-menu-empty').border, '1px dashed var(--color-zinc-300)');
    assert.equal(declarations(css, '.rm-onboarding-disclosure')['min-height'], 'var(--rm-spacing-touch)');
    assert.equal(declarations(css, '.rm-print-toolbar')['max-width'], '56rem');
    assert.equal(declarations(css, '.rm-print-toolbar')['flex-direction'], 'row');
    assert.match(css, /@media \(width >= 48rem\)/);
    const expectedClasses = new Map([
        ['vendor/livewire/simple-tailwind', ['rm-pagination__control', 'rm-pagination__disabled']],
        ['vendor/livewire/tailwind', ['rm-pagination__current', 'rm-pagination__page']],
        ['components/structure/list-toolbar', ['rm-structure-toolbar']],
        ['livewire/organizations/index', ['<x-structure.list-toolbar', 'rm-structure-avatar', 'rm-structure-error']],
        ['livewire/organizations/brands/index', ['<x-structure.list-toolbar', 'rm-structure-avatar']],
        ['livewire/organizations/brands/branches/index', ['<x-structure.list-toolbar', 'rm-structure-avatar', 'rm-branch-shortcut']],
        ['livewire/public-qr/draft-order', ['rm-draft-notice', 'rm-draft-notice--error']],
        ['livewire/public-qr/guest-entry', ['rm-guest-contact-link']],
        ['livewire/organizations/brands/branches/menu/catalog', ['rm-menu-empty']],
        ['livewire/organizations/brands/branches/menu/availability', ['rm-menu-empty']],
        ['livewire/onboarding/restaurant-setup', ['rm-onboarding-disclosure', 'rm-onboarding-next-link']],
        ['livewire/departments/ticket-print', ['qr-print-toolbar rm-print-toolbar']],
        ['livewire/organizations/brands/branches/qr/bulk-print', ['qr-print-toolbar rm-print-toolbar']],
        ['livewire/organizations/brands/branches/service-points/qr/print-template', ['qr-print-toolbar rm-print-toolbar']],
    ]);
    for (const [view, classes] of expectedClasses) {
        const source = await readFile(`resources/views/${view}.blade.php`, 'utf8');
        for (const name of classes) assert.ok(source.includes(name), `${view}: ${name}`);
    }
});

test('framework palette references in compositions remain declared by the installed Tailwind theme', async () => {
    const css = compileStyles('app');
    const framework = await readFile('node_modules/tailwindcss/theme.css', 'utf8');
    const declared = new Set([...framework.matchAll(/(--[\w-]+):/g)].map(([, name]) => name));
    for (const [, name] of css.matchAll(/var\((--[\w-]+)/g)) {
        if (!name.startsWith('--rm-')) assert.ok(declared.has(name), `Unknown framework variable: ${name}`);
    }
});

test('shared compositions retain responsive geometry and actionable keyboard states', () => {
    const css = compileStyles('app');
    assert.equal(declarations(css, '.rm-workspace')['min-width'], '0');
    assert.equal(declarations(css, '.rm-workspace')['grid-template-columns'], 'minmax(17rem, 0.72fr) minmax(25rem, 1.28fr)');
    assert.equal(declarations(css, '.rm-page-header__actions')['grid-template-columns'], 'repeat(2, minmax(0, 1fr))');
    assert.equal(declarations(css, '.rm-media-image')['aspect-ratio'], '4/3');
    assert.equal(declarations(css, '.rm-auth-main')['min-height'], '100svh');
    assert.match(css, /@media \(width >= 30rem\)/);
    assert.match(css, /@media \(width >= 64rem\)/);
    assert.equal(declarations(css, '.skip-link:focus-visible').translate, '0 0');
    assert.match(css, /prefers-reduced-motion: reduce/);
    assert.match(css, /forced-colors: active/);
});

test('two-factor QR contrast follows the existing dark class without inline styling', async () => {
    const css = compileStyles('app');
    assert.equal(declarations(css, '.dark .rm-security-qr').filter, 'invert(1) brightness(1.5)');
    assert.equal(declarations(css, '.rm-security-qr').filter, undefined);
    const view = await readFile('resources/views/livewire/settings/security.blade.php', 'utf8');
    assert.match(view, /class="rm-security-qr bg-white p-3 rounded"/);
    assert.doesNotMatch(view, /:style=|\$flux\.(?:appearance|dark)/);
});

test('area tree indentation uses nine bounded depths and preserves physical left spacing', async () => {
    const css = compileStyles('app');
    const selectors = [];
    postcss.parse(css).walkRules((rule) => {
        if (rule.selector.startsWith('.rm-area-node-label[')) selectors.push(rule.selector);
    });
    assert.equal(selectors.length, 9);
    for (let depth = 0; depth <= 8; depth++) {
        const styles = declarations(css, `.rm-area-node-label[data-depth="${depth}"]`);
        assert.equal(styles['padding-left'], `${depth * 1.25}rem`);
        assert.equal(styles['padding-inline-start'], undefined);
    }
    const view = await readFile('resources/views/livewire/organizations/brands/branches/area-node-row.blade.php', 'utf8');
    assert.match(view, /class="rm-area-node-label min-w-0"/);
    assert.ok(view.includes('data-depth="{{ max(0, min($node[\'depth\'], 8)) }}"'));
    assert.doesNotMatch(view, /style="padding-left:/);
});

test('print styles preserve physical QR geometry and standalone PDF palettes', () => {
    const qr = compileStyles('qr-print');
    assert.equal(declarations(qr, ':root')['--qr-sticker-width'], '76mm');
    assert.equal(declarations(qr, ':root')['--qr-sticker-height'], '104mm');
    assert.equal(declarations(qr, '.qr-sticker')['break-inside'], 'avoid');
    assert.equal(declarations(qr, '.qr-sticker-image').width, '48mm');
    assert.match(qr, /size: A4/);
    assert.match(qr, /margin: 8mm/);
    for (const entry of ['pdf-qr', 'pdf-report', 'emergency']) {
        assert.doesNotMatch(compileStyles(entry), /var\(|oklch\(|@layer|@font-face/, entry);
    }
    const pdf = compileStyles('pdf-qr');
    for (const preset of ['minimal', 'classic', 'restaurant', 'bar', 'hotel', 'premium']) {
        assert.ok(declarations(pdf, `[data-qr-preset="${preset}"] .label`)['border-color'], preset);
    }
    assert.equal(declarations(pdf, '.qr').width, '55mm');
    assert.equal(declarations(pdf, '.qr').height, '55mm');
});

test('the application owns SCSS sources independently of the framework bridge', async () => {
    const source = await readFile('resources/scss/app.scss', 'utf8');
    assert.match(source, /@use ['"]themes\/runtime['"]/);
    assert.doesNotMatch(source, /@(?:theme|source|utility|apply|reference)\b|tailwindcss|vendor\/livewire/);
    const bridge = await readFile('resources/css/app.css', 'utf8');
    assert.match(bridge, /@import 'tailwindcss' source\(none\)/);
    assert.doesNotMatch(bridge, /@layer|@utility|@apply|\[data-flux-|\[x-cloak\]/);
});

test('all production Sass modules use the module system without a framework compiler', async () => {
    async function inspect(directory) {
        for (const entry of await readdir(directory, { withFileTypes: true })) {
            const path = join(directory, entry.name);
            if (entry.isDirectory()) {
                await inspect(path);
            } else if (entry.name.endsWith('.scss')) {
                const source = await readFile(path, 'utf8');
                assert.doesNotMatch(source, /@(?:import|theme|source|utility|apply|reference|extend)\b/, path);
            }
        }
    }
    await inspect('resources/scss');
});

test('main head declares framework before application styles exactly once', async () => {
    const source = await readFile('resources/views/partials/head.blade.php', 'utf8');
    assert.match(source, /'resources\/css\/app\.css', 'resources\/scss\/app\.scss', 'resources\/js\/app\.js'/);
    for (const entry of ['resources/css/app.css', 'resources/scss/app.scss', 'resources/js/app.js']) {
        assert.equal(source.split(entry).length - 1, 1, entry);
    }
    assert.doesNotMatch(await readFile('resources/js/app.js', 'utf8'), /\.s?css['"]/);
});

test('Flux dark accent aliases resolve the same product palette as Tailwind utilities', () => {
    const css = compile('resources/scss/app.scss').css;
    for (const name of ['accent', 'accent-content', 'accent-foreground']) {
        assert.match(css, new RegExp(`\\.dark\\s*\\{[^}]*--color-${name}: var\\(--rm-${name}\\)`, 's'));
    }
});
