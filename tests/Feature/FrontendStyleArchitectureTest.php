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
        ->toContain(":root,\n    body[data-layout='print']")
        ->toContain('background: var(--qr-paper) !important;')
        ->toContain('break-inside: avoid;');
});

test('unused generic control clones are not published alongside Flux', function (): void {
    foreach (['button', 'alert', 'form-field', 'table-row', 'validation-error', 'form-input', 'select', 'textarea', 'primary-button', 'secondary-button', 'danger-button', 'confirmation-modal'] as $component) {
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

test('generic control wrappers have no first party callers or backing classes', function (): void {
    $views = collect(File::allFiles(resource_path('views')))
        ->map(fn (SplFileInfo $file): string => $file->getContents())->implode("\n");

    expect($views)->not->toMatch('/<x-ui\.(button|danger-button|alert|form-input|form-field|table-row|validation-error)(?=[\s\/>])/');

    foreach (['Button', 'Alert', 'Card', 'TableRow', 'ValidationError'] as $component) {
        expect(File::exists(app_path('View/Components/Ui/'.$component.'.php')))->toBeFalse();
    }
});

test('Flux component attributes do not silently discard repeated class values', function (): void {
    $invalidFiles = [];

    foreach (File::allFiles(resource_path('views')) as $file) {
        preg_match_all('/<flux:[\w.:-]+\b(?:"[^"]*"|\'[^\']*\'|[^\'">])*>/s', $file->getContents(), $tags);

        foreach ($tags[0] as $tag) {
            if (preg_match_all('/\sclass\s*=/', $tag) > 1) {
                $invalidFiles[] = $file->getRelativePathname();
            }
        }
    }

    expect($invalidFiles)->toBeEmpty();
});

test('frontend dependencies do not introduce a second CSS preprocessor', function (): void {
    $package = json_decode(File::get(base_path('package.json')), true, flags: JSON_THROW_ON_ERROR);
    $dependencies = array_keys(array_merge($package['dependencies'] ?? [], $package['devDependencies'] ?? []));

    expect(array_intersect($dependencies, ['sass', 'sass-embedded', 'node-sass', 'less', 'stylus', 'autoprefixer', 'postcss-import']))->toBeEmpty();
});

test('pending photo controls keep their ephemeral disabled state in Alpine', function (): void {
    view()->share('errors', new ViewErrorBag);
    $item = ['id' => 1, 'image_count' => 0, 'max_image_count' => 8, 'remaining_image_slots' => 8, 'images' => []];
    $html = Blade::render('<x-menu.item-images :item="$item" :pending-uploads="[]" />', compact('item'));

    expect($html)->toContain('x-bind:disabled="busy"')
        ->toContain('remove(preview.key)')
        ->toContain(__('uploads.editor.remove_pending'));
});

test('production styles load directly without redundant CSS preloads during navigation', function (): void {
    $this->withVite();
    $response = $this->get(route('login'))->assertOk();
    $dom = HTMLDocument::createFromString($response->getContent());

    expect($dom->querySelectorAll('link[rel="stylesheet"]')->length)->toBeGreaterThanOrEqual(1)
        ->and($dom->querySelectorAll('link[rel="preload"][as="style"]')->length)->toBe(0)
        ->and($dom->querySelectorAll('link[rel="modulepreload"]')->length)->toBeGreaterThanOrEqual(1);
});

test('product state panels preserve announcements while composing Flux', function (string $kind, string $role): void {
    $html = Blade::render('<x-ui.state-panel :kind="$kind" title="ui.empty.no_results" description="ui.empty.no_service_points"><x-slot:actions><flux:button>Retry</flux:button></x-slot:actions></x-ui.state-panel>', compact('kind'));
    $dom = HTMLDocument::createFromString('<!doctype html><html><body>'.$html.'</body></html>');
    $panel = $dom->querySelector('[data-state]');

    expect($panel->hasAttribute('data-flux-callout'))->toBeTrue()
        ->and($panel->getAttribute('role'))->toBe($role)
        ->and($panel->getAttribute('data-state'))->toBe($kind)
        ->and($panel->hasAttribute('aria-busy'))->toBe($kind === 'loading')
        ->and($dom->querySelector('[data-flux-skeleton]') !== null)->toBe($kind === 'loading')
        ->and($html)->toContain('Retry')->toContain(__('ui.empty.no_results'));

    if ($role === 'status') {
        expect($panel->getAttribute('aria-live'))->toBe('polite');
    }
})->with([
    ['empty', 'status'], ['filtered-empty', 'status'], ['loading', 'status'], ['slow', 'status'],
    ['offline', 'status'], ['stale', 'status'], ['validation', 'alert'], ['unauthorized', 'status'],
    ['error', 'alert'], ['fatal', 'alert'],
]);

test('dangerous confirmation initially focuses only the safe cancel action', function (string $locale): void {
    app()->setLocale($locale);
    view()->share('errors', new ViewErrorBag);
    $html = Blade::render(<<<'BLADE'
        <x-dangerous-action-confirmation name="safe-focus" confirm-action="remove" reason-model="reason" confirmation-model="confirmation" confirmation-text="DELETE">
            <x-slot:trigger><flux:button>{{ __('ui.actions.delete') }}</flux:button></x-slot:trigger>
        </x-dangerous-action-confirmation>
    BLADE);
    $dom = HTMLDocument::createFromString('<!doctype html><html><body>'.$html.'</body></html>');
    $initialFocus = $dom->querySelectorAll('dialog [autofocus]');

    expect($initialFocus->length)->toBe(1)
        ->and(trim($initialFocus->item(0)->textContent))->toBe(__('ui.actions.cancel'))
        ->and($dom->querySelectorAll('dialog [data-flux-modal-close]')->length)->toBe(1)
        ->and($dom->querySelectorAll('dialog [data-flux-field]')->length)->toBe(2);
})->with(['en', 'lt', 'ru']);

test('Livewire completions close a named component modal rather than every open editor', function (): void {
    foreach (File::allFiles(app_path('Livewire')) as $file) {
        expect($file->getContents())->not->toContain('Flux::modals()->close()');
    }
});
