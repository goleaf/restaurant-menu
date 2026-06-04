<?php

use App\Actions\Branches\CreateBranchAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\BranchOrderFlowMode;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\Settings;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('branch settings table has safe operational fields', function () {
    expect(Schema::hasTable('branch_settings'))->toBeTrue();
    expect(Schema::hasColumns('branch_settings', [
        'branch_id',
        'require_waiter_confirmation_for_orders',
        'allow_guest_created_sessions',
        'allow_waiter_opened_sessions',
        'allow_guest_invite_links',
        'guest_join_requires_approval',
        'polling_interval_seconds',
        'default_language',
        'default_currency',
        'service_charge_enabled',
        'tips_enabled',
        'order_flow_mode',
        'service_modes',
    ]))->toBeTrue();
});

test('creating branch creates settings with safe defaults', function () {
    [, $brand] = createOrganizationBrandForSettings();

    $branch = (new CreateBranchAction)->handle($brand, [
        'name' => 'Bella Pizza Vilnius Old Town',
        'address' => 'Pilies 1',
        'city' => 'Vilnius',
        'country' => 'Lithuania',
        'timezone' => 'Europe/Vilnius',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    $settings = $branch->settings()->firstOrFail();

    expect($settings->require_waiter_confirmation_for_orders)->toBeTrue();
    expect($settings->allow_guest_created_sessions)->toBeTrue();
    expect($settings->allow_waiter_opened_sessions)->toBeTrue();
    expect($settings->allow_guest_invite_links)->toBeTrue();
    expect($settings->guest_join_requires_approval)->toBeTrue();
    expect($settings->polling_interval_seconds)->toBe(1);
    expect($settings->default_language)->toBe('en');
    expect($settings->default_currency)->toBe('EUR');
    expect($settings->service_charge_enabled)->toBeFalse();
    expect($settings->tips_enabled)->toBeFalse();
    expect($settings->order_flow_mode)->toBe(BranchOrderFlowMode::WaiterConfirmation);
    expect($settings->service_modes)->toBe(['dine_in']);
});

test('branch settings page requires authentication', function () {
    [$organization, $brand, $branch] = createOrganizationBrandBranchForSettings();

    $this->get(route('organizations.brands.branches.settings.index', [$organization, $brand, $branch]))
        ->assertRedirect(route('login'));
});

test('owner can update branch settings', function () {
    [$organization, $brand, $branch, $owner] = createOrganizationBrandBranchForSettings();

    Livewire::actingAs($owner)
        ->test(Settings::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->assertSet('requireWaiterConfirmationForOrders', true)
        ->assertSet('allowGuestCreatedSessions', true)
        ->assertSet('allowWaiterOpenedSessions', true)
        ->assertSet('allowGuestInviteLinks', true)
        ->assertSet('guestJoinRequiresApproval', true)
        ->assertSet('pollingIntervalSeconds', 1)
        ->assertSet('serviceModes', ['dine_in'])
        ->assertSeeText('Service modes')
        ->set('allowGuestCreatedSessions', true)
        ->set('allowWaiterOpenedSessions', true)
        ->set('allowGuestInviteLinks', true)
        ->set('guestJoinRequiresApproval', false)
        ->set('pollingIntervalSeconds', 5)
        ->set('defaultLanguage', 'lt')
        ->set('defaultCurrency', 'usd')
        ->set('serviceChargeEnabled', true)
        ->set('tipsEnabled', true)
        ->set('orderFlowMode', BranchOrderFlowMode::StaffManaged->value)
        ->set('serviceModes', ['pickup', 'delivery', 'hotel_room_service', 'bar_only', 'custom'])
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Settings saved.');

    $settings = $branch->settings()->firstOrFail();

    expect($settings->allow_guest_created_sessions)->toBeTrue();
    expect($settings->allow_guest_invite_links)->toBeTrue();
    expect($settings->guest_join_requires_approval)->toBeFalse();
    expect($settings->polling_interval_seconds)->toBe(5);
    expect($settings->default_language)->toBe('lt');
    expect($settings->default_currency)->toBe('USD');
    expect($branch->fresh()->currency)->toBe('USD');
    expect($settings->service_charge_enabled)->toBeTrue();
    expect($settings->tips_enabled)->toBeTrue();
    expect($settings->order_flow_mode)->toBe(BranchOrderFlowMode::StaffManaged);
    expect($settings->service_modes)->toBe([
        'pickup',
        'delivery',
        'hotel_room_service',
        'bar_only',
        'custom',
    ]);
});

test('settings page creates missing settings for existing branch', function () {
    [$organization, $brand, , $owner] = createOrganizationBrandBranchForSettings(createSettings: false);

    $branch = Branch::query()
        ->where('brand_id', $brand->id)
        ->firstOrFail();

    expect($branch->settings()->exists())->toBeFalse();

    Livewire::actingAs($owner)
        ->test(Settings::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->assertSet('requireWaiterConfirmationForOrders', true)
        ->assertSet('guestJoinRequiresApproval', true)
        ->assertSet('pollingIntervalSeconds', 1);

    expect($branch->settings()->exists())->toBeTrue();
});

test('member without branch management cannot access settings', function () {
    [$organization, $brand, $branch] = createOrganizationBrandBranchForSettings();
    $waiter = User::factory()->create();
    $waiterRole = Role::query()
        ->where('code', SystemRole::Waiter->value)
        ->firstOrFail();

    $organization->users()->syncWithoutDetachingOrFail([
        $waiter->id => [
            'role_id' => $waiterRole->id,
            'status' => OrganizationUserStatus::Active->value,
            'joined_at' => now(),
        ],
    ]);

    Livewire::actingAs($waiter)
        ->test(Settings::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->assertForbidden();
});

test('branch must belong to route brand and organization', function () {
    [$organization, $brand, , $owner] = createOrganizationBrandBranchForSettings();
    [, , $otherBranch] = createOrganizationBrandBranchForSettings('Other Group', 'Other Brand');

    Livewire::actingAs($owner)
        ->test(Settings::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $otherBranch])
        ->assertForbidden();
});

test('settings validation keeps polling and order flow safe', function () {
    [$organization, $brand, $branch, $owner] = createOrganizationBrandBranchForSettings();

    Livewire::actingAs($owner)
        ->test(Settings::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->set('pollingIntervalSeconds', 0)
        ->set('defaultCurrency', 'EURO')
        ->set('orderFlowMode', 'guest_direct')
        ->set('serviceModes', ['maps_and_couriers'])
        ->call('save')
        ->assertHasErrors([
            'pollingIntervalSeconds' => ['min'],
            'defaultCurrency' => ['size', 'in'],
            'orderFlowMode' => ['in'],
            'serviceModes.0' => ['in'],
        ]);
});

function createOrganizationBrandForSettings(
    string $organizationName = 'Food Group',
    string $brandName = 'Bella Pizza',
): array {
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => $organizationName]);
    $brand = Brand::factory()->for($organization)->create(['name' => $brandName]);

    return [$organization, $brand, $owner];
}

function createOrganizationBrandBranchForSettings(
    string $organizationName = 'Food Group',
    string $brandName = 'Bella Pizza',
    bool $createSettings = true,
): array {
    [$organization, $brand, $owner] = createOrganizationBrandForSettings($organizationName, $brandName);

    if ($createSettings) {
        $branch = (new CreateBranchAction)->handle($brand, [
            'name' => $brandName.' Vilnius Old Town',
            'address' => 'Pilies 1',
            'city' => 'Vilnius',
            'country' => 'Lithuania',
            'timezone' => 'Europe/Vilnius',
            'currency' => 'EUR',
            'is_active' => true,
        ]);
    } else {
        $branch = Branch::factory()
            ->for($organization)
            ->for($brand)
            ->create(['name' => $brandName.' Legacy Branch']);
    }

    return [$organization, $brand, $branch, $owner];
}
