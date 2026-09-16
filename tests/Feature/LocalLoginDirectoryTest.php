<?php

declare(strict_types=1);

use App\Actions\Auth\BuildLocalLoginDirectoryAction;
use App\Enums\SystemRole;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\PermissionUserOverride;
use App\Models\Role;
use App\Models\User;
use App\Support\DemoLogin\DemoAccountCatalog;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Http\Request;

beforeEach(function (): void {
    config()->set([
        'app.env' => 'local',
        'demo-login.enabled' => true,
        'demo-login.allowed_hosts' => ['restaurant-menu.test'],
        'demo-login.password' => 'Local-fixture-only-123!',
    ]);
    $this->app->detectEnvironment(fn (): string => 'local');
});

test('login lists local identities memberships role grants and scoped overrides without exposing hashes', function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $identity = DemoAccountCatalog::forRole(SystemRole::Waiter);
    $user = User::factory()->create([
        'name' => $identity['name'], 'email' => $identity['email'],
        'password' => config('demo-login.password'),
    ]);
    $role = Role::query()->where('code', SystemRole::Waiter)->firstOrFail();
    $user->roles()->attach($role);
    $organization = Organization::factory()->create(['name' => 'Directory Company']);
    OrganizationUser::factory()->for($organization)->for($user)->for($role)->create();
    $permission = Permission::query()->where('code', 'view_reports')->firstOrFail();
    PermissionUserOverride::factory()->forUser($user)->forPermission($permission)->forOrganization($organization)->denied()->create();
    $other = User::factory()->create(['email' => 'ordinary@example.test']);
    $originalHash = $other->password;

    $this->get('https://restaurant-menu.test/login')->assertOk()
        ->assertSeeText($identity['email'])->assertSeeText($other->email)
        ->assertSeeText('Directory Company')->assertSeeText('Waiter')
        ->assertSeeText('view_reports')->assertSeeText(config('demo-login.password'))
        ->assertSeeText(__('local_login.password_unknown'))
        ->assertDontSee($user->password)->assertDontSee($other->password)
        ->assertHeaderContains('Cache-Control', 'no-store')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    expect($other->refresh()->password)->toBe($originalHash);
});

test('login directory is absent outside its explicit local environment and host', function (string $environment, string $configuredEnvironment, bool $enabled, string $host): void {
    $this->app->detectEnvironment(fn (): string => $environment);
    config()->set(['app.env' => $configuredEnvironment, 'demo-login.enabled' => $enabled]);
    $user = User::factory()->create();

    $this->get('https://'.$host.'/login')->assertOk()
        ->assertDontSeeText($user->email)->assertDontSeeText(__('local_login.title'))
        ->assertDontSeeText(config('demo-login.password'));
})->with([
    ['production', 'local', true, 'restaurant-menu.test'],
    ['local', 'production', true, 'restaurant-menu.test'],
    ['staging', 'staging', true, 'restaurant-menu.test'],
    ['local', 'local', false, 'restaurant-menu.test'],
    ['local', 'local', true, 'foreign.test'],
]);

test('password display requires canonical demo identity role and matching current hash', function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $identity = DemoAccountCatalog::forRole(SystemRole::Waiter);
    $user = User::factory()->create(['email' => $identity['email'], 'password' => config('demo-login.password')]);
    $role = Role::query()->where('code', SystemRole::Waiter)->firstOrFail();
    $request = Request::create('https://restaurant-menu.test/login');
    $action = app(BuildLocalLoginDirectoryAction::class);

    expect($action->handle($request)->items()[0]['password'])->toBeNull();
    $user->roles()->attach($role);
    expect($action->handle($request)->items()[0]['password'])->toBe(config('demo-login.password'));
    $user->update(['password' => 'Changed-fixture-only-456!']);
    expect($action->handle($request)->items()[0]['password'])->toBeNull();
    config()->set('demo-login.password', null);
    expect($action->handle($request)->items()[0]['password'])->toBeNull();
});

test('local directory is paginated and its query count does not grow per user', function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $role = Role::query()->where('code', SystemRole::Waiter)->firstOrFail();
    $organization = Organization::factory()->create();
    $addUsers = function (int $number) use ($role, $organization): void {
        foreach (User::factory()->count($number)->create() as $user) {
            $user->roles()->attach($role);
            OrganizationUser::factory()->for($organization)->for($user)->for($role)->create();
        }
    };
    $addUsers(1);
    $request = Request::create('https://restaurant-menu.test/login');
    $action = app(BuildLocalLoginDirectoryAction::class);
    $small = countDatabaseQueries(fn () => $action->handle($request));
    $addUsers(30);
    $large = countDatabaseQueries(fn () => $action->handle($request));

    expect($large)->toBe($small)->toBeLessThanOrEqual(12)
        ->and($action->handle($request)->items())->toHaveCount(25)
        ->and($action->handle($request)->hasMorePages())->toBeTrue();
    $this->get('https://restaurant-menu.test/login?users_page=2')->assertOk()
        ->assertViewHas('localUsers', fn ($users): bool => count($users->items()) === 7);
});

test('empty directory shows an empty state without creating users on a get request', function (): void {
    $this->get('https://restaurant-menu.test/login')->assertOk()->assertSeeText(__('local_login.empty'));
    expect(User::query()->exists())->toBeFalse();
});

test('canonical demo accounts can use their displayed password through normal Fortify login', function (SystemRole $systemRole): void {
    $this->seed(SystemPermissionsSeeder::class);
    $identity = DemoAccountCatalog::forRole($systemRole);
    $user = User::factory()->create(['email' => $identity['email'], 'password' => config('demo-login.password')]);
    $user->roles()->attach(Role::query()->where('code', $systemRole)->firstOrFail());
    $this->app->detectEnvironment(fn (): string => 'testing');

    $this->post('/login', ['email' => $identity['email'], 'password' => config('demo-login.password')])->assertRedirect();
    $this->assertAuthenticatedAs($user);
})->with(SystemRole::cases());
