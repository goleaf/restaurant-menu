// Project-local distribution pipeline. This does not reconstruct the upstream build.
import { createHash } from 'node:crypto';
import { readFile, readdir, lstat, writeFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';
import { minifySync, parseSync } from 'rolldown/utils';

const directory = dirname(fileURLToPath(import.meta.url));
const root = join(directory, '../../..');
const check = process.argv.includes('--check');
const require = createRequire(import.meta.url);
const hash = value => createHash('sha256').update(value).digest('hex');
const provenancePath = directory + '.provenance.json';
const provenance = JSON.parse(await readFile(provenancePath, 'utf8'));
const digest = files => hash(files.map(file => `${file.path}\t${file.bytes}\t${file.sha256}\n`).join(''));
const minifierVersion = require('rolldown/package.json').version;

if (minifierVersion !== '1.2.5') throw new Error('Local Flux build requires the locked rolldown 1.2.5; review and regenerate explicitly after toolchain changes.');
if (provenance.source_files.length !== 139 || digest(provenance.source_files) !== 'da0fcdf6baf21b0f747cae8fa804cfe45bcc7e1ac9e6bb101c9448695b5790c5') {
    throw new Error('The original 139-file source inventory must remain immutable.');
}

const donorPath = 'vendor/livewire/flux/dist/flux-lite.min.js';
const donorHash = 'e10cba88aaa015aaf1674a8ac6c78f45c9e79a113fdbdd90a02e66a08e6cea8a';
if (hash(await readFile(join(root, donorPath))) !== donorHash) throw new Error('The installed Free runtime differs from the reviewed 2.17.0 donor.');
const source = await readFile(join(directory, 'dist/flux.module.js'), 'utf8');
const parsed = parseSync('flux.module.js', source, { sourceType: 'script' });
if (parsed.errors.length) throw new Error(JSON.stringify(parsed.errors));
const debug = '(() => {\n' + source.trimEnd().split('\n').map(line => line ? '  ' + line : '').join('\n') + '\n})();\n';
const minifierOptions = {
    module: false,
    compress: { target: 'es2022', keepNames: { function: true, class: true } },
    mangle: { keepNames: true },
    codegen: { legalComments: 'inline' },
};
const minified = minifySync('flux.js', debug, minifierOptions);
if (minified.errors.length) throw new Error(JSON.stringify(minified.errors));
const production = minified.code.trimEnd() + '\n';
const manifest = JSON.parse(await readFile(join(directory, 'dist/manifest.json'), 'utf8'));
manifest['/flux.js'] = hash(source + debug + production).slice(0, 16);

async function output(path, value) {
    const current = await readFile(path, 'utf8');
    if (current === value) return;
    if (check) throw new Error(`Stale local Flux artifact: ${path}`);
    await writeFile(path, value);
}

await output(join(directory, 'dist/flux.js'), debug);
await output(join(directory, 'dist/flux.min.js'), production);
await output(join(directory, 'dist/manifest.json'), JSON.stringify(manifest, null, 2) + '\n');

async function inventory(relative = '') {
    const files = [];
    for (const name of await readdir(join(directory, relative))) {
        const path = relative ? `${relative}/${name}` : name;
        const stat = await lstat(join(directory, path));
        if (stat.isSymbolicLink()) throw new Error(`Distribution symlink: ${path}`);
        if (stat.isDirectory()) files.push(...await inventory(path));
        else if (stat.isFile()) files.push({ path, bytes: stat.size, sha256: hash(await readFile(join(directory, path))) });
        else throw new Error(`Non-regular distribution file: ${path}`);
    }
    return files.sort((a, b) => a.path < b.path ? -1 : a.path > b.path ? 1 : 0);
}

const reasons = {
    ...Object.fromEntries(provenance.local_patches.map(patch => [patch.path, patch.reason])),
    'dist/flux.module.js': 'Readable project-local backports from the installed proprietary Free 2.17.0 runtime: OTP replacement/touch, modal escape policy, tooltip/sidebar listener cleanup, sidebar/menu focus, dropdown scroll/navmenu/reference visibility, disclosure find-in-page and toast lifecycle/link options. Project-local tooltip unmount also releases its positioning observers and listeners. Existing Pro runtime and editor assets retained.',
    'dist/flux.js': 'Generated IIFE from the reviewed local flux.module.js using build-runtime.mjs; one combined runtime, no additional Alpine or Free bundle.',
    'dist/flux.min.js': 'Generated from the same local IIFE by locked rolldown 1.2.5 with class/function names preserved for Mixin lookup. This is a local artifact, not an upstream build.',
    'dist/manifest.json': 'Local cache identifier derives from all three Flux JavaScript artifacts; original editor entries remain unchanged.',
};
const additions = { 'build-runtime.mjs': 'Reproducible local distribution build and --check mode, immutable original inventory, donor check, generated artifacts and per-file provenance.' };
const files = await inventory();
const originals = new Map(provenance.source_files.map(file => [file.path, file]));
for (const original of originals.keys()) if (!files.some(file => file.path === original)) throw new Error(`Missing original source file: ${original}`);
provenance.local_patches = [];
provenance.local_additions = [];
for (const file of files) {
    const original = originals.get(file.path);
    if (!original) {
        if (!additions[file.path]) throw new Error(`Undocumented local addition: ${file.path}`);
        provenance.local_additions.push({ ...file, reason: additions[file.path] });
    } else if (original.sha256 !== file.sha256) {
        if (!reasons[file.path]) throw new Error(`Undocumented source patch: ${file.path}`);
        provenance.local_patches.push({ path: file.path, original_sha256: original.sha256, local_sha256: file.sha256, reason: reasons[file.path] });
    }
}
provenance.local_release.changed_files = provenance.local_patches.map(patch => patch.path);
provenance.files = files;
provenance.internal_snapshot = {
    file_count: files.length,
    total_bytes: files.reduce((sum, file) => sum + file.bytes, 0),
    inventory_sha256: digest(files),
    inventory_format: 'UTF-8; bytewise full-path order; path TAB bytes TAB sha256 LF',
};
provenance.runtime_build = {
    kind: 'project-local; not an upstream build reconstruction',
    source: 'dist/flux.module.js',
    command: 'node packages/livewire/flux-pro/build-runtime.mjs',
    verify_command: 'node packages/livewire/flux-pro/build-runtime.mjs --check',
    minifier: { package: 'rolldown', version: minifierVersion, options: minifierOptions },
    compatibility_donor: { package: 'livewire/flux', version: '2.17.0', license: 'proprietary', path: donorPath, sha256: donorHash },
    unchanged_editor_assets: ['dist/editor.css', 'dist/editor.js', 'dist/editor.min.js', 'dist/editor.module.js'],
};
await output(provenancePath, JSON.stringify(provenance, null, 2) + '\n');
await output(directory + '.sha256', files.map(file => `${file.sha256}  ${file.path}\n`).join(''));
console.log(`${check ? 'Verified' : 'Built'} local Flux runtime: ${files.length} files; ${provenance.local_patches.length} patches; ${provenance.local_additions.length} local addition.`);
