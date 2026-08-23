<?php

use App\Actions\Branches\CreateBranchAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\OrganizationSubscriptionStatus;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\Settings;
use App\Livewire\Superadmin\Dashboard as SuperadminDashboard;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\Order;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
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

test('superadmin sees expanded organization controls and counts', function () {
    [$organization, $brand, $branch] = createPlatformRecordsForSuperadmin();
    $superadmin = createSuperadminUser();
    $inactiveBranch = app(CreateBranchAction::class)->handle($brand, [
        'name' => 'Platform Paused Branch',
        'address' => 'Side Street 2',
        'city' => 'Kaunas',
        'country' => 'Lithuania',
        'timezone' => 'Europe/Vilnius',
        'currency' => 'EUR',
        'is_active' => false,
    ]);
    $firstServicePoint = ServicePoint::factory()->for($branch)->create(['name' => 'Dashboard Table 1']);
    ServicePoint::factory()->for($inactiveBranch)->create(['name' => 'Dashboard Table 2']);
    $tableSession = TableSession::factory()->forServicePoint($firstServicePoint)->active()->create();
    $draftOrder = DraftOrder::factory()->for($tableSession)->create();
    Order::factory()->create([
        'branch_id' => $branch->id,
        'service_point_id' => $firstServicePoint->id,
        'table_session_id' => $tableSession->id,
        'draft_order_id' => $draftOrder->id,
        'total_price_cents' => 2500,
    ]);

    $this->actingAs($superadmin)
        ->get(route('superadmin.dashboard'))
        ->assertOk()
        ->assertSee(__('navigation.service_points'))
        ->assertSee('Orders')
        ->assertSee('Activity active')
        ->assertSee('Service points')
        ->assertSee('Platform Visible Group')
        ->assertSee('Platform Visible Brand')
        ->assertSee('Platform Visible Branch')
        ->assertSee('Open details')
        ->assertSee('Audit log')
        ->assertSee(route('organizations.brands.index', $organization), false)
        ->assertSee(route('restaurant.audit-log.index', ['organization' => $organization->id]), false);

    Livewire::actingAs($superadmin)
        ->test(SuperadminDashboard::class)
        ->set('organizationSuspendReason', 'Manual billing pause for account review.')
        ->call('suspendOrganization', $organization->id)
        ->assertSee('Activity suspended')
        ->call('activateOrganization', $organization->id)
        ->assertSee('Activity active');

    expect($organization->fresh()->subscription->status)->toBe(OrganizationSubscriptionStatus::Active);
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
    $branch = app(CreateBranchAction::class)->handle($brand, [
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
