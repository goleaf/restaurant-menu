<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\AreaNodeType;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Livewire\Organizations\Brands\Branches\Areas;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('area node page requires authentication', function () {
    [$organization, $brand, $branch] = createAreaCrudBranch();

    $this->get(route('organizations.brands.branches.areas.index', [$organization, $brand, $branch]))
        ->assertRedirect(route('login'));
});

test('area node page requires manage zones permission', function () {
    [$organization, $brand, $branch, $manager] = createAreaCrudBranch();

    $this->actingAs($manager)
        ->get(route('organizations.brands.branches.areas.index', [$organization, $brand, $branch]))
        ->assertForbidden();

    grantAreaCrudManageZones($manager, $organization);

    $this->actingAs($manager)
        ->get(route('organizations.brands.branches.areas.index', [$organization, $brand, $branch]))
        ->assertOk()
        ->assertSee('Areas')
        ->assertSee('Зоны ресторана')
        ->assertSee('Шаг 2: добавьте зоны');
});

test('manager can create nested area nodes inside branch', function () {
    [$organization, $brand, $branch, $manager] = createAreaCrudBranch();
    grantAreaCrudManageZones($manager, $organization);

    Livewire::actingAs($manager)
        ->test(Areas::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->assertSee('No areas yet.')
        ->call('prepareCreate', AreaNodeType::Floor->value)
        ->assertSet('type', AreaNodeType::Floor->value)
        ->assertSet('icon', 'building-office')
        ->set('name', 'First floor')
        ->set('sortOrder', 10)
        ->call('create')
        ->assertHasNoErrors()
        ->assertSee('First floor');

    $floor = AreaNode::query()
        ->where('branch_id', $branch->id)
        ->where('name', 'First floor')
        ->firstOrFail();

    Livewire::actingAs($manager)
        ->test(Areas::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->call('prepareCreate', AreaNodeType::Hall->value)
        ->set('name', 'Main hall')
        ->set('parentId', (string) $floor->id)
        ->set('sortOrder', 20)
        ->call('create')
        ->assertHasNoErrors()
        ->assertSeeInOrder(['First floor', 'Main hall']);

    $hall = AreaNode::query()
        ->where('branch_id', $branch->id)
        ->where('name', 'Main hall')
        ->firstOrFail();

    expect($floor->type)->toBe(AreaNodeType::Floor);
    expect($hall->parent_id)->toBe($floor->id);
    expect($hall->type)->toBe(AreaNodeType::Hall);
});

test('manager can rename move and disable area nodes', function () {
    [$organization, $brand, $branch, $manager] = createAreaCrudBranch();
    grantAreaCrudManageZones($manager, $organization);
    $firstFloor = AreaNode::factory()->for($branch)->create([
        'type' => AreaNodeType::Floor,
        'name' => 'First floor',
        'icon' => 'building-office',
        'sort_order' => 10,
    ]);
    $secondFloor = AreaNode::factory()->for($branch)->create([
        'type' => AreaNodeType::Floor,
        'name' => 'Second floor',
        'icon' => 'building-office',
        'sort_order' => 20,
    ]);
    $hall = AreaNode::factory()->for($branch)->create([
        'parent_id' => $firstFloor->id,
        'type' => AreaNodeType::Hall,
        'name' => 'Small hall',
        'icon' => 'squares-2x2',
        'sort_order' => 30,
    ]);

    Livewire::actingAs($manager)
        ->test(Areas::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->call('startEditing', $hall->id)
        ->assertSet('editingName', 'Small hall')
        ->set('editingName', 'VIP hall')
        ->set('editingType', AreaNodeType::VipRoom->value)
        ->set('editingIcon', 'sparkles')
        ->set('editingParentId', (string) $secondFloor->id)
        ->set('editingSortOrder', 5)
        ->set('editingIsActive', false)
        ->call('update')
        ->assertHasNoErrors()
        ->assertSee('VIP hall');

    $hall->refresh();

    expect($hall->parent_id)->toBe($secondFloor->id);
    expect($hall->name)->toBe('VIP hall');
    expect($hall->type)->toBe(AreaNodeType::VipRoom);
    expect($hall->is_active)->toBeFalse();

    Livewire::actingAs($manager)
        ->test(Areas::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->call('enable', $hall->id);

    expect($hall->fresh()->is_active)->toBeTrue();
});

test('manager can soft delete area node and keep children visible', function () {
    [$organization, $brand, $branch, $manager] = createAreaCrudBranch();
    grantAreaCrudManageZones($manager, $organization);
    $floor = AreaNode::factory()->for($branch)->create([
        'type' => AreaNodeType::Floor,
        'name' => 'Floor to remove',
        'icon' => 'building-office',
    ]);
    $hall = AreaNode::factory()->for($branch)->create([
        'parent_id' => $floor->id,
        'type' => AreaNodeType::Hall,
        'name' => 'Hall to keep',
        'icon' => 'squares-2x2',
    ]);

    Livewire::actingAs($manager)
        ->test(Areas::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->call('confirmDelete', $floor->id)
        ->call('delete')
        ->assertDontSee('Floor to remove')
        ->assertSee('Hall to keep');

    expect(AreaNode::query()->whereKey($floor->id)->exists())->toBeFalse();
    expect(AreaNode::withTrashed()->whereKey($floor->id)->firstOrFail()->trashed())->toBeTrue();
    expect($hall->fresh()->parent_id)->toBeNull();
});

test('area node cannot be moved inside its own child', function () {
    [$organization, $brand, $branch, $manager] = createAreaCrudBranch();
    grantAreaCrudManageZones($manager, $organization);
    $floor = AreaNode::factory()->for($branch)->create([
        'type' => AreaNodeType::Floor,
        'name' => 'First floor',
        'icon' => 'building-office',
    ]);
    $hall = AreaNode::factory()->for($branch)->create([
        'parent_id' => $floor->id,
        'type' => AreaNodeType::Hall,
        'name' => 'Main hall',
        'icon' => 'squares-2x2',
    ]);

    Livewire::actingAs($manager)
        ->test(Areas::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->call('startEditing', $floor->id)
        ->set('editingParentId', (string) $hall->id)
        ->call('update')
        ->assertHasErrors('editingParentId');
});

test('branch must belong to route brand and organization on area page', function () {
    [$organization, $brand, , $manager] = createAreaCrudBranch();
    grantAreaCrudManageZones($manager, $organization);
    [, , $otherBranch] = createAreaCrudBranch('Other Group', 'Other Brand');

    Livewire::actingAs($manager)
        ->test(Areas::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $otherBranch])
        ->assertForbidden();
});

function createAreaCrudBranch(string $organizationName = 'Area Group', string $brandName = 'Area Brand'): array
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

function grantAreaCrudManageZones(User $user, Organization $organization): void
{
    $membership = OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $user->id)
        ->where('status', OrganizationUserStatus::Active->value)
        ->firstOrFail();
    $permission = Permission::query()
        ->where('code', SystemPermission::ManageZones->value)
        ->firstOrFail();

    $membership->role->permissions()->updateExistingPivot($permission->id, ['enabled' => true]);
}
