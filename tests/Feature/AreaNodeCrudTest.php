<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\AreaNodeType;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\Areas;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Order;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
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
    app()->setLocale('ru');
    [$organization, $brand, $branch, $manager] = createAreaCrudBranch();

    $this->actingAs($manager)
        ->get(route('organizations.brands.branches.areas.index', [$organization, $brand, $branch]))
        ->assertForbidden();

    grantAreaCrudManageZones($manager, $organization);

    $this->actingAs($manager)
        ->get(route('organizations.brands.branches.areas.index', [$organization, $brand, $branch]))
        ->assertOk()
        ->assertSee(__('ui.organizations.brands.branches.areas.areas'))
        ->assertSee(__('ui.organizations.brands.branches.areas.zony_restorana'))
        ->assertSee(__('ui.organizations.brands.branches.areas.sag_2_dobavte_zony'));
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

    Livewire::actingAs($manager)
        ->test(Areas::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->call('disable', $hall->id);

    expect($hall->fresh()->is_active)->toBeFalse();
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

test('manager can search filter sort and paginate areas inside the current branch', function () {
    [$organization, $brand, $branch, $manager] = createAreaCrudBranch();
    grantAreaCrudManageZones($manager, $organization);

    foreach (range(1, 16) as $number) {
        AreaNode::factory()->for($branch)->create([
            'type' => AreaNodeType::Hall,
            'name' => sprintf('Paged Hall %02d', $number),
            'sort_order' => $number,
            'is_active' => true,
        ]);
    }

    AreaNode::factory()->for($branch)->create([
        'type' => AreaNodeType::Terrace,
        'name' => 'Unique Filter Terrace',
        'sort_order' => 100,
        'is_active' => false,
    ]);

    $component = Livewire::actingAs($manager)
        ->test(Areas::class, compact('organization', 'brand', 'branch'));

    expect(collect($component->get('treeNodes'))->pluck('name')->all())
        ->toContain('Paged Hall 01')
        ->not->toContain('Paged Hall 16');

    $component
        ->call('setPage', 2, 'areasPage')
        ->assertSet('paginators.areasPage', 2);

    expect($component->get('displayedAreaNodes')->currentPage())->toBe(2);
    expect(collect($component->get('treeNodes'))->pluck('name')->all())
        ->toContain('Paged Hall 16')
        ->not->toContain('Paged Hall 01');

    $component
        ->set('areaSearch', 'Unique Filter')
        ->assertSee('Unique Filter Terrace');

    expect(collect($component->get('treeNodes'))->pluck('name')->all())
        ->toContain('Unique Filter Terrace')
        ->not->toContain('Paged Hall 16');

    $component
        ->set('filterType', AreaNodeType::Terrace->value)
        ->set('filterActive', 'inactive')
        ->assertSee('Unique Filter Terrace')
        ->set('areaSearch', '')
        ->set('filterType', 'all')
        ->set('filterActive', 'all')
        ->set('sort', 'name_desc');

    expect($component->get('treeNodes')[0]['name'])->toBe('Unique Filter Terrace');
});

test('manager cannot archive area node that contains a service point with an active order', function () {
    [$organization, $brand, $branch, $manager] = createAreaCrudBranch();
    grantAreaCrudManageZones($manager, $organization);
    $areaNode = AreaNode::factory()->for($branch)->create(['name' => 'Busy hall']);
    $servicePoint = ServicePoint::factory()->for($branch)->for($areaNode)->blocked()->create();
    $closedSession = TableSession::factory()->forServicePoint($servicePoint)->closed()->create();
    Order::factory()->forTableSession($closedSession)->preparing()->create();

    Livewire::actingAs($manager)
        ->test(Areas::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->call('confirmDelete', $areaNode->id)
        ->call('delete')
        ->assertHasErrors('structureDeletion');

    expect($areaNode->fresh())->not->toBeNull();
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

test('zone manager is authorized to restore an archived area node', function () {
    [$organization, , $branch, $manager] = createAreaCrudBranch();
    grantAreaCrudManageZones($manager, $organization);
    $areaNode = AreaNode::factory()->for($branch)->create();
    $areaNode->deleteOrFail();

    expect(Gate::forUser($manager)->allows('restore', $areaNode))->toBeTrue()
        ->and(Gate::forUser($manager)->allows('update', $areaNode))->toBeFalse()
        ->and(Gate::forUser($manager)->allows('delete', $areaNode))->toBeFalse();
});

test('zone manager can view and restore an archived area without a page reload', function () {
    [$organization, $brand, $branch, $manager] = createAreaCrudBranch();
    grantAreaCrudManageZones($manager, $organization);
    $areaNode = AreaNode::factory()->for($branch)->create(['name' => 'Archived Hall']);
    $areaNode->deleteOrFail();

    Livewire::actingAs($manager)
        ->test(Areas::class, compact('organization', 'brand', 'branch'))
        ->assertDontSee('Archived Hall')
        ->set('lifecycle', 'archived')
        ->assertSee('Archived Hall')
        ->assertSeeHtml('wire:click="restore('.$areaNode->id.')"')
        ->assertDontSeeHtml('wire:click="startEditing('.$areaNode->id.')"')
        ->assertDontSeeHtml('wire:click="confirmDelete('.$areaNode->id.')"')
        ->call('restore', $areaNode->id)
        ->assertHasNoErrors();

    expect($areaNode->fresh())->not->toBeNull();
});

test('livewire payload cannot restore an area from another branch', function () {
    [$organization, $brand, $branch, $manager] = createAreaCrudBranch();
    grantAreaCrudManageZones($manager, $organization);
    [, , $foreignBranch] = createAreaCrudBranch('Foreign Group', 'Foreign Brand');
    $foreignArea = AreaNode::factory()->for($foreignBranch)->create();
    $foreignArea->deleteOrFail();

    $caughtException = null;

    try {
        Livewire::actingAs($manager)
            ->test(Areas::class, compact('organization', 'brand', 'branch'))
            ->call('restore', $foreignArea->id);
    } catch (Throwable $exception) {
        $caughtException = $exception;
    }

    expect($caughtException)->toBeInstanceOf(ModelNotFoundException::class)
        ->and(AreaNode::withTrashed()->findOrFail($foreignArea->id)->trashed())->toBeTrue();
});

function createAreaCrudBranch(string $organizationName = 'Area Group', string $brandName = 'Area Brand'): array
{
    $manager = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($manager, ['name' => $organizationName]);
    $restrictedRole = Role::query()
        ->where('code', SystemRole::Waiter->value)
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
