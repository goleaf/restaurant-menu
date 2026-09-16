import { readFileSync, realpathSync, statSync } from 'node:fs';
import { basename, dirname, isAbsolute, relative, resolve, sep, posix } from 'node:path';
import { parseArgs } from 'node:util';
import { brotliCompressSync, constants, gzipSync } from 'node:zlib';

// Schema v1: entries[manifestKey] and totals[css|js|fonts|other|all] accept
// optional raw/gzip byte ceilings. scenarios[name] contains entries[] and limits.
// Entry limits cover its own emitted file; scenarios cover static imports, CSS,
// assets and CSS font URLs. Totals include dynamic chunks, scenarios do not.
// Compressed totals sum individually compressed files, matching separate responses.
// assetBase defaults to /build/ for Laravel's absolute local CSS font URLs.
const fontExtension = /\.(?:woff2?|ttf|otf|eot)$/i;
const categories = ['css', 'js', 'fonts', 'other', 'all'];

function record(value, label) {
    if (value === null || typeof value !== 'object' || Array.isArray(value)) {
        throw new Error(`${label} must be an object`);
    }
    return value;
}

function knownKeys(value, allowed, label) {
    for (const key of Object.keys(value)) {
        if (!allowed.includes(key)) throw new Error(`Unknown ${label}: ${key}`);
    }
}

function strings(value, label) {
    if (!Array.isArray(value) || value.some(item => typeof item !== 'string' || item.length === 0)) {
        throw new Error(`${label} must be an array of nonempty strings`);
    }
    return value;
}

function limits(value, label) {
    record(value, label);
    knownKeys(value, ['raw', 'gzip'], `${label} metric`);
    for (const [metric, ceiling] of Object.entries(value)) {
        if (!Number.isSafeInteger(ceiling) || ceiling < 0) {
            throw new Error(`Invalid byte ceiling: ${label}.${metric}`);
        }
    }
}

function readJson(path) {
    try {
        return JSON.parse(readFileSync(path, 'utf8'));
    } catch (error) {
        throw new Error(`Cannot read JSON ${path}: ${error.message}`);
    }
}

function measure(manifestPath, budgetPath) {
    const manifest = record(readJson(manifestPath), 'Manifest');
    const budget = record(readJson(budgetPath), 'Budget');
    knownKeys(budget, ['version', 'assetBase', 'entries', 'scenarios', 'totals'], 'budget field');
    if (budget.version !== 1) throw new Error('Budget version must be 1');
    const assetBase = budget.assetBase ?? '/build/';
    if (typeof assetBase !== 'string' || !assetBase.startsWith('/') || !assetBase.endsWith('/')
        || /[\\\x00-\x1f?#:%]/.test(assetBase) || assetBase !== posix.normalize(assetBase)
        || assetBase.startsWith('//') || assetBase.split('/').includes('..')) {
        throw new Error('Invalid assetBase: expected a local URL path with leading and trailing slash');
    }
    const entryLimits = record(budget.entries ?? {}, 'Budget entries');
    const scenarios = record(budget.scenarios ?? {}, 'Budget scenarios');
    const totalLimits = record(budget.totals ?? {}, 'Budget totals');
    knownKeys(totalLimits, categories, 'total category');
    for (const [name, ceiling] of Object.entries(entryLimits)) {
        if (!Object.hasOwn(manifest, name)) throw new Error(`Unknown budget entry: ${name}`);
        limits(ceiling, `entry ${name}`);
    }
    for (const [name, scenario] of Object.entries(scenarios)) {
        record(scenario, `Scenario ${name}`);
        knownKeys(scenario, ['entries', 'limits'], `scenario ${name} field`);
        strings(scenario.entries, `Scenario ${name} entries`);
        if (scenario.entries.length === 0) throw new Error(`Scenario ${name} needs at least one entry`);
        for (const entry of scenario.entries) {
            if (!Object.hasOwn(manifest, entry)) throw new Error(`Unknown scenario entry: ${entry}`);
        }
        limits(scenario.limits ?? {}, `scenario ${name}`);
    }
    for (const [name, ceiling] of Object.entries(totalLimits)) limits(ceiling, `total ${name}`);

    const manifestDirectory = dirname(resolve(manifestPath));
    const root = realpathSync(basename(manifestDirectory) === '.vite' ? dirname(manifestDirectory) : manifestDirectory);
    const chunks = Object.keys(manifest).sort();
    if (chunks.length === 0) throw new Error('Manifest must contain assets');
    for (const key of chunks) {
        const chunk = record(manifest[key], `Manifest entry ${key}`);
        if (typeof chunk.file !== 'string' || chunk.file.length === 0) throw new Error(`Missing file for manifest entry: ${key}`);
        for (const property of ['imports', 'dynamicImports', 'css', 'assets']) {
            if (chunk[property] !== undefined) strings(chunk[property], `${key}.${property}`);
        }
        for (const dependency of [...(chunk.imports ?? []), ...(chunk.dynamicImports ?? [])]) {
            if (!Object.hasOwn(manifest, dependency)) throw new Error(`Missing manifest import: ${key} -> ${dependency}`);
        }
    }

    const visited = new Set();
    const visiting = [];
    function validateGraph(key) {
        if (visiting.includes(key)) throw new Error(`Import cycle: ${[...visiting, key].join(' -> ')}`);
        if (visited.has(key)) return;
        visiting.push(key);
        for (const dependency of [...(manifest[key].imports ?? []), ...(manifest[key].dynamicImports ?? [])]) validateGraph(dependency);
        visiting.pop();
        visited.add(key);
    }
    for (const key of chunks) validateGraph(key);

    const assets = new Map();
    const fontReferences = new Map();
    const cssFonts = new Map();

    function assetPath(file) {
        let decoded;
        try {
            decoded = decodeURIComponent(file);
        } catch {
            throw new Error(`Unsafe asset path: ${file}`);
        }
        for (const path of [file, decoded]) {
            if (!path || /[\\\x00-\x1f?#:]/.test(path) || posix.isAbsolute(path) || path !== posix.normalize(path)
                || path.split('/').includes('..')) {
                throw new Error(`Unsafe asset path: ${file}`);
            }
        }
        const absolute = resolve(root, file);
        let real;
        try {
            real = realpathSync(absolute);
            if (!statSync(real).isFile()) throw new Error('not a regular file');
        } catch (error) {
            throw new Error(`Missing asset ${file}: ${error.message}`);
        }
        const fromRoot = relative(root, real);
        if (isAbsolute(fromRoot) || fromRoot === '..' || fromRoot.startsWith(`..${sep}`)) {
            throw new Error(`Unsafe asset path: ${file}`);
        }
        return real;
    }

    function loadAsset(file) {
        if (assets.has(file)) return;
        const bytes = readFileSync(assetPath(file));
        const type = file.endsWith('.css') ? 'css' : /\.m?js$/i.test(file) ? 'js' : fontExtension.test(file) ? 'fonts' : 'other';
        assets.set(file, {
            type,
            raw: bytes.length,
            gzip: gzipSync(bytes, { level: 9 }).length,
            brotli: brotliCompressSync(bytes, { params: { [constants.BROTLI_PARAM_QUALITY]: 11 } }).length,
        });
        if (type !== 'css') return;
        const fonts = [];
        const css = bytes.toString('utf8').replace(/\/\*[\s\S]*?\*\//g, '');
        for (const face of css.matchAll(/@font-face\s*\{([^}]*)\}/gi)) {
            for (const match of face[1].matchAll(/url\(\s*(?:"([^"]*)"|'([^']*)'|([^)]*))\s*\)/gi)) {
                const url = (match[1] ?? match[2] ?? match[3]).trim();
                let decoded;
                try {
                    decoded = decodeURIComponent(url.split(/[?#]/, 1)[0]);
                } catch {
                    throw new Error(`Unsafe font URL in ${file}: ${url}`);
                }
                const isPublicAsset = decoded.startsWith(assetBase);
                const font = isPublicAsset
                    ? posix.normalize(decoded.slice(assetBase.length))
                    : posix.normalize(posix.join(posix.dirname(file), decoded));
                if (!decoded || /[\\\x00-\x1f:]/.test(decoded) || (posix.isAbsolute(decoded) && !isPublicAsset)
                    || posix.isAbsolute(font) || font === '..' || font.startsWith('../') || !fontExtension.test(font)) {
                    throw new Error(`Unsafe font URL in ${file}: ${url}`);
                }
                if (fontReferences.has(font)) {
                    throw new Error(`Duplicate font URL ${font}: ${fontReferences.get(font)} and ${file}`);
                }
                fontReferences.set(font, file);
                fonts.push(font);
                loadAsset(font);
            }
        }
        cssFonts.set(file, fonts);
    }

    for (const key of chunks) {
        const chunk = manifest[key];
        for (const file of [chunk.file, ...(chunk.css ?? []), ...(chunk.assets ?? [])]) loadAsset(file);
    }

    function summarize(files) {
        return [...files].reduce((sum, file) => {
            const asset = assets.get(file);
            return { count: sum.count + 1, raw: sum.raw + asset.raw, gzip: sum.gzip + asset.gzip, brotli: sum.brotli + asset.brotli };
        }, { count: 0, raw: 0, gzip: 0, brotli: 0 });
    }

    const scenarioReports = Object.fromEntries(Object.keys(scenarios).sort().map(name => {
        const files = new Set();
        const included = new Set();
        function include(key) {
            if (included.has(key)) return;
            included.add(key);
            const chunk = manifest[key];
            for (const file of [chunk.file, ...(chunk.css ?? []), ...(chunk.assets ?? [])]) {
                files.add(file);
                for (const font of cssFonts.get(file) ?? []) files.add(font);
            }
            for (const dependency of chunk.imports ?? []) include(dependency);
        }
        for (const entry of scenarios[name].entries) include(entry);
        return [name, { files: [...files].sort(), ...summarize(files) }];
    }));
    const totals = Object.fromEntries(categories.map(type => [type,
        summarize([...assets.keys()].filter(file => type === 'all' || assets.get(file).type === type)),
    ]));
    const entries = Object.fromEntries(chunks.map(key => [key, { file: manifest[key].file, ...assets.get(manifest[key].file) }]));
    const violations = [];
    function enforce(label, measured, ceilings) {
        for (const [metric, ceiling] of Object.entries(ceilings)) {
            if (measured[metric] > ceiling) violations.push(`${label} ${metric}: ${measured[metric]} bytes exceeds ${ceiling}`);
        }
    }
    for (const [name, ceilings] of Object.entries(entryLimits)) enforce(`entry ${name}`, entries[name], ceilings);
    for (const [name, scenario] of Object.entries(scenarios)) enforce(`scenario ${name}`, scenarioReports[name], scenario.limits ?? {});
    for (const [name, ceilings] of Object.entries(totalLimits)) enforce(`total ${name}`, totals[name], ceilings);
    return {
        version: 1,
        assetBase,
        compression: { gzipLevel: 9, brotliQuality: 11 },
        passed: violations.length === 0,
        assets: Object.fromEntries([...assets.keys()].sort().map(file => [file, assets.get(file)])),
        entries,
        scenarios: scenarioReports,
        totals,
        violations,
    };
}

try {
    const { values } = parseArgs({ options: {
        manifest: { type: 'string' }, budget: { type: 'string' }, json: { type: 'boolean' }, help: { type: 'boolean' },
    } });
    if (values.help) {
        console.log('Usage: node tests/frontend-assets.mjs --manifest PATH --budget PATH [--json]');
    } else {
        if (!values.manifest || !values.budget) throw new Error('Both --manifest PATH and --budget PATH are required');
        const report = measure(values.manifest, values.budget);
        if (values.json) {
            console.log(JSON.stringify(report, null, 2));
        } else {
            console.log('Frontend assets: individual responses; gzip level 9; Brotli quality 11');
            for (const [name, metrics] of Object.entries(report.entries)) {
                console.log(`Entry ${name}: ${metrics.file}; raw ${metrics.raw}; gzip ${metrics.gzip}; brotli ${metrics.brotli} bytes`);
            }
            for (const [name, metrics] of Object.entries(report.totals)) {
                console.log(`${name.toUpperCase()}: ${metrics.count} files; raw ${metrics.raw}; gzip ${metrics.gzip}; brotli ${metrics.brotli} bytes`);
            }
            for (const [name, metrics] of Object.entries(report.scenarios)) {
                console.log(`Scenario ${name}: ${metrics.count} files; raw ${metrics.raw}; gzip ${metrics.gzip}; brotli ${metrics.brotli} bytes`);
            }
            console.log(report.passed ? 'Asset budgets passed.' : 'Asset budgets failed.');
        }
        for (const violation of report.violations) console.error(violation);
        if (!report.passed) process.exitCode = 1;
    }
} catch (error) {
    console.error(`Frontend assets: ${error.message}`);
    process.exitCode = 1;
}
