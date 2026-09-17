<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Staff\SyncWaiterAreaAssignmentsAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Staff\Show as EmployeeCard;
use App\Livewire\Waiter\Dashboard as WaiterDashboard;
use App\Models\AreaNode;
use App\Models\AreaNodeWaiter;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\Staff\StaffQueryService;
use Database\Seeders\SystemPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('branch staff page can assign waiter to branch zones', function () {
    [$manager, $organization, $brand, $branch] = createPrompt112Branch();
    grantPrompt112Permission($manager, $organization, SystemPermission::ManageStaff);

    $waiterRole = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    $waiter = User::factory()->create([
        'name' => 'Zone Waiter',
        'email' => 'zone-waiter@example.test',
    ]);

    $organizationMember = OrganizationUser::factory()->forOrganization($organization)->forUser($waiter)->forRole($waiterRole)->active()->create();
    $membership = BranchUser::factory()->create([
        'organization_id' => $organization->id,
        'branch_id' => $branch->id,
        'user_id' => $waiter->id,
        'role_id' => $waiterRole->id,
        'status' => OrganizationUserStatus::Active,
        'assigned_by_user_id' => $manager->id,
    ]);

    $mainHall = AreaNode::factory()->for($branch)->create(['name' => 'Main Hall']);
    $terrace = AreaNode::factory()->for($branch)->create(['name' => 'Terrace']);

    Livewire::actingAs($manager)
        ->test(EmployeeCard::class, [
            'organization' => $organization,
            'member' => $organizationMember,
            'brand' => $brand,
            'branch' => $branch,
        ])
        ->assertDontSee('Main Hall')
        ->assertDontSee('Terrace')
        ->call('selectSection', 'areas')
        ->assertSee('Main Hall')
        ->assertSee('Terrace')
        ->set('assignmentForm.areaIds', [(string) $mainHall->id])
        ->call('previewAreaAssignments')
        ->call('saveAreaAssignments')
        ->assertHasNoErrors()
        ->assertSee('Zone Waiter');

    expect(AreaNodeWaiter::query()
        ->where('branch_id', $branch->id)
        ->where('area_node_id', $mainHall->id)
        ->where('user_id', $waiter->id)
        ->exists())->toBeTrue()
        ->and(AreaNodeWaiter::query()
            ->where('branch_id', $branch->id)
            ->where('area_node_id', $terrace->id)
            ->where('user_id', $waiter->id)
            ->exists())->toBeFalse();
});

test('staff zone lookup reads ordered ids only for the selected waiter within one branch', function (): void {
    $branch = Branch::factory()->create();
    $firstWaiter = User::factory()->create();
    $secondWaiter = User::factory()->create();
    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    $membership = BranchUser::factory()->forBranch($branch)->forUser($firstWaiter)->forRole($role)->active()->create();
    $firstArea = AreaNode::factory()->for($branch)->create();
    $secondArea = AreaNode::factory()->for($branch)->create();

    AreaNodeWaiter::factory()->createMany([
        ['organization_id' => $branch->organization_id, 'branch_id' => $branch->id, 'area_node_id' => $secondArea->id, 'user_id' => $secondWaiter->id],
        ['organization_id' => $branch->organization_id, 'branch_id' => $branch->id, 'area_node_id' => $secondArea->id, 'user_id' => $firstWaiter->id],
        ['organization_id' => $branch->organization_id, 'branch_id' => $branch->id, 'area_node_id' => $firstArea->id, 'user_id' => $firstWaiter->id],
    ]);
    AreaNodeWaiter::factory()->create(['user_id' => $firstWaiter->id]);

    $queryCount = countDatabaseQueries(function () use ($branch, $membership, $firstArea, $secondArea): void {
        $snapshot = app(StaffQueryService::class)->assignmentSnapshot($branch, $membership);
        expect($snapshot['ids'])->toBe([$firstArea->id, $secondArea->id])
            ->and($snapshot['fingerprint'])->toHaveLength(64);
    });

    expect($queryCount)->toBe(3);
});

test('staff zone lookup returns an empty array when a branch has no assignments', function (): void {
    $branch = Branch::factory()->create();
    AreaNodeWaiter::factory()->create();

    $membership = BranchUser::factory()->forBranch($branch)->active()->create();
    expect(app(StaffQueryService::class)->assignmentSnapshot($branch, $membership)['ids'])->toBe([]);
});

test('waiter dashboard filters to assigned zones and can show all zones', function () {
    [$manager, $organization, , $branch] = createPrompt112Branch(branchName: 'Zone Filter Branch');
    $waiter = User::factory()->create(['name' => 'Assigned Waiter']);
    attachPrompt112Waiter($waiter, $organization, $branch, $manager);

    $mainHall = AreaNode::factory()->for($branch)->create(['name' => 'Assigned Hall']);
    $secondHall = AreaNode::factory()->for($branch)->create(['name' => 'Second Assigned Hall']);
    $terrace = AreaNode::factory()->for($branch)->create(['name' => 'Hidden Terrace']);
    $otherWaiter = User::factory()->create();
    attachPrompt112Waiter($otherWaiter, $organization, $branch, $manager);
    AreaNodeWaiter::factory()->create([
        'organization_id' => $organization->id,
        'branch_id' => $branch->id,
        'area_node_id' => $terrace->id,
        'user_id' => $otherWaiter->id,
        'assigned_by_user_id' => $manager->id,
    ]);

    AreaNodeWaiter::factory()->state([
        'organization_id' => $organization->id,
        'branch_id' => $branch->id,
        'user_id' => $waiter->id,
        'assigned_by_user_id' => $manager->id,
    ])->createMany([
        ['area_node_id' => $secondHall->id],
        ['area_node_id' => $mainHall->id],
    ]);

    ServicePoint::factory()
        ->for($branch)
        ->for($mainHall, 'areaNode')
        ->create(['name' => 'Assigned Table']);

    ServicePoint::factory()
        ->for($branch)
        ->for($secondHall, 'areaNode')
        ->create(['name' => 'Second Assigned Table']);

    ServicePoint::factory()
        ->for($branch)
        ->for($terrace, 'areaNode')
        ->create(['name' => 'Hidden Table']);

    Livewire::actingAs($waiter)
        ->test(WaiterDashboard::class)
        ->assertSet('zoneScope', 'mine')
        ->assertSee('My zones')
        ->assertSee('Assigned Hall')
        ->assertSee('Assigned Table')
        ->assertSee('Second Assigned Hall')
        ->assertSee('Second Assigned Table')
        ->assertDontSee('Hidden Terrace')
        ->assertDontSee('Hidden Table')
        ->set('zoneScope', 'all')
        ->call('refreshDashboard')
        ->assertSee('Hidden Terrace')
        ->assertSee('Hidden Table');
});

function createPrompt112Branch(string $branchName = 'Prompt 112 Branch'): array
{
    $manager = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($manager, ['name' => 'Prompt 112 Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Prompt 112 Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => $branchName,
            'city' => 'Vilnius',
        ]);

    return [$manager->fresh(), $organization, $brand, $branch];
}

function grantPrompt112Permission(User $user, Organization $organization, SystemPermission $permissionCode): void
{
    $membership = OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $user->id)
        ->firstOrFail();
    $permission = Permission::query()
        ->where('code', $permissionCode->value)
        ->firstOrFail();

    $membership->role->permissions()->updateExistingPivot($permission->id, ['enabled' => true]);
}

function attachPrompt112Waiter(User $waiter, Organization $organization, Branch $branch, User $assignedBy): void
{
    $waiterRole = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();

    grantPrompt112PermissionToRole($waiterRole, SystemPermission::ViewOrders);

    $organization->users()->syncWithoutDetachingOrFail([
        $waiter->id => [
            'role_id' => $waiterRole->id,
            'status' => OrganizationUserStatus::Active->value,
            'joined_at' => now(),
            'invited_by_user_id' => $assignedBy->id,
        ],
    ]);

    BranchUser::factory()->create([
        'organization_id' => $organization->id,
        'branch_id' => $branch->id,
        'user_id' => $waiter->id,
        'role_id' => $waiterRole->id,
        'status' => OrganizationUserStatus::Active,
        'assigned_by_user_id' => $assignedBy->id,
    ]);
}

function grantPrompt112PermissionToRole(Role $role, SystemPermission $permissionCode): void
{
    $permission = Permission::query()
        ->where('code', $permissionCode->value)
        ->firstOrFail();

    $role->permissions()->updateExistingPivot($permission->id, ['enabled' => true]);
}

test('waiter area selection follows exact parent child assignment and a moved table', function (): void {
    [$manager, $organization, , $branch] = createPrompt112Branch();
    grantPrompt112Permission($manager, $organization, SystemPermission::ManageStaff);
    $waiter = User::factory()->create();
    attachPrompt112Waiter($waiter, $organization, $branch, $manager);
    $membership = BranchUser::query()->where('branch_id', $branch->id)->where('user_id', $waiter->id)->firstOrFail();
    $parent = AreaNode::factory()->forBranch($branch)->create(['name' => 'Parent zone']);
    $child = AreaNode::factory()->forBranch($branch)->withParent($parent)->create(['name' => 'Child zone']);
    $table = ServicePoint::factory()->forBranch($branch)->for($parent, 'areaNode')->create(['name' => 'Table moved between zones']);
    $childTable = ServicePoint::factory()->forBranch($branch)->for($child, 'areaNode')->create(['name' => 'Child only table']);
    $assign = app(SyncWaiterAreaAssignmentsAction::class);
    $assign->handle($branch, $membership, $manager, [$parent->id]);
    $component = Livewire::actingAs($waiter)->test(WaiterDashboard::class)
        ->assertSee($table->name)->assertDontSee($childTable->name);
    $table->forceFill(['area_node_id' => $child->id])->save();
    $component->call('refreshDashboard')->assertDontSee($table->name)->assertDontSee($childTable->name);
    $assign->handle($branch, $membership, $manager, [$child->id]);
    $component->call('refreshDashboard')->assertSee($table->name)->assertSee($childTable->name);
    $assign->handle($branch, $membership, $manager, []);
    $component->call('refreshDashboard')->assertSee($table->name)->assertSee($childTable->name);
});
