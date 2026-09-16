<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

test('the local Flux Pro fork accounts for every source file and accepted patch', function (): void {
    $directory = dirname(__DIR__, 2).'/packages/livewire/flux-pro';
    $provenance = json_decode(file_get_contents($directory.'.provenance.json'), true, flags: JSON_THROW_ON_ERROR);
    $inventory = fluxProDistributionInventory($directory);
    $sourceFiles = $provenance['source_files'] ?? [];

    expect($sourceFiles)->toHaveCount(139)
        ->and(array_sum(array_column($sourceFiles, 'bytes')))->toBe(3291708)
        ->and(fluxProDistributionDigest($sourceFiles))->toBe('da0fcdf6baf21b0f747cae8fa804cfe45bcc7e1ac9e6bb101c9448695b5790c5')
        ->and($provenance['source_snapshot'])->toBe([
            'kind' => 'user-provided-snapshot',
            'directory' => 'flux-pro',
            'repository_commit' => 'eb3fa3d75c42774c4203fa8be6bde3bf944a631e',
            'file_count' => 139,
            'total_bytes' => 3291708,
            'inventory_sha256' => 'b64a3c813819e85a414a010f68bb13d23ed7dcda76668fb5e52cf0388330ad9f',
            'inventory_format' => 'UTF-8; lexicographic path-component order; path TAB bytes TAB sha256 LF',
            'canonical_inventory_sha256' => fluxProDistributionDigest($sourceFiles),
            'canonical_inventory_format' => 'UTF-8; bytewise full-path order; path TAB bytes TAB sha256 LF',
            'excluded_finder_metadata' => [
                '.DS_Store',
                'stubs/.DS_Store',
                'stubs/resources/.DS_Store',
                'stubs/resources/views/.DS_Store',
            ],
        ])
        ->and($inventory)->toHaveCount(140)->toBe($provenance['files'])
        ->and($provenance['internal_snapshot'])->toBe([
            'file_count' => count($inventory),
            'total_bytes' => array_sum(array_column($inventory, 'bytes')),
            'inventory_sha256' => fluxProDistributionDigest($inventory),
            'inventory_format' => 'UTF-8; bytewise full-path order; path TAB bytes TAB sha256 LF',
        ])
        ->and(fluxProDistributionChecksums(file_get_contents($directory.'.sha256')))
        ->toBe(array_column($inventory, 'sha256', 'path'));

    $patches = array_column($provenance['local_patches'], null, 'path');
    $currentFiles = array_column($inventory, null, 'path');
    $additions = $provenance['local_additions'];

    expect(array_keys($patches))->toBe([
        'composer.json',
        'dist/flux.js',
        'dist/flux.min.js',
        'dist/flux.module.js',
        'dist/manifest.json',
        'src/FluxProServiceProvider.php',
        'stubs/resources/views/flux/calendar/index.blade.php',
        'stubs/resources/views/flux/date-picker/index.blade.php',
        'stubs/resources/views/flux/pillbox/search.blade.php',
        'stubs/resources/views/flux/select/search.blade.php',
    ])->and(array_column($additions, 'path'))->toBe(['build-runtime.mjs']);

    foreach ($sourceFiles as $source) {
        $current = $currentFiles[$source['path']];
        $patch = $patches[$source['path']] ?? null;

        if ($patch === null) {
            expect($current)->toBe($source, $source['path']);

            continue;
        }

        expect($patch['original_sha256'])->toBe($source['sha256'])
            ->and($patch['local_sha256'])->toBe($current['sha256'])
            ->and($patch['local_sha256'])->not->toBe($patch['original_sha256'])
            ->and($patch['reason'])->toBeString()->not->toBeEmpty();
    }

    foreach ($additions as $addition) {
        expect($addition['reason'])->toBeString()->not->toBeEmpty();
        unset($addition['reason']);
        expect($currentFiles[$addition['path']])->toBe($addition);
    }

    $expectedPaths = [...array_column($sourceFiles, 'path'), ...array_column($additions, 'path')];
    sort($expectedPaths, SORT_STRING);
    expect(array_column($inventory, 'path'))->toBe($expectedPaths);
});

test('the local Flux runtime is reproducible and accounts for its installed compatibility donor', function (): void {
    $root = dirname(__DIR__, 2);
    $directory = $root.'/packages/livewire/flux-pro';
    $provenance = json_decode(file_get_contents($directory.'.provenance.json'), true, flags: JSON_THROW_ON_ERROR);
    $build = $provenance['runtime_build'];
    $donor = $build['compatibility_donor'];

    expect($build['kind'])->toBe('project-local; not an upstream build reconstruction')
        ->and($build['source'])->toBe('dist/flux.module.js')
        ->and($build['minifier']['package'])->toBe('rolldown')
        ->and($build['minifier']['version'])->toBe('1.2.5')
        ->and($donor['package'])->toBe('livewire/flux')
        ->and($donor['version'])->toBe('2.17.0')
        ->and($donor['license'])->toBe('proprietary')
        ->and($donor['path'])->toBe('vendor/livewire/flux/dist/flux-lite.min.js')
        ->and(hash_file('sha256', $root.'/'.$donor['path']))->toBe($donor['sha256']);

    $process = new Process(['node', $directory.'/build-runtime.mjs', '--check'], $root);
    expect($process->run())->toBe(0, $process->getErrorOutput().$process->getOutput());
});

test('the local Flux Pro release preserves its license and truthful upstream identity', function (): void {
    $root = dirname(__DIR__, 2);
    $directory = $root.'/packages/livewire/flux-pro';
    $package = json_decode(file_get_contents($directory.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $provenance = json_decode(file_get_contents($directory.'.provenance.json'), true, flags: JSON_THROW_ON_ERROR);
    $application = json_decode(file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($package['name'])->toBe('livewire/flux-pro')
        ->and($package['license'])->toBe('proprietary')
        ->and($package['version'] ?? null)->toBe('0.1.1')
        ->and($package['description'])->toContain('local fork')
        ->and($package['require']['livewire/flux'])->toBe('2.17.0')
        ->and($package)->not->toHaveKeys(['provide', 'replace'])
        ->and($package['extra']['laravel']['aliases'])->toBe(['FluxPro' => 'FluxPro\\FluxPro'])
        ->and(hash_file('sha256', $directory.'/LICENSE.md'))
        ->toBe('db157f8fe2a5f9dc30ce4104b1caffe619920c4a81038a585d1e1ff0df648870')
        ->and($provenance['schema_version'])->toBe(2)
        ->and($provenance['package'])->toBe($package['name'])
        ->and($provenance['status'])->toBe('local-fork')
        ->and($provenance['upstream_version'])->toBeNull()
        ->and($provenance['upstream_pristine_verified'])->toBeFalse()
        ->and($provenance['local_release']['version'])->toBe('0.1.1')
        ->and($provenance['local_release']['version_scope'])->toBe('project-local; not an upstream release')
        ->and($provenance['compatibility']['original_flux_requirement'])->toBe('2.13.1|dev-main')
        ->and($provenance['compatibility']['local_flux_requirement'])->toBe('2.17.0')
        ->and($application['require']['livewire/flux-pro'] ?? null)->toBe('0.1.1')
        ->and($application['minimum-stability'])->toBe('stable')
        ->and($application['prefer-stable'])->toBeTrue()
        ->and($application['repositories'])->toBe([
            [
                'type' => 'path',
                'url' => 'packages/livewire/flux-pro',
                'options' => ['symlink' => false],
            ],
        ]);
});

/**
 * @param  list<array{path: string, bytes: int, sha256: string}>  $inventory
 */
function fluxProDistributionDigest(array $inventory): string
{
    return hash('sha256', implode('', array_map(
        fn (array $file): string => $file['path']."\t".$file['bytes']."\t".$file['sha256']."\n",
        $inventory,
    )));
}

test('Pint excludes only the two root-relative Flux Pro distribution directories', function (): void {
    $root = dirname(__DIR__, 2);
    $directory = sys_get_temp_dir().'/restaurant-flux-pro-pint-'.bin2hex(random_bytes(8));
    $original = "<?php\n\n\$value=1;\n";
    $excluded = [
        'flux-pro/Fixture.php',
        'flux-pro/src/Nested.php',
        'packages/livewire/flux-pro/Fixture.php',
        'packages/livewire/flux-pro/src/Nested.php',
    ];
    $included = [
        'app/Fixture.php',
        'app/flux-pro/Fixture.php',
        'app/packages/livewire/flux-pro/Fixture.php',
        'flux-pro-tools/Fixture.php',
        'packages/livewire/flux-pro-tools/Fixture.php',
    ];
    mkdir($directory, 0700);

    try {
        foreach ([...$excluded, ...$included] as $path) {
            $parent = dirname($directory.'/'.$path);

            if (! is_dir($parent)) {
                mkdir($parent, 0700, true);
            }

            file_put_contents($directory.'/'.$path, $original);
        }

        $process = new Process([
            PHP_BINARY,
            $root.'/vendor/bin/pint',
            '--config='.$root.'/pint.json',
            '--cache-file='.$directory.'/pint.cache',
            '--format=agent',
            '--no-interaction',
        ], $directory);

        expect($process->run())->toBe(0, $process->getErrorOutput().$process->getOutput());

        foreach ($excluded as $path) {
            expect(file_get_contents($directory.'/'.$path))->toBe($original, $path);
        }

        foreach ($included as $path) {
            expect(file_get_contents($directory.'/'.$path))->toContain('$value = 1;');
        }
    } finally {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if ($file->isDir() && ! $file->isLink()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($directory);
    }
});

test('the Flux Pro checksum reader rejects unsafe and noncanonical paths', function (string $path): void {
    $manifest = str_repeat('a', 64).'  '.$path."\n";

    expect(fn (): array => fluxProDistributionChecksums($manifest))->toThrow(UnexpectedValueException::class);
})->with([
    'parent traversal' => '../LICENSE.md',
    'nested traversal' => 'src/../LICENSE.md',
    'absolute path' => '/LICENSE.md',
    'Windows path' => 'C:\\LICENSE.md',
    'dot segment' => './LICENSE.md',
    'empty segment' => 'src//helpers.php',
    'control character' => "src/\thelpers.php",
]);

test('the Flux Pro checksum reader rejects duplicate and unsorted entries', function (array $paths): void {
    $manifest = implode('', array_map(fn (string $path): string => str_repeat('a', 64).'  '.$path."\n", $paths));

    expect(fn (): array => fluxProDistributionChecksums($manifest))->toThrow(UnexpectedValueException::class);
})->with([
    'duplicate file' => [['LICENSE.md', 'LICENSE.md']],
    'unsorted files' => [['src/helpers.php', 'LICENSE.md']],
]);

test('the Flux Pro inventory rejects file and directory symlinks', function (bool $directoryLink): void {
    $directory = sys_get_temp_dir().'/restaurant-flux-pro-integrity-'.bin2hex(random_bytes(8));
    mkdir($directory, 0700);
    file_put_contents($directory.'/original', 'snapshot');
    symlink($directoryLink ? $directory : $directory.'/original', $directory.'/link');

    try {
        expect(fn (): array => fluxProDistributionInventory($directory))->toThrow(UnexpectedValueException::class);
    } finally {
        unlink($directory.'/link');
        unlink($directory.'/original');
        rmdir($directory);
    }
})->with(['file' => false, 'directory' => true]);

/**
 * @return list<array{path: string, bytes: int, sha256: string}>
 */
function fluxProDistributionInventory(string $directory): array
{
    if (is_link($directory)) {
        throw new UnexpectedValueException('The distribution root must not be a symlink.');
    }

    $inventory = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );

    foreach ($iterator as $file) {
        if ($file->isLink()) {
            throw new UnexpectedValueException('Distribution symlinks are forbidden.');
        }

        if ($file->isDir()) {
            continue;
        }

        if (! $file->isFile()) {
            throw new UnexpectedValueException('The distribution contains a non-regular file.');
        }

        $path = substr($file->getPathname(), strlen($directory) + 1);
        $inventory[$path] = [
            'path' => $path,
            'bytes' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getPathname()),
        ];
    }

    ksort($inventory, SORT_STRING);

    return array_values($inventory);
}

/**
 * @return array<string, string>
 */
function fluxProDistributionChecksums(string $manifest): array
{
    if ($manifest === '' || ! str_ends_with($manifest, "\n")) {
        throw new UnexpectedValueException('The checksum manifest must end with LF.');
    }

    $checksums = [];

    foreach (explode("\n", substr($manifest, 0, -1)) as $line) {
        if (preg_match('/\A([a-f0-9]{64})  ([A-Za-z0-9_.-]+(?:\/[A-Za-z0-9_.-]+)*)\z/', $line, $matches) !== 1) {
            throw new UnexpectedValueException('Invalid checksum line or relative path.');
        }

        $path = $matches[2];
        $segments = explode('/', $path);

        if (in_array('.', $segments, true) || in_array('..', $segments, true) || isset($checksums[$path])) {
            throw new UnexpectedValueException('Unsafe or duplicate checksum path.');
        }

        $checksums[$path] = $matches[1];
    }

    $sorted = $checksums;
    ksort($sorted, SORT_STRING);

    if ($checksums !== $sorted) {
        throw new UnexpectedValueException('Checksum paths must use bytewise order.');
    }

    return $checksums;
}
