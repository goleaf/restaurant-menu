<?php

declare(strict_types=1);

use App\Actions\Auth\BuildDemoLoginPageAction;
use App\Actions\Auth\LoginAsDemoRoleAction;
use App\Enums\SystemRole;
use App\Http\Middleware\EnsureDemoLoginIsEnabled;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\DemoRoleUserQuery;
use App\Support\DemoLogin\DemoAccountCatalog;
use Database\Seeders\SystemRolesSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    config()->set('demo-login.allowed_hosts', [
        'localhost',
        'www.example.com',
        'restaurant-menu.test',
        'ruflo.test',
    ]);

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

test('demo login is available only on an explicitly allowed host', function (): void {
    config()->set('demo-login.enabled', true);
    config()->set('demo-login.allowed_hosts', ['ruflo.test']);

    $this->get('https://restaurant-menu.test/__demo-login-probe')
        ->assertNotFound();

    $this->get('https://ruflo.test/__demo-login-probe')
        ->assertOk();
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
    config()->set('demo-login.enabled', true);
    $user = createDemoLoginAccount($role);
    $request = Request::create('/demo-login/'.$role->value, 'POST');
    $session = app('session')->driver();
    $session->start();
    $request->setLaravelSession($session);
    $previousSessionId = $session->getId();

    $response = app(LoginAsDemoRoleAction::class)->handle($request, $role);

    expect($response->getTargetUrl())->toBe(route('dashboard'))
        ->and(Auth::guard('web')->id())->toBe($user->id)
        ->and($session->getId())->not->toBe($previousSessionId);
})->with(SystemRole::cases());

test('missing or mismatched demo identities are not authenticated', function (): void {
    $request = Request::create('/demo-login/waiter', 'POST');
    $session = app('session')->driver();
    $session->start();
    $request->setLaravelSession($session);
    $demoRoleUsers = app(DemoRoleUserQuery::class);

    expect($demoRoleUsers->find(SystemRole::Waiter))->toBeNull()
        ->and(Auth::guard('web')->check())->toBeFalse();

    createDemoLoginAccount(SystemRole::Waiter, SystemRole::Cook);

    expect($demoRoleUsers->find(SystemRole::Waiter))->toBeNull()
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

    demoLivewireCall(demoLivewireSnapshot(), SystemRole::Waiter->value, csrf: false)->assertStatus(419);

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
        ->assertDontSee('type="password"', escape: false)
        ->assertDontSee('name="password"', escape: false);
});

test('every seeded demo role may log in through the demo route', function (SystemRole $role): void {
    config()->set('demo-login.enabled', true);
    $user = createDemoLoginAccount($role);

    demoLivewireCall(demoLivewireSnapshot(), $role->value)->assertOk()->assertJsonPath('components.0.effects.redirect', route('dashboard'));

    $this->assertAuthenticatedAs($user);
})->with(SystemRole::cases());

test('invalid missing and mismatched demo roles are rejected', function (): void {
    config()->set('demo-login.enabled', true);
    $this->post('/demo-login/not-a-role')->assertNotFound();
    $snapshot = demoLivewireSnapshot();
    foreach (['not-a-role', SystemRole::Waiter->value] as $role) {
        $response = demoLivewireCall($snapshot, $role)->assertOk();
        expect(json_decode($response->json('components.0.snapshot'), true, flags: JSON_THROW_ON_ERROR)['memo']['errors'])->toHaveKey('form.role');
        $this->assertGuest();
    }
    createDemoLoginAccount(SystemRole::Waiter, SystemRole::Cook);
    $response = demoLivewireCall($snapshot, SystemRole::Waiter->value)->assertOk();
    expect(json_decode($response->json('components.0.snapshot'), true, flags: JSON_THROW_ON_ERROR)['memo']['errors'])->toHaveKey('form.role');
    $this->assertGuest();
});

test('authenticated users cannot switch through a stale demo snapshot', function (): void {
    config()->set('demo-login.enabled', true);
    $currentUser = User::factory()->create();
    createDemoLoginAccount(SystemRole::Waiter);
    $snapshot = demoLivewireSnapshot();
    $this->actingAs($currentUser);
    demoLivewireCall($snapshot, SystemRole::Waiter->value)->assertStatus(409);
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

function demoLivewireSnapshot(): string
{
    $response = test()->get(route('demo-login.index'))->assertOk();
    preg_match_all('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
    foreach ($matches[1] as $encoded) {
        $snapshot = html_entity_decode($encoded, ENT_QUOTES | ENT_HTML5);
        if (json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR)['memo']['name'] === 'local.demo-login') {
            return $snapshot;
        }
    }
    throw new RuntimeException('Missing demo login snapshot.');
}

function demoLivewireCall(string $snapshot, mixed $role, bool $csrf = true): TestResponse
{
    return test()->withCredentials()->withCookie(config('session.cookie'), session()->getId())->postJson(route('default-livewire.update'), [
        'components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => [['method' => 'login', 'params' => [$role]]]]],
    ], ['X-Livewire' => '', ...($csrf ? ['X-CSRF-TOKEN' => session()->token()] : [])]);
}
