<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\AreaNodeType;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\Areas;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\AreaEditor;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\Index as FloorWorkspace;
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

    Livewire::actingAs($manager)->test(AreaEditor::class, ['branchId' => $branch->id])->assertForbidden();
    grantAreaCrudManageZones($manager, $organization);
    $this->actingAs($manager)->get(route('organizations.brands.branches.areas.index', [$organization, $brand, $branch]))
        ->assertRedirect();
    Livewire::actingAs($manager)->test(FloorWorkspace::class, compact('organization', 'brand', 'branch'))
        ->assertSee(__('floor.title'))->assertSee(__('floor.areas'))->assertSee(__('floor.add_area'));
    Livewire::actingAs($manager)->test(AreaEditor::class, ['branchId' => $branch->id])->assertOk();
});

test('manager can create nested area nodes inside branch', function () {
    [$organization, $brand, $branch, $manager] = createAreaCrudBranch();
    grantAreaCrudManageZones($manager, $organization);

    Livewire::actingAs($manager)
        ->test(AreaEditor::class, ['branchId' => $branch->id])
        ->set('form.type', AreaNodeType::Floor->value)
        ->set('form.icon', 'building-office')
        ->set('form.name', 'First floor')
        ->set('form.sortOrder', 10)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('First floor');

    $floor = AreaNode::query()
        ->where('branch_id', $branch->id)
        ->where('name', 'First floor')
        ->firstOrFail();

    Livewire::actingAs($manager)
        ->test(AreaEditor::class, ['branchId' => $branch->id, 'parentId' => $floor->id])
        ->set('form.type', AreaNodeType::Hall->value)
        ->set('form.name', 'Main hall')
        ->assertSet('form.parentId', (string) $floor->id)
        ->set('form.sortOrder', 20)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('First floor')->assertSet('form.name', 'Main hall');

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
        ->test(AreaEditor::class, ['branchId' => $branch->id, 'areaId' => $hall->id])
        ->assertSet('form.name', 'Small hall')
        ->set('form.name', 'VIP hall')
        ->set('form.type', AreaNodeType::VipRoom->value)
        ->set('form.icon', 'sparkles')
        ->set('form.parentId', (string) $secondFloor->id)
        ->set('form.sortOrder', 5)
        ->set('form.isActive', false)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('VIP hall');

    $hall->refresh();

    expect($hall->parent_id)->toBe($secondFloor->id);
    expect($hall->name)->toBe('VIP hall');
    expect($hall->type)->toBe(AreaNodeType::VipRoom);
    expect($hall->is_active)->toBeFalse();

    Livewire::actingAs($manager)
        ->test(AreaEditor::class, ['branchId' => $branch->id, 'areaId' => $hall->id])
        ->set('form.isActive', true)->call('save')->assertHasNoErrors();

    expect($hall->fresh()->is_active)->toBeTrue();

    Livewire::actingAs($manager)
        ->test(AreaEditor::class, ['branchId' => $branch->id, 'areaId' => $hall->id])
        ->set('form.isActive', false)->call('save')->assertHasNoErrors();

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
        ->test(AreaEditor::class, ['branchId' => $branch->id, 'areaId' => $floor->id])
        ->call('reviewArchive')
        ->call('archive')
        ->assertDontSee('Floor to remove')
        ->assertSee('Hall to keep');

    expect(AreaNode::query()->whereKey($floor->id)->exists())->toBeFalse();
    expect(AreaNode::withTrashed()->whereKey($floor->id)->firstOrFail()->trashed())->toBeTrue();
    expect($hall->fresh()->parent_id)->toBeNull();
});

test('manager can search filter sort and paginate areas inside the current branch', function () {
    [$organization, $brand, $branch, $manager] = createAreaCrudBranch();
    grantAreaCrudManageZones($manager, $organization);

    foreach (range(1, 26) as $number) {
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
        ->test(FloorWorkspace::class, compact('organization', 'brand', 'branch'));

    expect(collect($component->viewData('areaRows'))->pluck('name')->all())
        ->toContain('Paged Hall 01')
        ->not->toContain('Paged Hall 26');

    $component
        ->call('setPage', 2, 'areasPage')
        ->assertSet('paginators.areasPage', 2);

    expect($component->viewData('areaPages')->currentPage())->toBe(2);
    expect(collect($component->viewData('areaRows'))->pluck('name')->all())
        ->toContain('Paged Hall 26')
        ->not->toContain('Paged Hall 01');

    $component
        ->set('areaSearch', 'Unique Filter')
        ->assertSee('Unique Filter Terrace');

    expect(collect($component->viewData('areaRows'))->pluck('name')->all())
        ->toContain('Unique Filter Terrace')
        ->not->toContain('Paged Hall 26');

    $component
        ->set('areaType', AreaNodeType::Terrace->value)
        ->set('areaActive', 'inactive')
        ->assertSee('Unique Filter Terrace')
        ->set('areaSearch', '')
        ->set('areaType', 'all')
        ->set('areaActive', 'all')
        ->set('areaSort', 'name_desc');

    expect($component->viewData('areaRows')[0]['name'])->toBe('Unique Filter Terrace');
});

test('manager cannot archive area node that contains a service point with an active order', function () {
    [$organization, $brand, $branch, $manager] = createAreaCrudBranch();
    grantAreaCrudManageZones($manager, $organization);
    $areaNode = AreaNode::factory()->for($branch)->create(['name' => 'Busy hall']);
    $servicePoint = ServicePoint::factory()->for($branch)->for($areaNode)->blocked()->create();
    $closedSession = TableSession::factory()->forServicePoint($servicePoint)->closed()->create();
    Order::factory()->forTableSession($closedSession)->preparing()->create();

    Livewire::actingAs($manager)
        ->test(AreaEditor::class, ['branchId' => $branch->id, 'areaId' => $areaNode->id])
        ->call('reviewArchive')
        ->call('archive')
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
        ->test(AreaEditor::class, ['branchId' => $branch->id, 'areaId' => $floor->id])
        ->set('form.parentId', (string) $hall->id)
        ->call('save')
        ->assertHasErrors('form.parentId');
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
        ->test(FloorWorkspace::class, compact('organization', 'brand', 'branch'))
        ->assertDontSee('Archived Hall')->set('areaLifecycle', 'archived')->assertSee('Archived Hall')
        ->call('openArea', $areaNode->id)->assertSet('areaEditor', (string) $areaNode->id);
    Livewire::actingAs($manager)->test(AreaEditor::class, ['branchId' => $branch->id, 'areaId' => $areaNode->id])
        ->assertSeeHtml('wire:click="restore"')->assertDontSeeHtml('wire:submit="save"')->assertDontSeeHtml('wire:click="archive"')
        ->call('restore')->assertHasNoErrors();

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
            ->test(AreaEditor::class, ['branchId' => $branch->id, 'areaId' => $foreignArea->id])
            ->call('restore');
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
