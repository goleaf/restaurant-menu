import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { copyFileSync, mkdirSync, mkdtempSync, readFileSync, rmSync, symlinkSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import test from 'node:test';

const root = dirname(dirname(fileURLToPath(import.meta.url)));
const distribution = join(root, 'packages/livewire/flux-pro');

test('the checked-in local runtime and provenance are reproducible', () => {
    const result = execFileSync(process.execPath, [join(distribution, 'build-runtime.mjs'), '--check'], { encoding: 'utf8' });
    assert.match(result, /Verified local Flux runtime: 140 files; 10 patches; 1 local addition\./);
});

for (const stale of ['flux.js', 'flux.min.js', 'manifest.json']) {
    test(`build --check rejects stale ${stale} without repairing any artifact`, t => {
        const isolated = mkdtempSync(join(tmpdir(), 'restaurant-flux-runtime-build-'));
        t.after(() => rmSync(isolated, { recursive: true, force: true }));
        const packagePath = join(isolated, 'packages/livewire/flux-pro');
        mkdirSync(join(packagePath, 'dist'), { recursive: true });
        mkdirSync(join(isolated, 'vendor/livewire/flux/dist'), { recursive: true });
        symlinkSync(join(root, 'node_modules'), join(isolated, 'node_modules'), 'dir');
        copyFileSync(join(root, 'vendor/livewire/flux/dist/flux-lite.min.js'), join(isolated, 'vendor/livewire/flux/dist/flux-lite.min.js'));
        copyFileSync(distribution + '.provenance.json', packagePath + '.provenance.json');
        copyFileSync(join(distribution, 'build-runtime.mjs'), join(packagePath, 'build-runtime.mjs'));
        const artifacts = ['flux.module.js', 'flux.js', 'flux.min.js', 'manifest.json'];
        for (const name of artifacts) copyFileSync(join(distribution, 'dist', name), join(packagePath, 'dist', name));
        const path = join(packagePath, 'dist', stale);
        if (stale === 'manifest.json') {
            const manifest = JSON.parse(readFileSync(path, 'utf8'));
            manifest['/flux.js'] = 'stale';
            writeFileSync(path, JSON.stringify(manifest, null, 2) + '\n');
        } else {
            writeFileSync(path, readFileSync(path, 'utf8') + '// stale test artifact\n');
        }
        const before = artifacts.map(name => readFileSync(join(packagePath, 'dist', name), 'utf8'));
        assert.throws(() => execFileSync(process.execPath, [join(packagePath, 'build-runtime.mjs'), '--check'], { encoding: 'utf8', stdio: 'pipe' }), error => {
            assert.match(error.stderr, new RegExp(`Stale local Flux artifact: .*${stale.replaceAll('.', '\\.')}`));
            return error.status === 1;
        });
        assert.deepEqual(artifacts.map(name => readFileSync(join(packagePath, 'dist', name), 'utf8')), before);
    });
}
