import { createHash, randomBytes } from 'node:crypto';
import { accessSync, closeSync, constants, existsSync, fstatSync, lstatSync, mkdirSync, openSync, readdirSync, readFileSync, realpathSync, rmSync, symlinkSync, writeFileSync } from 'node:fs';
import { basename, delimiter, dirname, isAbsolute, join, relative, resolve, sep } from 'node:path';
import { runVerificationProcess } from './verification-process.mjs';

const phpProbe = 'echo json_encode(["version" => PHP_VERSION, "versionId" => PHP_VERSION_ID, "sapi" => PHP_SAPI, "binary" => PHP_BINARY, "extensions" => get_loaded_extensions(), "iniFiles" => array_values(array_filter(array_merge([php_ini_loaded_file()], preg_split("/,\\\\s*/", (string) php_ini_scanned_files()))))], JSON_THROW_ON_ERROR);';
const inheritedKeys = ['PATH', 'HOME', 'TMPDIR', 'TMP', 'TEMP', 'SystemRoot', 'WINDIR', 'LANG', 'LC_ALL', 'LC_CTYPE', 'TERM', 'NO_COLOR', 'FORCE_COLOR', 'PLAYWRIGHT_BROWSERS_PATH'];
const excludedTrees = ['.git', '.idea', '.vscode', '.codex', '.openai', 'vendor', 'node_modules', 'storage', 'bootstrap/cache', 'public/storage', 'public/build', 'tests/Browser/Screenshots', 'coverage', 'artifacts', '.phpunit.cache', '.phpstan.cache'];
const ownedRuntimes = new WeakSet();

function rejectPlatformBypass(environment) {
    for (const name of ['COMPOSER_IGNORE_PLATFORM_REQ', 'COMPOSER_IGNORE_PLATFORM_REQS']) {
        if (environment[name] && environment[name] !== '0') throw new Error('Composer platform requirement bypass is not permitted.');
    }
}

function inheritedEnvironment(environment) {
    rejectPlatformBypass(environment);
    return Object.fromEntries(inheritedKeys.filter(key => typeof environment[key] === 'string').map(key => [key, environment[key]]));
}

function absoluteFile(path, executable = false) {
    if (typeof path !== 'string' || !isAbsolute(path) || path.includes('\0')) throw new Error('An explicit absolute executable path is required.');
    const resolved = realpathSync(path);
    if (!lstatSync(resolved).isFile()) throw new Error('The executable path must identify a regular file.');
    accessSync(resolved, executable ? constants.R_OK | constants.X_OK : constants.R_OK);
    return resolved;
}

function engineMinimum(range) {
    const match = typeof range === 'string' ? range.match(/^>=(\d+)\.(\d+)\.(\d+) <(\d+)$/) : null;
    if (!match) throw new Error('Tool engine range must declare a stable minimum and exclusive major ceiling.');
    return match.slice(1).map(Number);
}

function assertEngine(name, version, range) {
    const [major, minor, patch, ceiling] = engineMinimum(range);
    const actual = version.split('.').map(Number);
    const minimum = [major, minor, patch];
    const difference = actual.findIndex((value, index) => value !== minimum[index]);
    if (actual[0] >= ceiling || (difference !== -1 && actual[difference] < minimum[difference])) {
        throw new Error(`${name} ${version} does not satisfy ${range}.`);
    }
}

export async function resolveJavaScriptRuntime({ npmBinary, engines, env = process.env }) {
    engineMinimum(engines?.node);
    engineMinimum(engines?.npm);
    if (!/^\d+\.\d+\.\d+$/.test(process.versions.node)) throw new Error('Node must be a stable release.');
    assertEngine('Node', process.versions.node, engines.node);
    const nodeBinary = absoluteFile(process.execPath, true);
    const npmPath = absoluteFile(npmBinary);
    const probe = await runVerificationProcess([nodeBinary, npmPath, '--version'], {
        env: inheritedEnvironment(env), timeout: 15_000,
    });
    if (probe.code !== 0) throw new Error('The selected npm CLI probe failed.');
    const npmVersion = probe.output.trim();
    if (!/^\d+\.\d+\.\d+$/.test(npmVersion)) throw new Error('The selected npm CLI returned an invalid npm version.');
    assertEngine('npm', npmVersion, engines.npm);
    return Object.freeze({ nodeBinary, nodeVersion: process.versions.node, npmBinary: npmPath, npmVersion });
}

export async function resolvePhpRuntime({ label, binary, expectedVersion, composerBinary, allowPrerelease = false, env = process.env }) {
    if (typeof label !== 'string' || !/^[a-z][a-z0-9-]*$/.test(label)) throw new Error('A runtime label is required.');
    if (typeof expectedVersion !== 'string' || !/^\d+\.\d+$/.test(expectedVersion)) throw new Error('Expected PHP must be an explicit major.minor version.');
    if (typeof allowPrerelease !== 'boolean') throw new Error('Prerelease permission must be a boolean.');
    const environment = inheritedEnvironment(env);
    const phpBinary = absoluteFile(binary, true);
    const composerPath = absoluteFile(composerBinary);
    const probe = await runVerificationProcess([phpBinary, '-r', phpProbe], { env: { ...environment, PHP_BINARY: phpBinary }, timeout: 15_000 });
    if (probe.code !== 0) throw new Error('The selected PHP runtime probe failed.');
    let identity;
    try { identity = JSON.parse(probe.output); } catch { throw new Error('The PHP runtime returned an invalid identity.'); }
    const version = typeof identity.version === 'string' ? identity.version.match(/^(\d+)\.(\d+)\.(\d+)(.*)$/) : null;
    if (!version || !Number.isInteger(identity.versionId) || identity.sapi !== 'cli'
        || identity.versionId !== Number(version[1]) * 10000 + Number(version[2]) * 100 + Number(version[3])
        || absoluteFile(identity.binary, true) !== phpBinary
        || !Array.isArray(identity.extensions) || !identity.extensions.every(value => typeof value === 'string')
        || !Array.isArray(identity.iniFiles) || !identity.iniFiles.every(value => typeof value === 'string')) throw new Error('The PHP runtime returned a misleading identity.');
    if (`${version[1]}.${version[2]}` !== expectedVersion) throw new Error(`Expected PHP ${expectedVersion}, received ${identity.version}.`);
    if (!allowPrerelease && version[4] !== '') throw new Error('The stable runtime must not be a prerelease.');
    const composer = await runVerificationProcess([phpBinary, composerPath, '--version', '--no-ansi'], {
        env: { ...environment, PHP_BINARY: phpBinary, COMPOSER_ALLOW_XDEBUG: '1' }, timeout: 15_000,
    });
    const composerVersion = composer.output.match(/^Composer version (\d+\.\d+\.\d+(?:-[\w.]+)?)(?:\s|$)/m)?.[1];
    if (composer.code !== 0 || !composerVersion) throw new Error('Composer did not run successfully under the selected PHP runtime.');
    const runtime = Object.freeze({ label, phpBinary, composerBinary: composerPath, expectedVersion, version: identity.version,
        versionId: identity.versionId, sapi: identity.sapi, extensions: Object.freeze([...identity.extensions]), iniFiles: Object.freeze([...identity.iniFiles]), composerVersion });
    ownedRuntimes.add(runtime);
    return runtime;
}

function commandArguments(runtime, args) {
    if (!ownedRuntimes.has(runtime)) throw new Error('A verified PHP runtime is required.');
    if (!Array.isArray(args) || !args.every(argument => typeof argument === 'string' && !argument.includes('\0'))) throw new Error('Command arguments must be strings without null bytes.');
    if (args.some(argument => /^--ignore-platform-reqs?(?:=|$)/.test(argument))) throw new Error('Composer platform requirement bypass is not permitted.');
    return args;
}

export function phpCommand(runtime, args) {
    return [runtime.phpBinary, ...commandArguments(runtime, args)];
}

export function composerCommand(runtime, args) {
    return [runtime.phpBinary, runtime.composerBinary, ...commandArguments(runtime, args)];
}

export function coverageEnvironment(environment, artifacts, extension) {
    const binary = absoluteFile(extension);
    if (/["\r\n]/.test(binary)) throw new Error('Invalid coverage extension path.');
    const directory = join(artifacts, 'coverage-ini');
    mkdirSync(directory, { mode: 0o700 });
    writeFileSync(join(directory, 'xdebug.ini'), `zend_extension="${binary}"\nxdebug.mode=coverage\n`, { flag: 'wx', mode: 0o600 });
    // An empty scan entry retains PHP's compiled default configuration directory.
    return { ...environment, PHP_INI_SCAN_DIR: delimiter+directory, XDEBUG_MODE: 'coverage' };
}

function safeExclusions(exclude) {
    if (!Array.isArray(exclude) || !exclude.every(path => typeof path === 'string' && path !== '' && !isAbsolute(path)
        && !path.split('/').some(part => part === '..' || part === '.' || part === '') && !/[\\\t\r\n\0]/.test(path))) throw new Error('Source exclusions must be relative subtree paths without traversal.');
    return exclude;
}

function excluded(path, exclude) {
    if (path === 'storage/app/public/.htaccess') return false;
    const name = path.split('/').at(-1);
    return [...excludedTrees, ...exclude].some(tree => path === tree || path.startsWith(tree+'/'))
        || (name.startsWith('.env') && name !== '.env.example')
        || ['auth.json', '.npmrc', '.netrc', '.DS_Store', '.phpunit.result.cache', 'public/hot'].includes(name === 'hot' ? path : name)
        || /\.(?:sqlite|sqlite3|db)(?:-(?:wal|shm|journal))?$/i.test(name)
        || /\.(?:pem|key|p12|pfx|jks)$/i.test(name);
}

function regularFileBytes(path) {
    const descriptor = openSync(path, constants.O_RDONLY | constants.O_NOFOLLOW);
    try {
        if (!fstatSync(descriptor).isFile()) throw new Error('Only regular source files may be inventoried.');
        return readFileSync(descriptor);
    } finally { closeSync(descriptor); }
}

const sha256 = bytes => createHash('sha256').update(bytes).digest('hex');

export function sourceInventory(root, { exclude = [] } = {}) {
    const sourceRoot = realpathSync(root);
    const exclusions = safeExclusions(exclude);
    const files = [];
    function inspect(path) {
        if (/[\t\r\n\0]/.test(path)) throw new Error('Source paths must not contain control characters.');
        if (excluded(path, exclusions)) return;
        const fullPath = join(sourceRoot, path);
        const stat = lstatSync(fullPath);
        if (stat.isSymbolicLink()) throw new Error(`Source symbolic link is not permitted: ${path}`);
        if (stat.isDirectory()) {
            for (const entry of readdirSync(fullPath)) inspect(path ? `${path}/${entry}` : entry);
        } else {
            const bytes = regularFileBytes(fullPath);
            files.push({ path, bytes: bytes.length, sha256: sha256(bytes) });
        }
    }
    for (const entry of readdirSync(sourceRoot)) inspect(entry);
    const accessRule = 'storage/app/public/.htaccess';
    if (existsSync(join(sourceRoot, accessRule))) inspect(accessRule);
    files.sort((one, two) => one.path < two.path ? -1 : one.path > two.path ? 1 : 0);
    const locks = Object.fromEntries(['composer.lock', 'package-lock.json'].map(path => [path, files.find(file => file.path === path)?.sha256 ?? null]));
    return { digest: sha256(files.map(file => `${file.path}\t${file.bytes}\t${file.sha256}\n`).join('')), files, locks };
}

export function createSourceSnapshot({ sourceRoot, destination, exclude = [] }) {
    const source = realpathSync(sourceRoot);
    const target = join(realpathSync(dirname(resolve(destination))), basename(destination));
    const relativeTarget = relative(source, target);
    if (relativeTarget === '' || (!relativeTarget.startsWith('..'+sep) && !isAbsolute(relativeTarget))) throw new Error('Source snapshot must be outside the source directory.');
    if (existsSync(target)) throw new Error('Source snapshot destination already exists.');
    const before = sourceInventory(source, { exclude });
    mkdirSync(target, { mode: 0o700 });
    try {
        for (const file of before.files) {
            const bytes = regularFileBytes(join(source, file.path));
            if (sha256(bytes) !== file.sha256) throw new Error('Source changed while creating the snapshot.');
            const output = join(target, file.path);
            mkdirSync(dirname(output), { recursive: true, mode: 0o700 });
            writeFileSync(output, bytes, { flag: 'wx', mode: lstatSync(join(source, file.path)).mode & 0o777 });
        }
        if (sourceInventory(source, { exclude }).digest !== before.digest || sourceInventory(target).digest !== before.digest) throw new Error('Source changed while creating the snapshot.');
        writeFileSync(join(target, '.env'), '', { flag: 'wx', mode: 0o600 });
        mkdirSync(join(target, 'bootstrap/cache'), { recursive: true, mode: 0o700 });
        return before;
    } catch (error) {
        rmSync(target, { recursive: true, force: true });
        throw error;
    }
}

export async function runPlatformPreflight({ run, php, composer }) {
    // These read-only checks are independent; retain all diagnostics before refusing application execution.
    const checks = [
        ['runtime-capabilities', php(['-r', 'echo json_encode(["version"=>PHP_VERSION,"sapi"=>PHP_SAPI,"binary"=>PHP_BINARY,"extensions"=>get_loaded_extensions(),"gd"=>function_exists("gd_info") ? gd_info() : null,"sqlite"=>class_exists("SQLite3", false) ? SQLite3::version() : null,"settings"=>array_map(ini_get(...),array_combine(["memory_limit","upload_max_filesize","post_max_size","upload_tmp_dir","sys_temp_dir","error_reporting","opcache.enable_cli"],["memory_limit","upload_max_filesize","post_max_size","upload_tmp_dir","sys_temp_dir","error_reporting","opcache.enable_cli"]))],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);'])],
        ['composer-manifest', composer(['validate', '--strict', '--no-check-publish'])],
        ['platform-lock', composer(['check-platform-reqs', '--lock'])],
        ['platform-installed', composer(['check-platform-reqs'])],
    ];
    const failures = [];
    for (const [name, command] of checks) {
        try { await run(name, command); } catch (error) {
            if ([130, 143].includes(error.exitCode)) throw error;
            failures.push(error);
        }
    }
    if (failures.length) throw new AggregateError(failures, `Platform preflight failed: ${failures.map(error => error.message).join('; ')}`);
}

export function createVerificationEnvironment({ runtime, artifacts, baseEnv = process.env, appKey }) {
    commandArguments(runtime, []);
    const environment = inheritedEnvironment(baseEnv);
    const root = resolve(artifacts);
    if (existsSync(root)) throw new Error('Verification artifact directory already exists.');
    mkdirSync(root, { mode: 0o700 });
    const storage = join(root, 'storage');
    for (const directory of ['app/public', 'app/private', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs', 'cache']) mkdirSync(join(storage, directory), { recursive: true, mode: 0o700 });
    const bin = join(root, 'bin');
    mkdirSync(bin, { mode: 0o700 });
    symlinkSync(runtime.phpBinary, join(bin, 'php'));
    const key = appKey ?? `base64:${randomBytes(32).toString('base64')}`;
    if (!/^base64:[A-Za-z0-9+/]{43}=$/.test(key)) throw new Error('Verification requires a valid isolated application key.');
    return { ...environment, PATH: bin+(environment.PATH ? delimiter+environment.PATH : ''), PHP_BINARY: runtime.phpBinary,
        RESTAURANT_EXPECTED_PHP_VERSION: runtime.version, RESTAURANT_EXPECTED_PHP_BINARY: runtime.phpBinary,
        APP_ENV: 'testing', APP_DEBUG: 'false', APP_KEY: key, APP_URL: 'http://localhost', ASSET_URL: '',
        APP_MAINTENANCE_DRIVER: 'file', DB_CONNECTION: 'sqlite', DB_DATABASE: ':memory:', DB_URL: '',
        CACHE_STORE: 'array', SESSION_DRIVER: 'array', SESSION_DOMAIN: '', SESSION_SECURE_COOKIE: 'false', QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'array',
        BROADCAST_CONNECTION: 'null', ERROR_NOTIFICATIONS_ENABLED: 'false', PULSE_ENABLED: 'false', TELESCOPE_ENABLED: 'false', NIGHTWATCH_ENABLED: 'false',
        LARAVEL_STORAGE_PATH: storage, VIEW_COMPILED_PATH: join(storage, 'framework/views'),
        ...Object.fromEntries(['CONFIG', 'ROUTES', 'EVENTS', 'PACKAGES', 'SERVICES'].map(kind => [`APP_${kind}_CACHE`, join(storage, `cache/${kind.toLowerCase()}.php`)])),
        COMPOSER_HOME: join(root, 'composer-home'), COMPOSER_PROCESS_TIMEOUT: '0', COMPOSER_ALLOW_XDEBUG: '1', PAO_DISABLE: '1',
    };
}
