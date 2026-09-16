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
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Pest\Browser\Api\PendingAwaitablePage;

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
    $branch = Branch::factory()->for($organization)->for($brand)->create(['name' => 'Sound Test Branch']);
    $servicePoint = ServicePoint::factory()->for($branch)->create(['name' => 'Sound Test Table']);
    TableSession::factory()->forServicePoint($servicePoint)->active()->create();

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
        ->resize(390, 844)
        ->navigate(route('restaurant.waiter.dashboard', absolute: false))
        ->assertSee(__('ui.waiter.dashboard.enable_sounds'))
        ->assertNoJavaScriptErrors();

    expect(waiterResponsiveDetailActions($page))->toBe([
        'desktop' => 0,
        'mobile' => 1,
    ]);

    $page
        ->resize(1440, 1000)
        ->navigate(route('restaurant.waiter.dashboard', absolute: false));

    expect(waiterResponsiveDetailActions($page))->toBe([
        'desktop' => 1,
        'mobile' => 0,
    ]);

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

    $page->script(<<<'JAVASCRIPT'
        (() => {
            window.__waiterAudio = { contexts: 0, notes: 0, closed: 0 };
            window.AudioContext = new Proxy(window.AudioContext, {
                construct(target, args) {
                    const context = new target(...args);
                    window.__waiterAudio.contexts++;
                    const createOscillator = context.createOscillator.bind(context);
                    const close = context.close.bind(context);
                    context.createOscillator = () => {
                        window.__waiterAudio.notes++;
                        return createOscillator();
                    };
                    context.close = () => {
                        window.__waiterAudio.closed++;
                        return close();
                    };
                    return context;
                },
            });
        })()
    JAVASCRIPT);

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

    $audioBeforeGuest = $page->script('({...window.__waiterAudio})');
    expect($audioBeforeGuest['contexts'])->toBe(1)
        ->and($audioBeforeGuest['notes'])->toBeGreaterThan(0);
    $guestUrl = json_encode(route('guest.home', absolute: false), JSON_THROW_ON_ERROR);
    $page->script("new Promise(resolve => { document.addEventListener('livewire:navigated', () => resolve(true), { once: true }); Livewire.navigate({$guestUrl}); })");
    $page->assertPathIs('/guest');
    $page->script("['waiter-new-draft', 'waiter-called', 'waiter-bill-requested', 'waiter-item-ready'].forEach(name => window.dispatchEvent(new CustomEvent(name)))");
    $guestAudio = $page->script('({...window.__waiterAudio})');
    expect($guestAudio['contexts'])->toBe($audioBeforeGuest['contexts'])
        ->and($guestAudio['notes'])->toBe($audioBeforeGuest['notes'])
        ->and($guestAudio['closed'])->toBe(1);

    $page->script("new Promise(resolve => { document.addEventListener('livewire:navigated', () => resolve(true), { once: true }); history.back(); })");
    $page->assertAttribute('[data-waiter-sounds]', 'data-waiter-sounds-ready', 'true')
        ->assertAttribute('[data-waiter-sound-toggle]', 'aria-pressed', 'true')
        ->click('[data-waiter-sound-test]');
    expect($page->script('window.__waiterAudio.contexts'))->toBe(2);

    $page
        ->navigate(route('restaurant.waiter.dashboard', absolute: false))
        ->assertNoJavaScriptErrors();

    expect($page->script("document.querySelector('[data-waiter-sound-toggle]')?.getAttribute('aria-pressed')"))
        ->toBe('true');
});

/**
 * @return array{desktop: int, mobile: int}
 */
function waiterResponsiveDetailActions(PendingAwaitablePage $page): array
{
    return $page->script(<<<'JAVASCRIPT'
        (() => {
            const isVisible = (element) => {
                const style = getComputedStyle(element);
                const rectangle = element.getBoundingClientRect();

                return style.display !== 'none'
                    && style.visibility !== 'hidden'
                    && rectangle.width > 0
                    && rectangle.height > 0;
            };

            return {
                desktop: [...document.querySelectorAll('[data-waiter-desktop-select]')].filter(isVisible).length,
                mobile: [...document.querySelectorAll('[data-waiter-mobile-detail]')].filter(isVisible).length,
            };
        })()
    JAVASCRIPT);
}
