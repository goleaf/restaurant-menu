<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\AreaNodeType;
use App\Enums\OrganizationUserStatus;
use App\Enums\ServicePointStatus;
use App\Enums\ServicePointType;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\Index as BranchesIndex;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\Index as ServicePointsIndex;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\QrCode;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('service point page requires authentication', function () {
    [$organization, $brand, $branch] = createServicePointCrudBranch();

    $this->get(route('organizations.brands.branches.service-points.index', [$organization, $brand, $branch]))
        ->assertRedirect(route('login'));
});

test('service point page requires manage service points permission', function () {
    app()->setLocale('ru');
    [$organization, $brand, $branch, $manager] = createServicePointCrudBranch();

    $this->actingAs($manager)
        ->get(route('organizations.brands.branches.service-points.index', [$organization, $brand, $branch]))
        ->assertForbidden();

    grantServicePointCrudPermission($manager, $organization);

    $this->actingAs($manager)
        ->get(route('organizations.brands.branches.service-points.index', [$organization, $brand, $branch]))
        ->assertOk()
        ->assertSee(__('navigation.service_points'))
        ->assertSee(__('ui.organizations.brands.branches.index.stoly_i_mesta'))
        ->assertSee(__('ui.organizations.brands.branches.service_points.index.sag_3_dobavte_stoly'));
});

test('branch list shows service point link to users with permission or waiter role', function () {
    [$organization, $brand, , $manager] = createServicePointCrudBranch();

    Livewire::actingAs($manager)
        ->test(BranchesIndex::class, ['organization' => $organization, 'brand' => $brand])
        ->assertDontSee('Service points');

    grantServicePointCrudPermission($manager, $organization);

    Livewire::actingAs($manager)
        ->test(BranchesIndex::class, ['organization' => $organization, 'brand' => $brand])
        ->assertSee('Service points');

    $waiter = User::factory()->create();
    attachServicePointCrudWaiter($waiter, $organization);

    Livewire::actingAs($waiter)
        ->test(BranchesIndex::class, ['organization' => $organization, 'brand' => $brand])
        ->assertSee('Service points');
});

test('manager can create service points inside a branch area', function () {
    [$organization, $brand, $branch, $manager] = createServicePointCrudBranch();
    grantServicePointCrudPermission($manager, $organization);
    $hall = AreaNode::factory()
        ->for($branch)
        ->create([
            'type' => AreaNodeType::Hall,
            'name' => 'Main hall',
            'icon' => 'squares-2x2',
        ]);

    Livewire::actingAs($manager)
        ->test(ServicePointsIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->assertSee('No service points yet.')
        ->call('prepareCreate', ServicePointType::Table->value)
        ->assertSet('type', ServicePointType::Table->value)
        ->assertSet('icon', 'squares-2x2')
        ->set('name', 'Table by window')
        ->set('displayNumber', '12')
        ->set('areaNodeId', (string) $hall->id)
        ->set('capacity', 4)
        ->call('create')
        ->assertHasNoErrors()
        ->assertSee('Table by window')
        ->assertSee('Main hall');

    $servicePoint = ServicePoint::query()
        ->where('branch_id', $branch->id)
        ->where('name', 'Table by window')
        ->firstOrFail();

    expect($servicePoint->area_node_id)->toBe($hall->id);
    expect($servicePoint->type)->toBe(ServicePointType::Table);
    expect($servicePoint->display_number)->toBe('12');
    expect($servicePoint->capacity)->toBe(4);
    expect($servicePoint->internal_code)->not->toBeNull();
    expect(str_starts_with((string) $servicePoint->internal_code, 'SP-'))->toBeTrue();
    expect($servicePoint->status)->toBe(ServicePointStatus::Free);
});

test('manager can preview and bulk create service points without creating qr automatically', function () {
    [$organization, $brand, $branch, $manager] = createServicePointCrudBranch();
    grantServicePointCrudPermission($manager, $organization);
    $hall = AreaNode::factory()
        ->for($branch)
        ->create([
            'type' => AreaNodeType::Hall,
            'name' => 'Main hall',
        ]);
    ServicePoint::factory()
        ->for($branch)
        ->for($hall)
        ->create([
            'name' => 'Existing T2',
            'display_number' => 'T2',
            'internal_code' => 'T2',
        ]);

    Livewire::actingAs($manager)
        ->test(ServicePointsIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->set('bulkAreaNodeId', (string) $hall->id)
        ->set('bulkType', ServicePointType::Table->value)
        ->set('bulkPrefix', 'T')
        ->set('bulkFrom', 1)
        ->set('bulkTo', 3)
        ->set('bulkCapacity', 4)
        ->call('previewBulkCreate')
        ->assertHasNoErrors()
        ->assertSee('T1')
        ->assertSee('T2')
        ->assertSee('Already exists')
        ->assertSee('T3')
        ->call('confirmBulkCreate')
        ->assertHasNoErrors()
        ->assertSee('Created 2 service points.')
        ->assertSee('Generate QR later');

    $servicePoints = ServicePoint::query()
        ->where('branch_id', $branch->id)
        ->orderBy('internal_code')
        ->get();

    expect($servicePoints)->toHaveCount(3);
    expect($servicePoints->pluck('internal_code')->all())->toBe(['T1', 'T2', 'T3']);
    expect($servicePoints->where('internal_code', 'T1')->first()->area_node_id)->toBe($hall->id);
    expect($servicePoints->where('internal_code', 'T1')->first()->name)->toBe('T1');
    expect($servicePoints->where('internal_code', 'T1')->first()->display_number)->toBe('T1');
    expect($servicePoints->where('internal_code', 'T1')->first()->capacity)->toBe(4);
    expect($servicePoints->where('internal_code', 'T1')->first()->type)->toBe(ServicePointType::Table);
    expect($servicePoints->where('internal_code', 'T1')->first()->activeQrCode()->exists())->toBeFalse();
    expect($servicePoints->where('internal_code', 'T3')->first()->activeQrCode()->exists())->toBeFalse();
});

test('manager can search and filter service points inside current branch', function () {
    [$organization, $brand, $branch, $manager] = createServicePointCrudBranch();
    grantServicePointCrudPermission($manager, $organization);
    $hall = AreaNode::factory()->for($branch)->create(['name' => 'Filter Hall']);
    $terrace = AreaNode::factory()->for($branch)->create(['name' => 'Filter Terrace']);
    $target = ServicePoint::factory()
        ->for($branch)
        ->for($hall)
        ->create([
            'type' => ServicePointType::Table,
            'name' => 'Alpha Window Table',
            'display_number' => 'A1',
            'internal_code' => 'ALPHA-001',
            'status' => ServicePointStatus::Free,
            'is_active' => true,
        ]);
    ServicePoint::factory()
        ->for($branch)
        ->for($terrace)
        ->create([
            'type' => ServicePointType::BarSeat,
            'name' => 'Beta Patio Seat',
            'display_number' => 'B2',
            'internal_code' => 'BETA-002',
            'status' => ServicePointStatus::Occupied,
            'is_active' => false,
        ]);
    ServicePoint::factory()
        ->for($branch)
        ->create([
            'type' => ServicePointType::PickupWindow,
            'name' => 'Gamma Pickup Window',
            'display_number' => 'G3',
            'internal_code' => 'GAMMA-003',
            'status' => ServicePointStatus::Reserved,
            'is_active' => true,
        ]);
    QrCode::factory()
        ->for($target)
        ->create([
            'short_code' => 'QR-FIND110',
            'created_by_user_id' => $manager->id,
        ]);

    Livewire::actingAs($manager)
        ->test(ServicePointsIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->assertSee($branch->name)
        ->assertSee('Alpha Window Table')
        ->assertSee('Beta Patio Seat')
        ->assertSee('Gamma Pickup Window')
        ->set('servicePointSearch', 'find110')
        ->assertSee('Alpha Window Table')
        ->assertDontSee('Beta Patio Seat')
        ->assertDontSee('Gamma Pickup Window')
        ->call('resetServicePointFilters')
        ->set('filterAreaNodeId', (string) $terrace->id)
        ->assertSee('Beta Patio Seat')
        ->assertDontSee('Alpha Window Table')
        ->call('resetServicePointFilters')
        ->set('filterType', ServicePointType::PickupWindow->value)
        ->assertSee('Gamma Pickup Window')
        ->assertDontSee('Alpha Window Table')
        ->call('resetServicePointFilters')
        ->set('filterStatus', ServicePointStatus::Occupied->value)
        ->assertSee('Beta Patio Seat')
        ->assertDontSee('Alpha Window Table')
        ->call('resetServicePointFilters')
        ->set('filterActive', 'inactive')
        ->assertSee('Beta Patio Seat')
        ->assertDontSee('Gamma Pickup Window')
        ->call('resetServicePointFilters')
        ->set('filterQr', 'with')
        ->assertSee('Alpha Window Table')
        ->assertDontSee('Beta Patio Seat')
        ->call('resetServicePointFilters')
        ->set('filterQr', 'without')
        ->assertSee('Beta Patio Seat')
        ->assertSee('Gamma Pickup Window')
        ->assertDontSee('Alpha Window Table');
});

test('service point page shows a simple visual floor board grouped by zones', function () {
    app()->setLocale('ru');
    [$organization, $brand, $branch, $manager] = createServicePointCrudBranch();
    grantServicePointCrudPermission($manager, $organization);
    grantServicePointCrudPermission($manager, $organization, SystemPermission::GenerateQr);
    grantServicePointCrudPermission($manager, $organization, SystemPermission::ViewOrders);
    $hall = AreaNode::factory()
        ->for($branch)
        ->create([
            'type' => AreaNodeType::Hall,
            'name' => 'Board Hall',
            'icon' => 'squares-2x2',
            'sort_order' => 1,
        ]);
    $terrace = AreaNode::factory()
        ->for($branch)
        ->create([
            'type' => AreaNodeType::Terrace,
            'name' => 'Board Terrace',
            'icon' => 'sun',
            'sort_order' => 2,
        ]);
    $hallTable = ServicePoint::factory()
        ->for($branch)
        ->for($hall)
        ->create([
            'type' => ServicePointType::Table,
            'name' => 'Board Alpha Table',
            'display_number' => 'A1',
            'internal_code' => 'BOARD-HALL',
            'status' => ServicePointStatus::HasNewOrder,
            'icon' => 'squares-2x2',
        ]);
    $terraceTable = ServicePoint::factory()
        ->for($branch)
        ->for($terrace)
        ->create([
            'type' => ServicePointType::BarSeat,
            'name' => 'Board Terrace Seat',
            'display_number' => 'T2',
            'internal_code' => 'BOARD-TERRACE',
            'status' => ServicePointStatus::WaitingWaiter,
            'icon' => 'beaker',
        ]);
    ServicePoint::factory()
        ->for($branch)
        ->create([
            'type' => ServicePointType::PickupWindow,
            'name' => 'Board Pickup Window',
            'display_number' => 'P3',
            'internal_code' => 'BOARD-PICKUP',
            'status' => ServicePointStatus::Free,
            'icon' => 'shopping-bag',
        ]);
    QrCode::factory()
        ->for($hallTable)
        ->create([
            'short_code' => 'QR-BOARD1',
            'created_by_user_id' => $manager->id,
        ]);

    Livewire::actingAs($manager)
        ->test(ServicePointsIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->assertSee(__('ui.organizations.brands.branches.service_points.index.vizualnyi_zal'))
        ->assertSee('Board Hall')
        ->assertSee('Board Terrace')
        ->assertSee(__('ui.livewire.organizations.brands.branches.servicepoints.index.bez_zony'))
        ->assertSee('Board Alpha Table')
        ->assertSee('Board Terrace Seat')
        ->assertSee('Board Pickup Window')
        ->assertSee(__(ServicePointStatus::HasNewOrder->label()))
        ->assertSee(__(ServicePointStatus::WaitingWaiter->label()))
        ->assertSee('QR-BOARD1')
        ->assertSee(__('qr.actions.show'))
        ->assertSee(__('ui.organizations.brands.branches.service_points.index.otkryt_stol'))
        ->assertSee(__('ui.organizations.brands.branches.area_node_row.izmenit'))
        ->call('startEditingFromBoard', $terraceTable->id)
        ->assertSet('editingServicePointId', $terraceTable->id)
        ->assertSet('servicePointSearch', 'BOARD-TERRACE')
        ->assertSee('Board Terrace Seat');
});

test('service point list is paginated instead of loading every row', function () {
    [$organization, $brand, $branch, $manager] = createServicePointCrudBranch();
    grantServicePointCrudPermission($manager, $organization);

    foreach (range(1, 12) as $number) {
        ServicePoint::factory()
            ->for($branch)
            ->create([
                'name' => sprintf('Paged Table %02d', $number),
                'display_number' => sprintf('%02d', $number),
                'internal_code' => sprintf('PAGED-%02d', $number),
            ]);
    }

    Livewire::actingAs($manager)
        ->test(ServicePointsIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->assertSee('Paged Table 01')
        ->assertDontSee('Paged Table 11')
        ->call('nextPage')
        ->assertSee('Paged Table 11')
        ->assertDontSee('Paged Table 01');
});

test('waiter cannot bulk create service points', function () {
    [$organization, $brand, $branch] = createServicePointCrudBranch();
    $waiter = User::factory()->create();
    attachServicePointCrudWaiter($waiter, $organization);

    Livewire::actingAs($waiter)
        ->test(ServicePointsIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->set('bulkPrefix', 'W')
        ->set('bulkFrom', 1)
        ->set('bulkTo', 2)
        ->call('previewBulkCreate')
        ->assertForbidden();
});

test('manager can rename move and disable service points without changing identity', function () {
    [$organization, $brand, $branch, $manager] = createServicePointCrudBranch();
    grantServicePointCrudPermission($manager, $organization);
    $firstHall = AreaNode::factory()->for($branch)->create(['name' => 'First hall']);
    $terrace = AreaNode::factory()->for($branch)->create(['name' => 'Terrace']);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->for($firstHall)
        ->create([
            'type' => ServicePointType::Table,
            'name' => 'Table 12',
            'display_number' => '12',
            'internal_code' => 'SP-STABLE-12',
            'icon' => 'squares-2x2',
            'capacity' => 2,
        ]);
    $qrCode = QrCode::factory()
        ->for($servicePoint)
        ->create([
            'short_code' => 'QR-MOVE12',
            'created_by_user_id' => $manager->id,
        ]);

    $originalId = $servicePoint->id;
    $originalInternalCode = $servicePoint->internal_code;
    $originalQrId = $qrCode->id;
    $originalQrToken = $qrCode->public_token;

    $component = Livewire::actingAs($manager)
        ->test(ServicePointsIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->call('startEditing', $servicePoint->id)
        ->assertSet('editingName', 'Table 12')
        ->set('editingName', 'Terrace table 12')
        ->set('editingDisplayNumber', 'T-12')
        ->set('editingAreaNodeId', (string) $terrace->id)
        ->set('editingCapacity', 5)
        ->set('editingIcon', 'sparkles')
        ->call('update')
        ->assertHasNoErrors()
        ->assertSee('Terrace table 12')
        ->call('disable', $servicePoint->id);

    expect($component->get('floorBoardServicePointCount'))->toBe(1);

    $servicePoint->refresh();

    expect($servicePoint->id)->toBe($originalId);
    expect($servicePoint->internal_code)->toBe($originalInternalCode);
    expect($qrCode->fresh()->id)->toBe($originalQrId);
    expect($qrCode->fresh()->public_token)->toBe($originalQrToken);
    expect($qrCode->fresh()->service_point_id)->toBe($servicePoint->id);
    expect($servicePoint->area_node_id)->toBe($terrace->id);
    expect($servicePoint->name)->toBe('Terrace table 12');
    expect($servicePoint->display_number)->toBe('T-12');
    expect($servicePoint->capacity)->toBe(5);
    expect($servicePoint->icon)->toBe('sparkles');
    expect($servicePoint->is_active)->toBeFalse();

    $component->call('enable', $servicePoint->id);

    expect($servicePoint->fresh()->is_active)->toBeTrue();
});

test('manager can change service point status manually', function () {
    [$organization, $brand, $branch, $manager] = createServicePointCrudBranch();
    grantServicePointCrudPermission($manager, $organization);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Status table',
            'status' => ServicePointStatus::Free,
        ]);

    Livewire::actingAs($manager)
        ->test(ServicePointsIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->assertSee('Free')
        ->set('statusSelections.'.$servicePoint->id, ServicePointStatus::WaitingWaiter->value)
        ->call('changeStatus', $servicePoint->id)
        ->assertHasNoErrors()
        ->assertSee('Waiting waiter');

    expect($servicePoint->fresh()->status)->toBe(ServicePointStatus::WaitingWaiter);
});

test('waiter can change service point status without service point management permission', function () {
    [$organization, $brand, $branch] = createServicePointCrudBranch();
    $waiter = User::factory()->create();
    attachServicePointCrudWaiter($waiter, $organization);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Waiter table',
            'status' => ServicePointStatus::Free,
        ]);

    Livewire::actingAs($waiter)
        ->test(ServicePointsIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->assertSet('canManageServicePoints', false)
        ->assertSet('canChangeServicePointStatus', true)
        ->assertDontSee('Add table')
        ->set('statusSelections.'.$servicePoint->id, ServicePointStatus::HasNewOrder->value)
        ->call('changeStatus', $servicePoint->id)
        ->assertHasNoErrors()
        ->assertSee('Has new order');

    expect($servicePoint->fresh()->status)->toBe(ServicePointStatus::HasNewOrder);
});

test('service point cannot be assigned to area from another branch', function () {
    [$organization, $brand, $branch, $manager] = createServicePointCrudBranch();
    grantServicePointCrudPermission($manager, $organization);
    [, , $otherBranch] = createServicePointCrudBranch('Other Group', 'Other Brand');
    $otherArea = AreaNode::factory()
        ->for($otherBranch)
        ->create(['name' => 'Other hall']);

    Livewire::actingAs($manager)
        ->test(ServicePointsIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->set('name', 'Wrong area table')
        ->set('areaNodeId', (string) $otherArea->id)
        ->call('create')
        ->assertHasErrors('areaNodeId');
});

test('branch must belong to route brand and organization on service point page', function () {
    [$organization, $brand, , $manager] = createServicePointCrudBranch();
    grantServicePointCrudPermission($manager, $organization);
    [, , $otherBranch] = createServicePointCrudBranch('Foreign Group', 'Foreign Brand');

    Livewire::actingAs($manager)
        ->test(ServicePointsIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $otherBranch])
        ->assertForbidden();
});

function createServicePointCrudBranch(string $organizationName = 'Service Point Group', string $brandName = 'Service Point Brand'): array
{
    $manager = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($manager, ['name' => $organizationName]);
    $restrictedRole = Role::query()
        ->where('code', SystemRole::Cook->value)
        ->firstOrFail();
    $membership = OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $manager->id)
        ->firstOrFail();
    $membership->forceFill(['role_id' => $restrictedRole->id])->saveOrFail();

    $brand = Brand::factory()->for($organization)->create(['name' => $brandName]);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create(['name' => $brandName.' Branch']);

    return [$organization, $brand, $branch, $manager->fresh()];
}

function grantServicePointCrudPermission(
    User $user,
    Organization $organization,
    SystemPermission $systemPermission = SystemPermission::ManageServicePoints,
): void {
    $membership = OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $user->id)
        ->where('status', OrganizationUserStatus::Active->value)
        ->firstOrFail();
    $permission = Permission::query()
        ->where('code', $systemPermission->value)
        ->firstOrFail();

    $membership->role->permissions()->updateExistingPivot($permission->id, ['enabled' => true]);
}

function attachServicePointCrudWaiter(User $user, Organization $organization): void
{
    $waiterRole = Role::query()
        ->where('code', SystemRole::Waiter->value)
        ->firstOrFail();

    $organization->users()->syncWithoutDetachingOrFail([
        $user->id => [
            'role_id' => $waiterRole->id,
            'status' => OrganizationUserStatus::Active->value,
            'joined_at' => now(),
            'invited_by_user_id' => null,
        ],
    ]);
}
