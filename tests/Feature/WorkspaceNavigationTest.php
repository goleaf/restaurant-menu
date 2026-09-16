<?php

use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\User;
use App\Services\Navigation\ApplicationNavigationPresenter;
use Database\Seeders\SystemRolesSeeder;
use Dom\HTMLDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

test('workspace navigation prepares only currently permitted destinations for both presentations', function () {
    $user = User::factory()->create();
    $request = Request::create(route('restaurant.dashboard'));
    $matched = app('router')->getRoutes()->match($request);
    $request->setRouteResolver(fn () => $matched);
    $navigation = app(ApplicationNavigationPresenter::class)->handle($user, $request);
    $items = collect($navigation['navigationItems']);

    expect($items->pluck('key')->all())
        ->toContain('dashboard', 'organizations')
        ->not->toContain('superadmin', 'waiter', 'kitchen', 'bar', 'exports', 'audit_log', 'qr_lookup');
    expect($items->firstWhere('key', 'dashboard'))
        ->toMatchArray(['label' => __('workspace.choose'), 'href' => route('dashboard')]);
    expect($items->pluck('href')->unique())->toHaveCount($items->count());
});

test('workspace navigation does not retain the previous account permissions', function () {
    $this->seed(SystemRolesSeeder::class);
    $superadmin = User::factory()->create();
    $role = Role::query()->where('code', SystemRole::Superadmin->value)->firstOrFail();
    $superadmin->roles()->syncWithoutDetachingOrFail([$role->id]);
    $regular = User::factory()->create();
    $navigation = app(ApplicationNavigationPresenter::class);
    $request = Request::create('/dashboard');

    expect(collect($navigation->handle($superadmin, $request)['navigationItems'])->pluck('key')->all())
        ->toContain('superadmin');
    expect(collect($navigation->handle($regular, $request)['navigationItems'])->pluck('key')->all())
        ->not->toContain('superadmin');
    expect($navigation->handle(null, $request)['navigationItems'])->toBe([]);
});

test('authenticated shell shares navigation search and one notification and account host', function () {
    $this->actingAs(User::factory()->create());
    $html = $this->get(route('restaurant.dashboard'))->assertOk()->getContent();
    $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);

    expect($document->querySelector('ui-sidebar')->getAttribute('collapsible'))->toBe('true');
    expect($document->querySelectorAll('[data-workspace-navigation]'))->toHaveCount(1);
    expect($document->querySelectorAll('[data-modal="workspace-navigation"]'))->toHaveCount(1);
    expect($document->querySelectorAll('[data-account-menu]'))->toHaveCount(1);
    expect($document->querySelector('[data-flux-sidebar-toggle]')->getAttribute('aria-label'))->toBe(__('navigation.toggle_sidebar'));
    expect($document->querySelector('[data-account-menu] a[href$="#profile-interface-language"]'))->not->toBeNull();
    expect($document->querySelectorAll('[data-account-menu] [data-flux-menu-radio]'))->toHaveCount(3);
    expect(substr_count($html, '&quot;name&quot;:&quot;notifications.unread-count&quot;'))->toBe(1);
    $sidebarKeys = array_map(fn ($link) => $link->getAttribute('data-navigation-key'), iterator_to_array($document->querySelectorAll('[data-navigation-key]')));
    $searchKeys = array_map(fn ($link) => $link->getAttribute('data-navigation-search-key'), iterator_to_array($document->querySelectorAll('[data-navigation-search-key]')));
    expect($searchKeys)->toBe($sidebarKeys);
    expect($document->querySelectorAll('[data-search-label]'))->toHaveCount(count($searchKeys));
    expect($html)->not->toContain('persist("sidebar")', 'persist("account")');
});

test('workspace search uses disposable Alpine state without a global navigation subscription', function () {
    $script = File::get(resource_path('js/alpine/components/navigation-search.js'));

    expect($script)
        ->toContain('event.isComposing', 'event.repeat', 'isContentEditable', 'dialog[open]')
        ->not->toContain('localStorage', 'setInterval', "addEventListener('keydown'", "addEventListener('livewire:navigated'");
});

test('local reference has a current navigation marker only in the local administrator workspace', function () {
    $this->seed(SystemRolesSeeder::class);
    $user = User::factory()->create();
    $user->roles()->attach(Role::query()->where('code', SystemRole::Superadmin->value)->firstOrFail());
    $this->app->instance('env', 'local');
    $request = Request::create('/local/components');
    $matched = app('router')->getRoutes()->match($request);
    $request->setRouteResolver(fn () => $matched);

    expect(collect(app(ApplicationNavigationPresenter::class)->handle($user, $request)['navigationItems'])->firstWhere('key', 'components'))
        ->toMatchArray(['current' => true]);
});
