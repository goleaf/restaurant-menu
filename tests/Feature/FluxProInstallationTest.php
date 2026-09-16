<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Flux\Flux;
use Flux\FluxManager;
use FluxPro\FluxPro;
use FluxPro\FluxProManager;
use FluxPro\FluxProServiceProvider;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Foundation\PackageManifest;
use Illuminate\Support\Facades\Blade;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

test('Composer installs the local Flux Pro release as a physical vendor package', function (): void {
    expect(InstalledVersions::isInstalled('livewire/flux-pro'))->toBeTrue()
        ->and(InstalledVersions::getPrettyVersion('livewire/flux-pro'))->toBe('0.1.1')
        ->and(InstalledVersions::getVersion('livewire/flux'))->toBe('2.17.0.0');

    $directory = InstalledVersions::getInstallPath('livewire/flux-pro');

    expect(realpath($directory))->toBe(realpath(base_path('vendor/livewire/flux-pro')))
        ->and(is_link($directory))->toBeFalse();

    $provenance = json_decode(file_get_contents(base_path('packages/livewire/flux-pro.provenance.json')), true, flags: JSON_THROW_ON_ERROR);
    $paths = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $file) {
        expect($file->isLink())->toBeFalse();

        if ($file->isFile()) {
            $paths[] = substr($file->getPathname(), strlen($directory) + 1);
        }
    }

    sort($paths, SORT_STRING);

    expect($paths)->toBe(array_column($provenance['files'], 'path'));

    foreach ($provenance['files'] as $file) {
        expect(hash_file('sha256', $directory.'/'.$file['path']))->toBe($file['sha256'], $file['path']);
    }
});

test('normal package discovery preserves the Free facade and registers the Pro facade', function (): void {
    expect(app(PackageManifest::class)->providers())->toContain(FluxProServiceProvider::class)
        ->and(app()->getLoadedProviders()[FluxProServiceProvider::class] ?? false)->toBeTrue()
        ->and(AliasLoader::getInstance()->getAliases()['Flux'])->toBe(Flux::class)
        ->and(AliasLoader::getInstance()->getAliases()['FluxPro'])->toBe(FluxPro::class)
        ->and(app('flux'))->toBeInstanceOf(FluxManager::class)
        ->and(app('flux-pro'))->toBeInstanceOf(FluxProManager::class)
        ->and(Flux::getFacadeRoot())->toBe(app('flux'))
        ->and(FluxPro::getFacadeRoot())->toBe(app('flux-pro'))
        ->and(Flux::pro())->toBeTrue()
        ->and(realpath((new ReflectionClass(FluxProServiceProvider::class))->getFileName()))
        ->toBe(realpath(base_path('vendor/livewire/flux-pro/src/FluxProServiceProvider.php')));
});

test('discovered Pro templates render alongside existing Free components', function (): void {
    $html = Blade::render('<flux:accordion><flux:accordion.item heading="Local package">Ready</flux:accordion.item></flux:accordion><flux:button>Continue</flux:button>');

    expect($html)->toContain('data-flux-accordion-item', 'Local package', 'Ready', 'data-flux-button', 'Continue');

    expect(realpath(app('view')->getFinder()->find(hash('xxh128', 'flux').'::accordion.index')))
        ->toBe(realpath(base_path('vendor/livewire/flux-pro/stubs/resources/views/flux/accordion/index.blade.php')));
});

test('Flux serves the preserved installed Pro asset', function (string $asset, string $contentType): void {
    $response = $this->get('/flux/'.$asset)->assertOk()->assertHeader('Content-Type', $contentType);

    expect($response->baseResponse)->toBeInstanceOf(BinaryFileResponse::class)
        ->and(realpath($response->baseResponse->getFile()->getPathname()))
        ->toBe(realpath(base_path('vendor/livewire/flux-pro/dist/'.$asset)))
        ->and(hash_file('sha256', $response->baseResponse->getFile()->getPathname()))
        ->toBe(hash_file('sha256', base_path('packages/livewire/flux-pro/dist/'.$asset)));
})->with([
    'development runtime' => ['flux.js', 'text/javascript; charset=utf-8'],
    'production runtime' => ['flux.min.js', 'text/javascript; charset=utf-8'],
    'editor styles' => ['editor.css', 'text/css; charset=utf-8'],
    'development editor' => ['editor.js', 'text/javascript; charset=utf-8'],
    'production editor' => ['editor.min.js', 'text/javascript; charset=utf-8'],
]);
