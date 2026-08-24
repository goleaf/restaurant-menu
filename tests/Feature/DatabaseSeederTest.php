<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\Organization;
use App\Models\User;
use App\Support\DemoLogin\DemoAccountCatalog;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
});

test('database seeder assembles the complete demo graph for the enabled ruflo environment', function (): void {
    config()->set('app.url', 'https://ruflo.test');
    config()->set('demo-login.enabled', true);
    config()->set('demo-login.allowed_hosts', ['ruflo.test', 'restaurant-menu.test']);

    $this->seed(DatabaseSeeder::class);

    expect(Organization::query()->count())->toBe(3)
        ->and(User::query()->whereIn('email', array_column(DemoAccountCatalog::accounts(), 'email'))->count())
        ->toBe(count(DemoAccountCatalog::accounts()))
        ->and(Storage::disk('public')->allFiles('qr'))->not->toBeEmpty();
});

test('database seeder keeps demo data opt in outside the dedicated host', function (): void {
    config()->set('app.url', 'https://restaurant-menu.test');
    config()->set('demo-login.enabled', true);
    config()->set('demo-login.allowed_hosts', ['ruflo.test']);

    $this->seed(DatabaseSeeder::class);

    expect(Organization::query()->count())->toBe(0);
});

test('database seeder never creates demo data in production', function (): void {
    config()->set('app.env', 'production');
    config()->set('app.url', 'https://ruflo.test');
    config()->set('demo-login.enabled', true);
    config()->set('demo-login.allowed_hosts', ['ruflo.test']);

    $this->seed(DatabaseSeeder::class);

    expect(Organization::query()->count())->toBe(0);
});

test('every database-seeded demo role signs in and reaches its prepared workspace', function (): void {
    config()->set('app.url', 'https://ruflo.test');
    config()->set('demo-login.enabled', true);
    config()->set('demo-login.allowed_hosts', ['ruflo.test', 'restaurant-menu.test']);

    $this->seed(DatabaseSeeder::class);

    foreach (DemoAccountCatalog::accounts() as $account) {
        $user = User::query()->where('email', $account['email'])->firstOrFail();

        $this->post(route('demo-login.authenticate', ['role' => $account['role']->value]))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->get(route('dashboard'))->assertOk();
        $this->get(route(demoWorkspaceRoute($account['role'])))->assertOk();

        Auth::guard('web')->logout();
        $this->assertGuest();
    }
});

function demoWorkspaceRoute(SystemRole $role): string
{
    return match ($role) {
        SystemRole::Superadmin => 'superadmin.dashboard',
        SystemRole::HeadChef, SystemRole::Cook => 'restaurant.kitchen.dashboard',
        SystemRole::Bartender => 'restaurant.bar.dashboard',
        SystemRole::Waiter => 'restaurant.waiter.dashboard',
        default => 'restaurant.dashboard',
    };
}
