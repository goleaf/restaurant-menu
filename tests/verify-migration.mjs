import { runVerificationProcess } from './Support/verification-process.mjs';
import { assertDependencyIntegrity } from './Support/dependency-integrity.mjs';
import { composerCommand, coverageEnvironment, createSourceSnapshot, createVerificationEnvironment, phpCommand, resolvePhpRuntime, sourceInventory } from './Support/platform-verification.mjs';
import { constants, copyFileSync, cpSync, mkdtempSync, readFileSync, realpathSync, symlinkSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { parseArgs } from 'node:util';

let artifacts;
const summary = { status: 'incomplete', steps: [] };
try {
    const { values } = parseArgs({ options: {
        php: { type: 'string' }, composer: { type: 'string' }, npm: { type: 'string' },
        'coverage-extension': { type: 'string' },
        'expected-php': { type: 'string', default: '8.5' }, preflight: { type: 'boolean', default: false },
    } });
    if (!values.php || !values.composer) throw new Error('Explicit --php /absolute/php and --composer /absolute/composer paths are required.');
    if (!['8.5', '8.6'].includes(values['expected-php'])) throw new Error('Expected PHP must be 8.5 (stable) or 8.6 (experimental).');
    const experimental = values['expected-php'] === '8.6';
    const runtime = await resolvePhpRuntime({ label: experimental ? 'experimental' : 'stable', binary: values.php,
        composerBinary: values.composer, expectedVersion: values['expected-php'], allowPrerelease: experimental });
    artifacts = mkdtempSync(join(tmpdir(), 'restaurant-migration-verification-'));
    summary.runtime = runtime;
    summary.node = process.version;
    const sourceRoot = process.cwd();
    summary.dependencies = assertDependencyIntegrity(sourceRoot);
    const workspace = join(artifacts, 'source');
    summary.source = createSourceSnapshot({ sourceRoot, destination: workspace });
    const environment = createVerificationEnvironment({ runtime, artifacts: join(artifacts, 'runtime') });
    const npmBinary = realpathSync(values.npm ?? process.env.npm_execpath ?? join(dirname(process.execPath), 'npm'));
    // Nested package scripts inherit the exact selected Node, npm and PHP executables.
    symlinkSync(process.execPath, join(artifacts, 'runtime/bin/node'));
    symlinkSync(npmBinary, join(artifacts, 'runtime/bin/npm'));
    summary.npmBinary = npmBinary;
    for (const tree of ['vendor', 'node_modules']) cpSync(join(sourceRoot, tree), join(workspace, tree), { recursive: true, verbatimSymlinks: true, mode: constants.COPYFILE_FICLONE });
    if (assertDependencyIntegrity(workspace).digest !== summary.dependencies.digest) throw new Error('Dependency metadata changed while copying the installed graph.');
    copyFileSync(join(sourceRoot, 'storage/app/public/.htaccess'), join(environment.LARAVEL_STORAGE_PATH, 'app/public/.htaccess'));
    const php = args => phpCommand(runtime, args);
    const composer = args => composerCommand(runtime, ['--no-plugins', ...args]);
    const record = () => writeFileSync(join(artifacts, 'summary.json'), JSON.stringify(summary, null, 2)+'\n');
    record();
    console.log(`Migration artifacts: ${artifacts}\nPHP ${runtime.version}: ${runtime.phpBinary}\nDisposable source: ${workspace}`);
    async function run(name, command, { timeout = 600_000, env = {} } = {}) {
        console.log(`\n[${name}] ${command.join(' ')}`);
        const started = Date.now();
        const result = await runVerificationProcess(command, { cwd: workspace,
            env: { ...environment, ...env }, timeout, onOutput: chunk => process.stdout.write(chunk) });
        writeFileSync(join(artifacts, `${name}.log`), result.output);
        summary.steps.push({ name, exitCode: result.code, timeout: result.timedOut, milliseconds: Date.now()-started });
        record();
        if (result.code !== 0) throw new Error(`${name} failed with exit ${result.code}`);
    }
    await run('composer-manifest', composer(['validate', '--strict', '--no-check-publish']));
    await run('platform-lock', composer(['check-platform-reqs', '--lock']));
    await run('platform-installed', composer(['check-platform-reqs']));
    await run('runtime-capabilities', php(['-r', 'echo json_encode(["version"=>PHP_VERSION,"sapi"=>PHP_SAPI,"binary"=>PHP_BINARY,"gd"=>gd_info(),"sqlite"=>SQLite3::version(),"settings"=>array_map(ini_get(...),array_combine(["memory_limit","upload_max_filesize","post_max_size","upload_tmp_dir","sys_temp_dir","error_reporting","opcache.enable_cli"],["memory_limit","upload_max_filesize","post_max_size","upload_tmp_dir","sys_temp_dir","error_reporting","opcache.enable_cli"]))],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);']));
    if (!values.preflight) {
        await run('composer-audit', composer(['audit', '--locked', '--no-interaction']));
        await run('npm-installed', ['npm', 'ls', '--depth=0']);
        await run('npm-audit', ['npm', 'audit', '--registry=https://registry.npmjs.org']);
        await run('architecture', ['npm', 'run', 'test:architecture']);
        await run('scss-lint', ['npm', 'run', 'lint:styles']);
        await run('js-lint', ['npm', 'run', 'lint:js']);
        await run('php-format', php(['vendor/bin/pint', '--format', 'agent']));
        if (sourceInventory(workspace).digest !== summary.source.digest) throw new Error('Formatter changed source; apply the reported formatting to the owned files before repeating verification.');
        await run('static-analysis', composer(['analyse']));
        await run('production-build', ['npm', 'run', 'build']);
        await run('generated-styles', ['npm', 'run', 'styles:check']);
        const discovery = join(artifacts, 'backend-discovery.xml');
        const junit = join(artifacts, 'backend.xml');
        await run('backend-discovery', php(['vendor/bin/pest', '--testsuite=Unit,Feature', '--list-tests-xml', discovery]));
        const strict = ['--fail-on-all-issues', '--display-all-issues'];
        await run('backend', php(['-d', 'memory_limit=512M', '-d', 'error_reporting=-1', 'vendor/bin/pest', '--testsuite=Unit,Feature', '--compact', ...strict, '--log-junit', junit]), { timeout: 1_200_000 });
        const expected = (readFileSync(discovery, 'utf8').match(/<testMethod\s/g) ?? []).length;
        const actual = (readFileSync(junit, 'utf8').match(/<testcase\s/g) ?? []).length;
        if (expected === 0 || expected !== actual) throw new Error(`Backend discovery mismatch: ${expected} discovered, ${actual} executed`);
        summary.backend = { discovered: expected, executed: actual };
        await run('js-tests-coverage', ['npm', 'run', 'test:js:coverage']);
        if (!experimental) {
            const coverage = values['coverage-extension'] ? coverageEnvironment(environment, artifacts, values['coverage-extension']) : {};
            await run('php-coverage', composer(['test:coverage', '--', '-d', 'memory_limit=4G', '--coverage-clover', join(artifacts, 'php-coverage.xml'), ...strict]), { timeout: 1_800_000, env: coverage });
        } else {
            summary.coverage = 'Not measured on experimental PHP; the stable runtime must independently pass the unchanged 90% gate.';
        }
        await run('browser', php(['tests/browser.php']), { timeout: 3_600_000 });
        await run('translations-audit', php(['artisan', 'translations:audit', '--no-interaction']));
        await run('translations-scan', php(['artisan', 'translations:scan', '--no-interaction']));
        await run('asset-budgets', ['npm', 'run', 'build:check']);
    }
    if (sourceInventory(sourceRoot).digest !== summary.source.digest) throw new Error('Working source changed during verification; this result is only evidence for the recorded snapshot.');
    if (sourceInventory(workspace).digest !== summary.source.digest) throw new Error('Snapshot source changed during verification.');
    if ([sourceRoot, workspace].some(root => assertDependencyIntegrity(root).digest !== summary.dependencies.digest)) throw new Error('Installed dependency metadata changed during verification.');
    summary.status = values.preflight ? 'preflight-passed-not-full-verification' : 'passed';
    record();
    console.log(`Migration ${summary.status}. Artifacts: ${artifacts}`);
} catch (error) {
    summary.error = error.message;
    if (artifacts) writeFileSync(join(artifacts, 'summary.json'), JSON.stringify(summary, null, 2)+'\n');
    console.error(`${error.message}\nMigration verification incomplete.${artifacts ? ` Artifacts: ${artifacts}` : ''}`);
    process.exitCode = 1;
}
