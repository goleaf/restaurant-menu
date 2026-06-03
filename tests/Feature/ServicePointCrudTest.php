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
    [$organization, $brand, $branch, $manager] = createServicePointCrudBranch();

    $this->actingAs($manager)
        ->get(route('organizations.brands.branches.service-points.index', [$organization, $brand, $branch]))
        ->assertForbidden();

    grantServicePointCrudPermission($manager, $organization);

    $this->actingAs($manager)
        ->get(route('organizations.brands.branches.service-points.index', [$organization, $brand, $branch]))
        ->assertOk()
        ->assertSee('Service points');
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

    $originalId = $servicePoint->id;
    $originalInternalCode = $servicePoint->internal_code;

    Livewire::actingAs($manager)
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

    $servicePoint->refresh();

    expect($servicePoint->id)->toBe($originalId);
    expect($servicePoint->internal_code)->toBe($originalInternalCode);
    expect($servicePoint->area_node_id)->toBe($terrace->id);
    expect($servicePoint->name)->toBe('Terrace table 12');
    expect($servicePoint->display_number)->toBe('T-12');
    expect($servicePoint->capacity)->toBe(5);
    expect($servicePoint->icon)->toBe('sparkles');
    expect($servicePoint->is_active)->toBeFalse();
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
    $brand = Brand::factory()->for($organization)->create(['name' => $brandName]);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create(['name' => $brandName.' Branch']);

    return [$organization, $brand, $branch, $manager->fresh()];
}

function grantServicePointCrudPermission(User $user, Organization $organization): void
{
    $membership = OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $user->id)
        ->where('status', OrganizationUserStatus::Active->value)
        ->firstOrFail();
    $permission = Permission::query()
        ->where('code', SystemPermission::ManageServicePoints->value)
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
