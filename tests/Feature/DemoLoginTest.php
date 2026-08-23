<?php

declare(strict_types=1);

use App\Actions\Auth\BuildDemoLoginPageAction;
use App\Actions\Auth\LoginAsDemoRoleAction;
use App\Enums\SystemRole;
use App\Http\Middleware\EnsureDemoLoginIsEnabled;
use App\Models\Role;
use App\Models\User;
use App\Support\DemoLogin\DemoAccountCatalog;
use Database\Factories\UserFactory;
use Database\Seeders\SystemRolesSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::middleware(['web', EnsureDemoLoginIsEnabled::class])
        ->get('/__demo-login-probe', fn () => response('demo-enabled'));
});

test('demo login is hidden when it is disabled', function (): void {
    config()->set('demo-login.enabled', false);

    $this->get('/__demo-login-probe')->assertNotFound();
});

test('demo login is hidden in production even when it is enabled', function (): void {
    config()->set('demo-login.enabled', true);
    $this->app->detectEnvironment(fn (): string => 'production');

    $this->get('/__demo-login-probe')->assertNotFound();
});

test('demo login is available when it is enabled outside production', function (): void {
    config()->set('demo-login.enabled', true);

    $this->get('/__demo-login-probe')
        ->assertOk()
        ->assertSeeText('demo-enabled');
});

test('page data lists every role in canonical order with two bounded queries', function (): void {
    createDemoLoginAccount(SystemRole::Waiter);
    createDemoLoginAccount(SystemRole::Cook, SystemRole::Waiter);

    $accounts = [];
    $queryCount = countDatabaseQueries(function () use (&$accounts): void {
        $accounts = app(BuildDemoLoginPageAction::class)->handle();
    });

    expect($queryCount)->toBe(2)
        ->and(array_column($accounts, 'role'))->toBe(SystemRole::values())
        ->and(array_column($accounts, 'available'))->toBe([
            false,
            false,
            false,
            false,
            false,
            true,
            false,
            false,
            false,
            false,
            false,
            false,
        ]);
});

test('every seeded demo role may be authenticated with a regenerated session', function (SystemRole $role): void {
    $user = createDemoLoginAccount($role);
    $request = Request::create('/demo-login/'.$role->value, 'POST');
    $session = app('session')->driver();
    $session->start();
    $request->setLaravelSession($session);
    $previousSessionId = $session->getId();

    $authenticated = app(LoginAsDemoRoleAction::class)->handle($request, $role);

    expect($authenticated)->toBeTrue()
        ->and(Auth::guard('web')->id())->toBe($user->id)
        ->and($session->getId())->not->toBe($previousSessionId);
})->with(SystemRole::cases());

test('missing or mismatched demo identities are not authenticated', function (): void {
    $request = Request::create('/demo-login/waiter', 'POST');
    $session = app('session')->driver();
    $session->start();
    $request->setLaravelSession($session);
    $loginAsDemoRole = app(LoginAsDemoRoleAction::class);

    expect($loginAsDemoRole->handle($request, SystemRole::Waiter))->toBeFalse()
        ->and(Auth::guard('web')->check())->toBeFalse();

    createDemoLoginAccount(SystemRole::Waiter, SystemRole::Cook);

    expect($loginAsDemoRole->handle($request, SystemRole::Waiter))->toBeFalse()
        ->and(Auth::guard('web')->check())->toBeFalse();
});

test('disabled and production demo login routes are hidden', function (): void {
    config()->set('demo-login.enabled', false);

    foreach (range(1, 21) as $attempt) {
        $this->get('/demo-login')->assertNotFound();
    }

    $this->post('/demo-login/waiter')->assertNotFound();

    config()->set('demo-login.enabled', true);
    $this->app->detectEnvironment(fn (): string => 'production');

    foreach (range(1, 21) as $attempt) {
        $this->get('/demo-login')->assertNotFound();
    }

    $this->post('/demo-login/waiter')->assertNotFound();
});

test('enabled non-production demo login post retains csrf protection', function (): void {
    config()->set('demo-login.enabled', true);
    $this->app->detectEnvironment(fn (): string => 'demo');

    $this->post(route('demo-login.authenticate', ['role' => SystemRole::Waiter->value]))
        ->assertStatus(419);

    $this->assertGuest();
});

test('demo middleware priority preserves csrf before authenticated web routes', function (): void {
    $this->app->detectEnvironment(fn (): string => 'demo');

    $this->post(route('logout'))->assertStatus(419);

    $this->assertGuest();
});

test('enabled demo login page lists all roles without exposing the password', function (): void {
    config()->set('demo-login.enabled', true);
    createDemoLoginAccount(SystemRole::Waiter);

    $roleLabels = array_map(
        static fn (SystemRole $role): string => $role->localizedLabel(),
        SystemRole::cases(),
    );

    $this->get(route('demo-login.index'))
        ->assertOk()
        ->assertHeaderContains('Cache-Control', 'no-store')
        ->assertHeaderContains('Cache-Control', 'private')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSeeTextInOrder($roleLabels)
        ->assertSeeText('waiter@demo.test')
        ->assertSeeText('cook@demo.test')
        ->assertSee('disabled', escape: false)
        ->assertDontSee(UserFactory::DEMO_PASSWORD);
});

test('every seeded demo role may log in through the demo route', function (SystemRole $role): void {
    config()->set('demo-login.enabled', true);
    $user = createDemoLoginAccount($role);

    $this->post(route('demo-login.authenticate', ['role' => $role->value]))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
})->with(SystemRole::cases());

test('invalid missing and mismatched demo roles are rejected', function (): void {
    config()->set('demo-login.enabled', true);

    $this->post('/demo-login/not-a-role')->assertNotFound();

    $this->post(route('demo-login.authenticate', ['role' => SystemRole::Waiter->value]))
        ->assertRedirect(route('demo-login.index'))
        ->assertSessionHasErrors(['demo_login' => __('demo_login.unavailable_error')]);

    $this->assertGuest();

    createDemoLoginAccount(SystemRole::Waiter, SystemRole::Cook);

    $this->post(route('demo-login.authenticate', ['role' => SystemRole::Waiter->value]))
        ->assertRedirect(route('demo-login.index'))
        ->assertSessionHasErrors(['demo_login' => __('demo_login.unavailable_error')]);

    $this->assertGuest();
});

test('authenticated users cannot switch through demo login', function (): void {
    config()->set('demo-login.enabled', true);
    $currentUser = User::factory()->create();
    createDemoLoginAccount(SystemRole::Waiter);

    $this->actingAs($currentUser)
        ->post(route('demo-login.authenticate', ['role' => SystemRole::Waiter->value]))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($currentUser);
});

test('demo login page is rate limited', function (): void {
    config()->set('demo-login.enabled', true);

    foreach (range(1, 20) as $attempt) {
        $this->get(route('demo-login.index'))->assertOk();
    }

    $this->get(route('demo-login.index'))->assertTooManyRequests();
});

function createDemoLoginAccount(SystemRole $identityRole, ?SystemRole $assignedRole = null): User
{
    test()->seed(SystemRolesSeeder::class);

    $identity = DemoAccountCatalog::forRole($identityRole);
    $user = User::factory()->demoIdentity($identity['name'], $identity['email'])->create();
    $role = Role::query()
        ->select(['id', 'code'])
        ->where('code', ($assignedRole ?? $identityRole)->value)
        ->firstOrFail();

    $user->roles()->sync([$role->id]);

    return $user;
}
