import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { compile, compileString, sassNull } from 'sass-embedded';

function writeChanged(path, content, check) {
    let previous;
    try { previous = readFileSync(path, 'utf8'); } catch (error) {
        if (error.code !== 'ENOENT') throw error;
    }
    if (previous === content) return;
    if (check) throw new Error(`Generated styles are stale: ${path}`);
    mkdirSync(dirname(path), { recursive: true });
    writeFileSync(path, content);
}

export function generateStyles(root, { check = false } = {}) {
    const aliases = [];
    const breakpoints = [];
    const result = compileString(`
        @use 'settings/tokens';
        @use 'settings/breakpoints';
        @each $name, $value in tokens.$light {
            $_: collect-token($name, tokens.variable($name));
        }
        @each $name, $value in breakpoints.$values {
            $_: collect-breakpoint($name, $value);
        }
    `, {
        loadPaths: [resolve(root, 'resources/scss')],
        functions: {
            'collect-token($name, $variable)': ([name, variable]) => {
                aliases.push(`    --${name.assertString().text}: var(${variable.assertString().text});`);
                return sassNull;
            },
            'collect-breakpoint($name, $value)': ([name, value]) => {
                breakpoints.push(`    --breakpoint-${name.assertString().text}: ${value};`);
                return sassNull;
            },
        },
    });
    const heading = '/* Generated from resources/scss/settings. Do not edit; Vite rebuilds this file. */\n';
    writeChanged(resolve(root, 'resources/css/generated-theme.css'), `${heading}@theme inline static {\n${aliases.join('\n')}\n}\n\n@theme {\n${breakpoints.join('\n')}\n}\n`, check);
    const dependencies = [...result.loadedUrls];
    for (const entry of ['emergency', 'pdf-qr', 'pdf-report']) {
        const compiled = compile(resolve(root, `resources/scss/${entry}.scss`), { style: 'expanded', sourceMap: false });
        if (compiled.css.includes('</style') || compiled.css.includes('<?') || compiled.css.includes('{{')) {
            throw new Error(`Unsafe generated stylesheet: ${entry}`);
        }
        const content = `/* Generated from resources/scss/${entry}.scss. Do not edit. */\n${compiled.css}\n`;
        writeChanged(resolve(root, `resources/views/generated/styles/${entry}.blade.php`), content, check);
        dependencies.push(...compiled.loadedUrls);
    }
    return [...new Set(dependencies.map(url => url.pathname))];
}

export function applicationStyles() {
    let root;
    let dependencies = [];
    return {
        name: 'restaurant-application-styles',
        enforce: 'pre',
        configResolved(config) {
            root = config.root;
            dependencies = generateStyles(root);
        },
        buildStart() {
            dependencies.forEach(path => this.addWatchFile(path));
        },
        handleHotUpdate(context) {
            if (context.file.startsWith(resolve(root, 'resources/scss') + '/')) {
                dependencies = generateStyles(root);
            }
        },
    };
}
