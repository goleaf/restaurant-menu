<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;

test('waiter can persist notification sound preference in the browser', function () {
    $this->withVite();
    $this->seed(SystemPermissionsSeeder::class);

    $waiter = User::factory()->create([
        'email' => 'waiter-sounds@example.test',
        'password' => 'password',
    ]);
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Sound Test Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Sound Test Brand']);
    Branch::factory()->for($organization)->for($brand)->create(['name' => 'Sound Test Branch']);

    $waiterRole = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    $viewOrders = Permission::query()->where('code', SystemPermission::ViewOrders->value)->firstOrFail();
    $waiterRole->permissions()->updateExistingPivot($viewOrders->id, ['enabled' => true]);
    $organization->users()->syncWithoutDetachingOrFail([
        $waiter->id => [
            'role_id' => $waiterRole->id,
            'status' => OrganizationUserStatus::Active->value,
            'joined_at' => now(),
            'invited_by_user_id' => null,
        ],
    ]);

    $page = visit(route('login', absolute: false));

    $page
        ->fill('email', 'waiter-sounds@example.test')
        ->fill('password', 'password')
        ->click('@login-button')
        ->navigate(route('restaurant.waiter.dashboard', absolute: false))
        ->assertSee(__('ui.waiter.dashboard.enable_sounds'))
        ->assertNoJavaScriptErrors();

    $disabledState = $page->script(<<<'JAVASCRIPT'
        (() => {
            const toggle = document.querySelector('[data-waiter-sound-toggle]');

            return {
                audioContextType: typeof window.AudioContext,
                disabled: toggle?.disabled,
                pressed: toggle?.getAttribute('aria-pressed'),
                ready: document.querySelector('[data-waiter-sounds]')?.getAttribute('data-waiter-sounds-ready'),
                stored: window.localStorage.getItem('restaurant-menu:waiter-sounds-enabled'),
            };
        })()
    JAVASCRIPT);

    expect($disabledState)->toBe([
        'audioContextType' => 'function',
        'disabled' => false,
        'pressed' => 'false',
        'ready' => 'true',
        'stored' => null,
    ]);

    $page->click('[data-waiter-sound-toggle]');

    $enabledState = $page->script(<<<'JAVASCRIPT'
        (() => ({
            pressed: document.querySelector('[data-waiter-sound-toggle]')?.getAttribute('aria-pressed'),
            stored: window.localStorage.getItem('restaurant-menu:waiter-sounds-enabled'),
        }))()
    JAVASCRIPT);

    expect($enabledState)->toBe([
        'pressed' => 'true',
        'stored' => 'true',
    ]);

    $page
        ->assertSee(__('ui.waiter.dashboard.disable_sounds'))
        ->assertSee(__('ui.waiter.dashboard.sounds_enabled'));

    $page->script(<<<'JAVASCRIPT'
        (() => {
            window.dispatchEvent(new CustomEvent('waiter-new-draft'));
            window.dispatchEvent(new CustomEvent('waiter-called'));
            window.dispatchEvent(new CustomEvent('waiter-bill-requested'));
            window.dispatchEvent(new CustomEvent('waiter-item-ready'));
        })()
    JAVASCRIPT);

    $page
        ->script('new Promise((resolve) => window.setTimeout(resolve, 1200))');

    expect($page->script("document.querySelector('[data-waiter-sound-toggle]')?.getAttribute('aria-pressed')"))
        ->toBe('true');

    $page
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();

    $page
        ->navigate(route('restaurant.waiter.dashboard', absolute: false))
        ->assertNoJavaScriptErrors();

    expect($page->script("document.querySelector('[data-waiter-sound-toggle]')?.getAttribute('aria-pressed')"))
        ->toBe('true');
});
