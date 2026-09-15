<?php

declare(strict_types=1);

use Dom\HTMLDocument;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\Finder\Finder;

test('first party styles remain native CSS with explicit Tailwind runtime sources', function (): void {
    $preprocessors = Finder::create()->files()->in(base_path())
        ->exclude(['.git', 'vendor', 'node_modules', 'storage', 'public/build'])
        ->name('/\.(scss|sass|less|styl)$/i');

    expect(iterator_to_array($preprocessors))->toBeEmpty();

    expect(File::get(resource_path('css/app.css')))
        ->toContain("@import 'tailwindcss' source(none);")
        ->toContain("@source '../views';")
        ->toContain("@source '../js';")
        ->toContain("@source '../../app';")
        ->toContain("@source '../../vendor/livewire/flux/stubs/**/*.blade.php';")
        ->toContain("@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';")
        ->toContain('@custom-variant dark (&:where(.dark, .dark *));')
        ->toContain('@theme {');

    foreach (['tailwind.config.js', 'postcss.config.js', 'postcss.config.cjs'] as $legacyConfig) {
        expect(File::exists(base_path($legacyConfig)))->toBeFalse();
    }
});

test('Flux overrides are limited to documented accessibility gaps in the installed Free package', function (): void {
    $overrides = collect(File::allFiles(resource_path('views/flux')))
        ->map(fn (SplFileInfo $file): string => str_replace(resource_path('views/flux').DIRECTORY_SEPARATOR, '', $file->getPathname()))
        ->sort()->values()->all();

    expect($overrides)->toBe(['input/viewable.blade.php', 'toast/index.blade.php']);
});

test('toast dismissal has a translated accessible name and a touch target', function (string $locale): void {
    app()->setLocale($locale);
    $html = Blade::render('<flux:toast />');

    expect($html)->toContain('aria-label="'.e(__('ui.accessibility.dismiss_notification')).'"')
        ->toContain('min-h-touch min-w-touch');
})->with(['en', 'lt', 'ru']);

test('retained accessibility overrides require review when their upstream templates change', function (string $component, string $digest): void {
    $upstream = base_path('vendor/livewire/flux/stubs/resources/views/flux/'.$component.'.blade.php');

    expect(hash_file('sha256', $upstream))->toBe($digest);
})->with([
    'Flux 2.17 password visibility label and pressed state' => ['input/viewable', '05573307e271f31da9850cc2c61334722abb232cb2f8d33c0e5ff15984af590b'],
    'Flux 2.17 toast dismissal label and touch target' => ['toast/index', 'a5af9a21bb1010da29f27bf49483378d876269e6d12520b63592d0039e41def4'],
]);

test('print integration loads only on print layouts and preserves physical QR dimensions', function (): void {
    expect(File::get(resource_path('views/layouts/print.blade.php')))
        ->toContain("@vite('resources/css/qr-print.css')");
    expect(File::get(resource_path('views/partials/head.blade.php')))->not->toContain('qr-print.css');
    expect(File::get(resource_path('css/app.css')))->not->toContain('.qr-sticker');
    expect(File::get(resource_path('css/qr-print.css')))
        ->toContain('--qr-sticker-width: 76mm;')
        ->toContain('--qr-sticker-height: 104mm;')
        ->toContain('print-color-adjust: exact;')
        ->toContain('break-inside: avoid;');
});

test('unused generic control clones are not published alongside Flux', function (): void {
    foreach (['form-input', 'select', 'textarea', 'primary-button', 'secondary-button', 'danger-button', 'confirmation-modal'] as $component) {
        expect(File::exists(resource_path('views/components/ui/'.$component.'.blade.php')))->toBeFalse();
    }
});

test('menu dialogs use the translated close composition through the supported Flux API', function (): void {
    foreach (['index', 'catalog'] as $view) {
        $blade = File::get(resource_path('views/livewire/organizations/brands/branches/menu/'.$view.'.blade.php'));
        preg_match_all('/<flux:modal\s[^>]+>/', $blade, $modals);

        foreach ($modals[0] as $modal) {
            expect($modal)->toContain(':closable="false"');
        }

        expect(substr_count($blade, '<x-modal-close-button :autofocus="true" />'))->toBe(count($modals[0]));
    }
});

test('password visibility exposes its toggle state and translated accessible name', function (string $locale): void {
    app()->setLocale($locale);
    view()->share('errors', new ViewErrorBag);
    $html = Blade::render('<flux:input type="password" name="password" label="Password" viewable />');

    expect($html)
        ->toContain('aria-label="'.e(__('ui.accessibility.toggle_password_visibility')).'"')
        ->toContain('x-bind:aria-pressed="open"')
        ->toContain('x-data="fluxInputViewable"')
        ->toContain('x-on:click="toggle()"');
})->with(['en', 'lt', 'ru']);

test('operational action composition delegates button semantics to Flux', function (): void {
    $html = Blade::render(<<<'BLADE'
        <x-ui.button variant="primary" icon="check" wire:click="approve(4)" full-width>Approve</x-ui.button>
        <x-ui.button href="/restaurant" icon-trailing="arrow-right">Open restaurant</x-ui.button>
        <x-ui.button variant="danger" disabled aria-label="Delete dish">Delete</x-ui.button>
    BLADE);

    expect(HTMLDocument::createFromString('<!doctype html><html><body>'.$html.'</body></html>')->querySelectorAll('[data-flux-button]')->length)->toBe(3)
        ->and($html)
        ->toContain('wire:target="approve(4)"')
        ->toContain('href="/restaurant"')
        ->toContain('aria-label="Delete dish"')
        ->toContain('disabled')
        ->toContain('min-h-touch')
        ->toContain('w-full');
});
