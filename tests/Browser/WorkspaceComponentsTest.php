<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;

test('workspace navigation and local component states retain keyboard focus and independent drafts', function (): void {
    $this->withVite();
    $this->app->instance('env', 'local');
    $this->seed(SystemPermissionsSeeder::class);
    $user = User::factory()->create(['email' => 'components@example.test', 'password' => 'password']);
    $user->roles()->attach(Role::query()->where('code', SystemRole::Superadmin->value)->firstOrFail());
    $page = visit(route('login', absolute: false));
    $page->fill('email', $user->email)->fill('password', 'password')->click('@login-button')
        ->navigate(route('local.components', absolute: false))->resize(1440, 1000)
        ->assertPresent('[data-component-reference]');

    $page->script("window.Flux.appearance = 'light'");
    $page->assertScript("document.documentElement.classList.contains('dark')", false);
    $contrast = $page->script(<<<'JS'
        (() => {
            const error = document.querySelector('#reference-invalid-error');
            const context = document.createElement('canvas').getContext('2d');
            const luminance = (color) => {
                context.fillStyle = color;
                context.fillRect(0, 0, 1, 1);
                const rgb = [...context.getImageData(0, 0, 1, 1).data].slice(0, 3)
                    .map(value => value / 255)
                    .map(value => value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4);
                return rgb[0] * 0.2126 + rgb[1] * 0.7152 + rgb[2] * 0.0722;
            };
            const foreground = luminance(getComputedStyle(error).color);
            const background = luminance(getComputedStyle(error.closest('[data-flux-card]')).backgroundColor);
            return (Math.max(foreground, background) + 0.05) / (Math.min(foreground, background) + 0.05);
        })()
        JS);
    expect($contrast)->toBeGreaterThanOrEqual(4.5);

    $page->click('[data-flux-sidebar-toggle]');
    $page->assertAttribute('[data-flux-sidebar]', 'data-flux-sidebar-collapsed-desktop', '');
    $page->click('[data-navigation-search-trigger]');
    $page->assertVisible('dialog[data-modal="workspace-navigation"]');
    $page->fill('[data-workspace-search-input]', 'component');
    $page->keys('[data-workspace-search-input]', 'ArrowDown');
    expect($page->script("document.activeElement.getAttribute('data-navigation-search-key')"))->toBe('components');
    $page->keys('[data-workspace-search-input]', 'Escape');
    expect($page->script("document.activeElement.hasAttribute('data-navigation-search-trigger')"))->toBeTrue();

    $page->keys('[data-navigation-search-trigger]', 'Control+k')
        ->assertVisible('dialog[data-modal="workspace-navigation"]')
        ->keys('[data-workspace-search-input]', 'ArrowDown')
        ->assertScript('document.activeElement.dataset.navigationSearchKey', 'dashboard')
        ->keys('[data-navigation-search-key="dashboard"]', 'ArrowDown')
        ->assertScript('document.activeElement.dataset.navigationSearchKey', 'organizations')
        ->keys('[data-navigation-search-key="organizations"]', 'End')
        ->assertScript('document.activeElement.dataset.navigationSearchKey', 'components')
        ->keys('[data-navigation-search-key="components"]', 'Home')
        ->assertScript('document.activeElement.dataset.navigationSearchKey', 'dashboard')
        ->keys('[data-navigation-search-key="dashboard"]', 'ArrowUp')
        ->assertScript('document.activeElement.dataset.navigationSearchKey', 'components');
    $page->assertScript('document.activeElement.tagName', 'A')
        ->assertAttribute('[data-navigation-search-key="components"]', 'href', route('local.components'))
        ->keys('[data-navigation-search-key="components"]', 'Enter')
        ->assertPathIs(route('local.components', absolute: false))
        ->navigate(route('local.components', absolute: false));
    $page->keys('[data-navigation-search-trigger]', 'Meta+k')
        ->assertVisible('dialog[data-modal="workspace-navigation"]')
        ->fill('[data-workspace-search-input]', 'no-matching-workspace')
        ->keys('[data-workspace-search-input]', 'Enter')
        ->assertPathIs(route('local.components', absolute: false))
        ->assertSee(__('navigation.search_empty'))
        ->keys('[data-workspace-search-input]', 'Escape');
    $page->script(<<<'JS'
        (() => {
            const trigger = document.querySelector('[data-navigation-search-trigger]');
            const cancelled = new KeyboardEvent('keydown', { key: 'k', ctrlKey: true, bubbles: true, cancelable: true });
            cancelled.preventDefault();
            trigger.dispatchEvent(cancelled);
            trigger.dispatchEvent(new KeyboardEvent('keydown', { key: 'k', ctrlKey: true, isComposing: true, bubbles: true }));
        })()
        JS);
    $page->assertMissing('dialog[data-modal="workspace-navigation"][open]');
    $page->fill('textarea[name="note"]', 'Keep this independent draft');
    $page->keys('textarea[name="note"]', 'Control+k')
        ->assertMissing('dialog[data-modal="workspace-navigation"][open]')
        ->assertValue('textarea[name="note"]', 'Keep this independent draft');
    $page->click('[data-reference-editor-trigger]');
    $page->assertVisible('dialog[data-modal="component-reference-edit"]');
    expect($page->script('document.activeElement.textContent.trim()'))->toBe(__('ui.actions.cancel'));
    $page->keys('dialog[data-modal="component-reference-edit"] button[autofocus]', 'Meta+k')
        ->assertMissing('dialog[data-modal="workspace-navigation"][open]')
        ->assertVisible('dialog[data-modal="component-reference-edit"]');
    $page->fill('input[name="name"]', '');
    $page->click('dialog[data-modal="component-reference-edit"] button[type="submit"]');
    $page->assertSee(__('ui.reference.name_required'))->assertValue('textarea[name="note"]', 'Keep this independent draft');
    $page->fill('input[name="name"]', 'Vilniaus restoranas ir семейная терраса');
    $page->click('dialog[data-modal="component-reference-edit"] button[type="submit"]');
    $page->assertMissing('dialog[data-modal="component-reference-edit"][open]')
        ->assertSee(__('ui.reference.saved'))->assertValue('textarea[name="note"]', 'Keep this independent draft');

    foreach (['en', 'lt', 'ru'] as $locale) {
        $page->navigate(route('local.components', ['lang' => $locale], false));
        foreach (['light', 'dark'] as $appearance) {
            $page->script("window.Flux.appearance = '{$appearance}'");
            $page->assertScript("document.documentElement.classList.contains('dark')", $appearance === 'dark');
            $controls = $page->script(<<<'JS'
                (() => {
                    const canvas = document.createElement('canvas').getContext('2d');
                    const luminance = color => {
                        canvas.clearRect(0, 0, 1, 1);
                        canvas.fillStyle = color;
                        canvas.fillRect(0, 0, 1, 1);
                        const rgb = [...canvas.getImageData(0, 0, 1, 1).data].slice(0, 3)
                            .map(value => value / 255)
                            .map(value => value <= .04045 ? value / 12.92 : ((value + .055) / 1.055) ** 2.4);
                        return rgb[0] * .2126 + rgb[1] * .7152 + rgb[2] * .0722;
                    };
                    return [...document.querySelectorAll('[data-reference-danger]')].map(button => {
                        const style = getComputedStyle(button);
                        const foreground = luminance(style.color), background = luminance(style.backgroundColor);
                        return { state: button.dataset.referenceDanger, height: button.getBoundingClientRect().height,
                            contrast: (Math.max(foreground, background) + .05) / (Math.min(foreground, background) + .05),
                            disabled: button.disabled };
                    });
                })()
                JS);
            foreach ($controls as $control) {
                expect($control['height'])->toBeGreaterThanOrEqual($control['state'] === 'operational' ? 56 : 44);
                if (! $control['disabled']) {
                    expect($control['contrast'])->toBeGreaterThanOrEqual(4.5);
                }
            }
            $page->assertAttribute('#reference-image-upload', 'aria-describedby', 'reference-image-upload-help');
            foreach ([[320, 800], [390, 844], [768, 900], [1024, 900], [1440, 1000]] as [$width, $height]) {
                $page->resize($width, $height);
                expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
                $page->screenshot(false, "workspace-components-{$locale}-{$appearance}-{$width}");
            }
        }
    }
    $page->resize(390, 844)->script("document.documentElement.style.zoom = '2'");
    $page->screenshot(false, 'workspace-components-zoom');
    $overflow = $page->script("[...document.querySelectorAll('body *')].filter(e => e.getBoundingClientRect().right > innerWidth && e.getBoundingClientRect().width > 0).slice(0, 12).map(e => ({tag:e.tagName, text:e.textContent.trim().slice(0,60), width:e.getBoundingClientRect().width, right:e.getBoundingClientRect().right}))");
    expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue(json_encode($overflow));
    $page->script("document.documentElement.style.zoom = ''");
    $page->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});
