<?php

declare(strict_types=1);

use App\Actions\Auth\BuildDemoLoginPageAction;
use App\Actions\Auth\LoginAsDemoRoleAction;
use App\Enums\SystemRole;
use App\Http\Middleware\EnsureDemoLoginIsEnabled;
use App\Models\Role;
use App\Models\User;
use App\Support\DemoLogin\DemoAccountCatalog;
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
