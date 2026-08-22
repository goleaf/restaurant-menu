<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\Staff\Index as BranchStaffIndex;
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

    BranchUser::factory()->create([
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
        ->test(BranchStaffIndex::class, [
            'organization' => $organization,
            'brand' => $brand,
            'branch' => $branch,
        ])
        ->assertSee('Waiter zones')
        ->assertSee('Main Hall')
        ->assertSee('Terrace')
        ->set('areaAssignments.'.$waiter->id, [(string) $mainHall->id])
        ->call('saveAreaAssignments', $waiter->id)
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

test('waiter dashboard filters to assigned zones and can show all zones', function () {
    [$manager, $organization, , $branch] = createPrompt112Branch(branchName: 'Zone Filter Branch');
    $waiter = User::factory()->create(['name' => 'Assigned Waiter']);
    attachPrompt112Waiter($waiter, $organization, $branch, $manager);

    $mainHall = AreaNode::factory()->for($branch)->create(['name' => 'Assigned Hall']);
    $terrace = AreaNode::factory()->for($branch)->create(['name' => 'Hidden Terrace']);

    $assignment = new AreaNodeWaiter;
    $assignment->forceFill([
        'organization_id' => $organization->id,
        'branch_id' => $branch->id,
        'area_node_id' => $mainHall->id,
        'user_id' => $waiter->id,
        'assigned_by_user_id' => $manager->id,
        'assigned_at' => now(),
    ])->save();

    ServicePoint::factory()
        ->for($branch)
        ->for($mainHall, 'areaNode')
        ->create(['name' => 'Assigned Table']);

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
