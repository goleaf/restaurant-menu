<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

test('the shared bootstrap registers factories before one Livewire start and owns no stylesheet import', function (): void {
    $bootstrap = File::get(resource_path('js/app.js'));
    expect($bootstrap)->toContain('registerAlpineComponents(Alpine)', 'Livewire.start()')
        ->not->toContain('Alpine.start()', '.scss', 'window.Alpine =');
    expect(substr_count($bootstrap, 'Livewire.start()'))->toBe(1);
    expect(strpos($bootstrap, 'registerAlpineComponents(Alpine)'))->toBeLessThan(strpos($bootstrap, 'Livewire.start()'));
    expect(File::get(resource_path('scss/app.scss')))->toContain("@use 'base/fonts';");
});

test('all layouts configure the bundled runtime without injecting a second Livewire script', function (): void {
    foreach (File::allFiles(resource_path('views/layouts')) as $file) {
        $blade = $file->getContents();
        if (! str_contains($blade, '@fluxScripts')) {
            continue;
        }
        expect($blade)->toContain('@livewireScriptConfig')->not->toContain('@livewireScripts');
    }
    $this->withVite();
    $response = $this->get(route('login'))->assertOk();
    expect($response->getContent())->toContain('window.livewireScriptConfig')
        ->not->toMatch('/<script[^>]*src="[^"]*livewire(?:\.min)?\.js/');
});

test('critical editor roots remain inert until the common registered runtime initializes', function (string $view, string $module): void {
    $blade = File::get(resource_path('views/'.$view.'.blade.php'));
    expect($blade)->toContain('data-page-module="'.$module.'"', 'wire:ignore.self', 'x-ignore', 'inert')
        ->toContain("@pushOnce('page-module-status'", '<x-page-module-status')
        ->not->toContain("@vite('resources/js/");
})->with([
    ['livewire/organizations/brands/branches/menu/index', 'menu'],
    ['livewire/organizations/staff/index', 'staff'],
]);

test('Flux integration does not couple application CSS to vendor color utility classes', function (): void {
    foreach (['[data-flux-badge].text-orange-700', '[data-flux-button].bg-red-500', '[data-loading]'] as $selector) {
        expect(File::get(resource_path('css/app.css')))->not->toContain($selector);
    }
});

test('unavailable editor offers a native reload link without requiring its failed javascript', function (): void {
    $html = view('components.page-module-status', ['module' => 'menu'])->render();

    expect($html)->toMatch('/<a\b[^>]*href=""[^>]*data-page-module-reload/s')
        ->toContain(__('frontend.reload'));
});

test('operational modules expose loading fallback and initially disabled sound controls', function (): void {
    foreach (['waiter', 'departments'] as $module) {
        expect(File::get(resource_path('views/livewire/'.$module.'/dashboard.blade.php')))
            ->toContain("@pushOnce('page-module-status'", '<x-page-module-status');
        expect(File::get(resource_path('views/livewire/'.$module.'/dashboard.blade.php')))->toContain('x-data="'.($module === 'waiter' ? 'waiterSounds' : 'kitchenTimers').'"');
    }

    $blade = File::get(resource_path('views/livewire/waiter/dashboard.blade.php'));
    expect($blade)->toMatch('/<flux:button\b[^>]*data-waiter-sound-toggle[^>]*\bdisabled\b/s')
        ->toMatch('/<flux:button\b[^>]*data-waiter-sound-test[^>]*\bdisabled\b/s');
});
