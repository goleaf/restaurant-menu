<?php

use App\Actions\Branches\CreateBranchAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\Settings;
use App\Models\Brand;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\FirstSuperadminSeeder;
use Database\Seeders\SystemPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('first superadmin seeder creates configured platform user', function () {
    config()->set('platform.first_superadmin.name', 'Platform Root');
    config()->set('platform.first_superadmin.email', 'platform@example.test');
    config()->set('platform.first_superadmin.password', 'safe-local-password');

    $this->seed(FirstSuperadminSeeder::class);

    $user = User::query()
        ->where('email', 'platform@example.test')
        ->firstOrFail();

    expect($user->name)->toBe('Platform Root');
    expect($user->isSuperadmin())->toBeTrue();
});

test('ordinary user cannot access or see the superadmin zone', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Platform dashboard')
        ->assertDontSee('Superadmin');

    $this->actingAs($user)
        ->get(route('superadmin.dashboard'))
        ->assertForbidden();
});

test('superadmin can access platform dashboard and see platform records', function () {
    [$organization, $brand, $branch] = createPlatformRecordsForSuperadmin();
    $superadmin = createSuperadminUser();
    $visibleUser = User::factory()->create([
        'name' => 'Visible Staff User',
        'email' => 'visible-staff@example.test',
    ]);

    $this->actingAs($superadmin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Platform');

    $this->actingAs($superadmin)
        ->get(route('superadmin.dashboard'))
        ->assertOk()
        ->assertSee('Platform dashboard')
        ->assertSee($organization->name)
        ->assertSee($brand->name)
        ->assertSee($branch->name)
        ->assertSee($visibleUser->email);
});

test('superadmin bypasses organization branch restrictions', function () {
    [$organization, $brand, $branch] = createPlatformRecordsForSuperadmin();
    $superadmin = createSuperadminUser();

    Livewire::actingAs($superadmin)
        ->test(Settings::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->assertSee('Branch settings')
        ->assertSet('branch.id', $branch->id);
});

function createSuperadminUser(): User
{
    $user = User::factory()->create([
        'name' => 'Platform Superadmin',
        'email' => 'superadmin@example.test',
    ]);
    $role = Role::query()
        ->where('code', SystemRole::Superadmin->value)
        ->firstOrFail();

    $user->roles()->syncWithoutDetachingOrFail([$role->id]);

    return $user;
}

function createPlatformRecordsForSuperadmin(): array
{
    $owner = User::factory()->create(['name' => 'Restaurant Owner']);
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Platform Visible Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Platform Visible Brand']);
    $branch = (new CreateBranchAction)->handle($brand, [
        'name' => 'Platform Visible Branch',
        'address' => 'Main Street 1',
        'city' => 'Vilnius',
        'country' => 'Lithuania',
        'timezone' => 'Europe/Vilnius',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    return [$organization, $brand, $branch, $owner];
}
