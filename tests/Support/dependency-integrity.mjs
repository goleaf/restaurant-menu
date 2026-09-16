import { createHash } from 'node:crypto';
import { existsSync, lstatSync, readdirSync, readFileSync, realpathSync } from 'node:fs';
import { join, resolve, sep } from 'node:path';
import { isDeepStrictEqual } from 'node:util';

const json = path => JSON.parse(readFileSync(path, 'utf8'));
const digest = value => createHash('sha256').update(JSON.stringify(value)).digest('hex');
const version = value => typeof value === 'string' ? value.replace(/^v(?=\d)/, '') : null;
const sourceIdentity = item => Object.fromEntries(['source', 'dist'].map(kind => [kind,
    item[kind] ? { type: item[kind].type ?? null, url: item[kind].url ?? null, reference: item[kind].reference ?? null } : null,
]));

function namedPackages(packages, label) {
    if (!Array.isArray(packages)) throw new Error(`${label} package records are missing.`);
    const result = new Map();
    for (const item of packages) {
        if (typeof item.name !== 'string' || !version(item.version) || result.has(item.name)) throw new Error(`${label} contains invalid or duplicate package records.`);
        result.set(item.name, item);
    }
    return result;
}

function composerIdentity(root) {
    const lock = json(join(root, 'composer.lock'));
    const locked = namedPackages([...lock.packages, ...(lock['packages-dev'] ?? [])], 'Composer lock');
    const installedDocument = json(join(root, 'vendor/composer/installed.json'));
    const installed = namedPackages(installedDocument.packages ?? installedDocument, 'Composer installed');
    const vendor = realpathSync(join(root, 'vendor'));
    for (const name of installed.keys()) if (!locked.has(name)) throw new Error(`Composer unexpected installed package: ${name}`);
    const identities = [];
    for (const [name, expected] of locked) {
        const actual = installed.get(name);
        if (!actual) throw new Error(`Composer missing installed package: ${name}`);
        if (version(actual.version) !== version(expected.version)) throw new Error(`Composer version mismatch: ${name}`);
        if (!isDeepStrictEqual(sourceIdentity(actual), sourceIdentity(expected))) throw new Error(`Composer source/dist reference mismatch: ${name}`);
        let manifestDigest = null;
        if (expected.type !== 'metapackage') {
            if (typeof actual['install-path'] !== 'string') throw new Error(`Composer missing install path: ${name}`);
            const location = realpathSync(resolve(vendor, 'composer', actual['install-path']));
            if (!location.startsWith(vendor+sep)) throw new Error(`Composer package outside vendor: ${name}`);
            const manifest = join(location, 'composer.json');
            if (json(manifest).name !== name) throw new Error(`Composer package identity mismatch: ${name}`);
            manifestDigest = digest(readFileSync(manifest, 'utf8'));
        }
        identities.push({ name, version: version(actual.version), ...sourceIdentity(actual), manifestDigest });
    }
    identities.sort((a, b) => a.name.localeCompare(b.name));
    return { count: identities.length, metadataDigest: digest(identities), identities };
}

function validPackagePath(path) {
    return typeof path === 'string' && !/[\\\0\r\n]/.test(path)
        && !path.split('/').some(part => part === '.' || part === '..')
        && /^(?:node_modules\/(?:@[^/]+\/)?[^/]+)(?:\/node_modules\/(?:@[^/]+\/)?[^/]+)*$/.test(path);
}

function platformMatches(entry) {
    const libc = process.platform === 'linux' ? (process.report.getReport().header.glibcVersionRuntime ? 'glibc' : 'musl') : null;
    for (const [key, current] of [['os', process.platform], ['cpu', process.arch], ['libc', libc]]) {
        const values = entry[key];
        if (values === undefined) continue;
        if (!Array.isArray(values) || !values.every(value => typeof value === 'string')) throw new Error(`npm invalid ${key} platform restriction.`);
        const positive = values.filter(value => !value.startsWith('!'));
        if (values.includes('!'+current) || (positive.length > 0 && !positive.includes(current) && !positive.includes('any'))) return false;
    }
    return true;
}

function installedNpmPackages(root) {
    const packages = new Map();
    function inspectPackage(path) {
        const location = join(root, path);
        if (lstatSync(location).isSymbolicLink()) throw new Error(`npm package symbolic link is not supported: ${path}`);
        const manifest = join(location, 'package.json');
        if (!existsSync(manifest)) throw new Error(`npm installed package has no manifest: ${path}`);
        packages.set(path, { manifest: json(manifest), manifestDigest: digest(readFileSync(manifest, 'utf8')) });
        inspectModules(path+'/node_modules');
    }
    function inspectModules(path) {
        if (!existsSync(join(root, path))) return;
        if (lstatSync(join(root, path)).isSymbolicLink()) throw new Error(`npm node_modules symbolic link is not supported: ${path}`);
        for (const entry of readdirSync(join(root, path), { withFileTypes: true })) {
            if (entry.name.startsWith('.')) continue;
            const packagePath = path+'/'+entry.name;
            if (entry.name.startsWith('@')) {
                if (entry.isSymbolicLink()) throw new Error(`npm package scope symbolic link is not supported: ${packagePath}`);
                for (const child of readdirSync(join(root, packagePath))) inspectPackage(packagePath+'/'+child);
            } else {
                inspectPackage(packagePath);
            }
        }
    }
    inspectModules('node_modules');
    return packages;
}

function npmIdentity(root) {
    const manifest = json(join(root, 'package.json'));
    const lock = json(join(root, 'package-lock.json'));
    if (lock.lockfileVersion !== 3 || !lock.packages?.['']) throw new Error('npm requires lockfile version 3 with root metadata.');
    for (const field of ['dependencies', 'devDependencies', 'optionalDependencies', 'peerDependencies', 'peerDependenciesMeta', 'engines']) {
        if (!isDeepStrictEqual(manifest[field] ?? {}, lock.packages[''][field] ?? {})) throw new Error(`npm manifest/lock ${field} mismatch.`);
    }
    const locked = new Map(Object.entries(lock.packages).filter(([path]) => path !== ''));
    for (const [path, entry] of locked) {
        if (!validPackagePath(path) || entry.link || typeof entry.version !== 'string') throw new Error(`npm invalid locked package path or version: ${path}`);
    }
    const installed = installedNpmPackages(root);
    const identities = [];
    for (const [path, actual] of installed) {
        const expected = locked.get(path);
        if (!expected) throw new Error(`npm unexpected installed package: ${path}`);
        if (actual.manifest.version !== expected.version) throw new Error(`npm version mismatch: ${path}`);
        const expectedName = expected.name ?? path.slice(path.lastIndexOf('node_modules/')+'node_modules/'.length);
        if (actual.manifest.name !== expectedName) throw new Error(`npm package name mismatch: ${path}`);
        identities.push({ path, name: actual.manifest.name, version: actual.manifest.version,
            resolved: expected.resolved ?? null, integrity: expected.integrity ?? null, manifestDigest: actual.manifestDigest });
    }
    function locate(parent, name) {
        let location = parent;
        while (true) {
            const candidate = (location ? location+'/' : '')+'node_modules/'+name;
            if (!validPackagePath(candidate)) throw new Error(`npm invalid dependency path: ${name}`);
            if (locked.has(candidate)) return candidate;
            if (location === '') return null;
            const separator = location.lastIndexOf('/node_modules/');
            location = separator < 0 ? '' : location.slice(0, separator);
        }
    }
    const visited = new Set();
    function visit(path, optional = false, optionalPeer = false) {
        const entry = locked.get(path);
        if (!platformMatches(entry)) {
            if (optional && entry.optional) return;
            throw new Error(`npm required package is incompatible with this platform: ${path}`);
        }
        if (!installed.has(path)) {
            if (optionalPeer) return;
            throw new Error(`npm missing required or applicable optional package: ${path}`);
        }
        if (visited.has(path)) return;
        visited.add(path);
        visitEdges(path, entry);
    }
    function visitEdges(parent, entry, includeDev = false) {
        const edges = { ...entry.dependencies, ...(includeDev ? entry.devDependencies : {}), ...entry.optionalDependencies };
        for (const name of Object.keys(edges)) {
            const path = locate(parent, name);
            if (!path) throw new Error(`npm missing locked dependency: ${name} from ${parent || 'root'}`);
            visit(path, Object.hasOwn(entry.optionalDependencies ?? {}, name));
        }
        for (const name of Object.keys(entry.peerDependencies ?? {})) {
            const path = locate(parent, name);
            const optionalPeer = entry.peerDependenciesMeta?.[name]?.optional === true;
            if (!path && !optionalPeer) throw new Error(`npm missing required peer: ${name} from ${parent || 'root'}`);
            if (path) visit(path, optionalPeer, optionalPeer);
        }
    }
    visitEdges('', lock.packages[''], true);
    const excludedOptionalPaths = [];
    for (const [path, entry] of locked) {
        if (installed.has(path)) continue;
        if (!entry.optional) throw new Error(`npm missing locked package: ${path}`);
        excludedOptionalPaths.push(path);
    }
    identities.sort((a, b) => a.path.localeCompare(b.path));
    return { count: identities.length, lockedCount: locked.size, excludedOptionalPaths: excludedOptionalPaths.sort(), metadataDigest: digest(identities), identities };
}

export function assertDependencyIntegrity(root) {
    const composer = composerIdentity(root);
    const npm = npmIdentity(root);
    return { composer, npm, digest: digest({ composer: composer.metadataDigest, npm: npm.metadataDigest }) };
}
