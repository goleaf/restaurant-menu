import assert from 'node:assert/strict';
import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import test from 'node:test';
import { Linter } from 'eslint';

function files(directory) {
    return readdirSync(directory, { withFileTypes: true }).flatMap(entry => {
        const path = join(directory, entry.name);
        return entry.isDirectory() ? files(path) : [path];
    });
}

test('browser modules cannot introduce an application AJAX transport or a second framework', () => {
    const linter = new Linter();
    const rule = {
        meta: { schema: [] },
        create(context) {
            return {
                CallExpression(node) {
                    const callee = node.callee;
                    const passkeyProtocol = /(^|\/)resources\/js\/integrations\/passkeys\.js$/.test(context.filename)
                        && callee.type === 'Identifier' && callee.name === 'fetch';
                    if ((callee.type === 'Identifier' && ['fetch', 'axios'].includes(callee.name))
                        || (callee.type === 'MemberExpression' && ['fetch', 'ajax', 'sendBeacon'].includes(callee.property.name))) {
                        if (!passkeyProtocol) context.report({ node, message: 'Use Livewire for application requests; SDK protocols stay in the tested passkey adapter.' });
                    }
                },
                NewExpression(node) {
                    if (node.callee.name === 'XMLHttpRequest') context.report({ node, message: 'Independent AJAX transport is not allowed.' });
                },
                ImportDeclaration(node) {
                    if (/^(alpinejs|react|vue|svelte|angular|@inertiajs)(\/|$)/.test(node.source.value)) {
                        context.report({ node, message: 'Livewire owns the browser framework runtime.' });
                    }
                },
            };
        },
    };
    for (const path of files('resources/js')) {
        const errors = linter.verify(readFileSync(path, 'utf8'), {
            languageOptions: { ecmaVersion: 'latest', sourceType: 'module' },
            plugins: { migration: { rules: { transport: rule } } },
            rules: { 'migration/transport': 'error' },
        }, { filename: path });
        assert.deepEqual(errors, [], path);
        assert.ok(path === 'resources/js/app.js' || /^resources\/js\/(alpine|integrations|support|services)\//.test(path), path);
    }
});

test('styles have canonical Sass sources and only fixed generated HTML inclusions', () => {
    assert.deepEqual(files('resources/css').sort(), ['resources/css/app.css', 'resources/css/generated-theme.css']);
    const allowed = new Set(['resources/views/errors/shell.blade.php', 'resources/views/pdf/qr-labels.blade.php', 'resources/views/pdf/reports/branch-report.blade.php']);
    for (const path of files('resources/views')) {
        const source = readFileSync(path, 'utf8');
        assert.doesNotMatch(source, /<script\b/i, path);
        for (const match of source.matchAll(/<style\b[^>]*>([\s\S]*?)<\/style>/gi)) {
            assert.ok(allowed.has(path), path);
            assert.match(match[1].trim(), /^@include\('generated\.styles\.(emergency|pdf-qr|pdf-report)'\)$/);
        }
        assert.doesNotMatch(source, /x-data="\{/, path);
        for (const match of source.matchAll(/\s(?::|x-bind:)?style="([^"]*)"/g)) {
            assert.ok(['resources/views/components/menu/item-images.blade.php', 'resources/views/components/menu/dish-preview.blade.php', 'resources/views/livewire/public-qr/guest-menu.blade.php'].includes(path), path);
            assert.match(match[1], /^(?:object-position:\s*\{\{[^}]+\}\}|\{ objectPosition: position \})$/, path);
        }
    }
    assert.doesNotMatch(readFileSync('vite.config.js', 'utf8'), /additionalData/);
});

test('JS coverage inventory names every production browser source and keeps one runtime', () => {
    const packageJson = JSON.parse(readFileSync('package.json', 'utf8'));
    assert.match(packageJson.scripts['test:js:coverage'], /--test-coverage-include='resources\/js\/\*\*\/\*\.js'/);
    assert.match(packageJson.scripts['test:js:coverage'], /--test-coverage-lines=100/);
    const dependencies = { ...packageJson.dependencies, ...packageJson.devDependencies };
    assert.equal(dependencies.alpinejs, undefined);
    assert.equal(dependencies.sass, undefined);
    assert.ok(dependencies['sass-embedded']);
    const bootstrap = readFileSync('resources/js/app.js', 'utf8');
    assert.equal(bootstrap.match(/Livewire\.start\(\)/g)?.length, 1);
    assert.doesNotMatch(bootstrap, /Alpine\.start|window\.Alpine\s*=/);
});
