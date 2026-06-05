<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\Staff\Index as BranchStaffIndex;
use App\Livewire\Organizations\Staff\Index as OrganizationStaffIndex;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('branch users table stores branch staff assignments', function () {
    expect(Schema::hasTable('branch_users'))->toBeTrue();
    expect(Schema::hasColumns('branch_users', [
        'organization_id',
        'branch_id',
        'user_id',
        'role_id',
        'status',
        'assigned_at',
        'assigned_by_user_id',
    ]))->toBeTrue();
});

test('organization staff page requires manage staff permission', function () {
    [$manager, $organization] = createOrganizationForStaff();

    $this->actingAs($manager)
        ->get(route('organizations.staff.index', $organization))
        ->assertForbidden();

    grantManageStaff($manager, $organization);

    $this->actingAs($manager)
        ->get(route('organizations.staff.index', $organization))
        ->assertOk()
        ->assertSee(__('staff.organization_access'));
});

test('branch staff page requires manage staff permission', function () {
    [$manager, $organization] = createOrganizationForStaff();
    [$brand, $branch] = createBranchForStaff($organization);

    $this->actingAs($manager)
        ->get(route('organizations.brands.branches.staff.index', [$organization, $brand, $branch]))
        ->assertForbidden();

    grantManageStaff($manager, $organization);

    $this->actingAs($manager)
        ->get(route('organizations.brands.branches.staff.index', [$organization, $brand, $branch]))
        ->assertOk()
        ->assertSee(__('staff.branch_access'));
});

test('organization staff page can manually add and toggle a staff member', function () {
    [$manager, $organization] = createOrganizationForStaff();
    grantManageStaff($manager, $organization);
    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();

    Livewire::actingAs($manager)
        ->test(OrganizationStaffIndex::class, ['organization' => $organization])
        ->set('manualName', 'Manual Waiter')
        ->set('manualEmail', 'manual-waiter@example.test')
        ->set('manualRoleId', $role->id)
        ->call('addManualStaffMember')
        ->assertSee('Manual Waiter');

    $staffUser = User::query()
        ->where('email', 'manual-waiter@example.test')
        ->firstOrFail();
    $membership = OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $staffUser->id)
        ->firstOrFail();

    expect($membership->role_id)->toBe($role->id);
    expect($membership->status)->toBe(OrganizationUserStatus::Active);

    Livewire::actingAs($manager)
        ->test(OrganizationStaffIndex::class, ['organization' => $organization])
        ->set('staffDeactivationReason', 'No longer works this venue.')
        ->call('deactivateMember', $membership->id);

    expect($membership->fresh()->status)->toBe(OrganizationUserStatus::Suspended);

    Livewire::actingAs($manager)
        ->test(OrganizationStaffIndex::class, ['organization' => $organization])
        ->call('activateMember', $membership->id);

    expect($membership->fresh()->status)->toBe(OrganizationUserStatus::Active);
});

test('organization staff page can create invite link and invite code', function () {
    [$manager, $organization] = createOrganizationForStaff();
    grantManageStaff($manager, $organization);
    $role = Role::query()->where('code', SystemRole::Director->value)->firstOrFail();

    Livewire::actingAs($manager)
        ->test(OrganizationStaffIndex::class, ['organization' => $organization])
        ->set('inviteEmail', 'director@example.test')
        ->set('invitePhone', '+37060000001')
        ->set('inviteRoleId', $role->id)
        ->call('createInviteLink')
        ->assertSee(__('staff.invite_link'))
        ->call('createInviteCode')
        ->assertSee(__('staff.invite_code'));

    $invitation = Invitation::query()
        ->where('organization_id', $organization->id)
        ->where('email', 'director@example.test')
        ->latest('id')
        ->firstOrFail();

    expect($invitation->role_id)->toBe($role->id);
    expect($invitation->phone)->toBe('+37060000001');
    expect($invitation->invite_token)->not->toBeEmpty();
    expect($invitation->invite_code)->not->toBeEmpty();
});

test('branch staff page can manually add and toggle a branch staff member', function () {
    [$manager, $organization] = createOrganizationForStaff();
    grantManageStaff($manager, $organization);
    [$brand, $branch] = createBranchForStaff($organization);
    $directorRole = Role::query()->where('code', SystemRole::Director->value)->firstOrFail();
    $role = Role::query()->where('code', SystemRole::Bartender->value)->firstOrFail();

    Livewire::actingAs($manager)
        ->test(OrganizationStaffIndex::class, ['organization' => $organization])
        ->set('manualName', 'Existing Director')
        ->set('manualEmail', 'existing-director@example.test')
        ->set('manualRoleId', $directorRole->id)
        ->call('addManualStaffMember');

    Livewire::actingAs($manager)
        ->test(BranchStaffIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->set('manualName', 'Existing Director')
        ->set('manualEmail', 'existing-director@example.test')
        ->set('manualRoleId', $role->id)
        ->call('addManualStaffMember')
        ->assertSee('Existing Director');

    $staffUser = User::query()
        ->where('email', 'existing-director@example.test')
        ->firstOrFail();
    $organizationMembership = OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $staffUser->id)
        ->firstOrFail();
    $branchUser = BranchUser::query()
        ->where('branch_id', $branch->id)
        ->where('user_id', $staffUser->id)
        ->firstOrFail();

    expect($organizationMembership->role_id)->toBe($directorRole->id);
    expect($branchUser->organization_id)->toBe($organization->id);
    expect($branchUser->role_id)->toBe($role->id);
    expect($branchUser->status)->toBe(OrganizationUserStatus::Active);

    Livewire::actingAs($manager)
        ->test(BranchStaffIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->set('staffDeactivationReason', 'Moved to another branch.')
        ->call('deactivateMember', $branchUser->id);

    expect($branchUser->fresh()->status)->toBe(OrganizationUserStatus::Suspended);

    Livewire::actingAs($manager)
        ->test(BranchStaffIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->call('activateMember', $branchUser->id);

    expect($branchUser->fresh()->status)->toBe(OrganizationUserStatus::Active);
});

test('branch staff page can create branch scoped invite link and code', function () {
    [$manager, $organization] = createOrganizationForStaff();
    grantManageStaff($manager, $organization);
    [$brand, $branch] = createBranchForStaff($organization);
    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();

    Livewire::actingAs($manager)
        ->test(BranchStaffIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->set('inviteEmail', 'branch-invite@example.test')
        ->set('invitePhone', '+37060000002')
        ->set('inviteRoleId', $role->id)
        ->call('createInviteLink')
        ->assertSee(__('staff.invite_link'))
        ->call('createInviteCode')
        ->assertSee(__('staff.invite_code'));

    $invitation = Invitation::query()
        ->where('organization_id', $organization->id)
        ->where('brand_id', $brand->id)
        ->where('branch_id', $branch->id)
        ->where('email', 'branch-invite@example.test')
        ->latest('id')
        ->firstOrFail();

    expect($invitation->role_id)->toBe($role->id);
    expect($invitation->phone)->toBe('+37060000002');
    expect($invitation->invite_token)->not->toBeEmpty();
    expect($invitation->invite_code)->not->toBeEmpty();
});

function createOrganizationForStaff(): array
{
    $manager = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($manager, ['name' => 'Staff Group']);
    $role = Role::query()->where('code', SystemRole::ShiftManager->value)->firstOrFail();

    $manager->roles()->sync([$role->id]);
    OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $manager->id)
        ->firstOrFail()
        ->forceFill(['role_id' => $role->id])
        ->save();

    return [$manager->fresh(), $organization];
}

function createBranchForStaff(Organization $organization): array
{
    $brand = Brand::factory()->for($organization)->create(['name' => 'Staff Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create(['name' => 'Staff Branch']);

    return [$brand, $branch];
}

function grantManageStaff(User $user, Organization $organization): void
{
    $membership = OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $user->id)
        ->firstOrFail();
    $permission = Permission::query()
        ->where('code', SystemPermission::ManageStaff->value)
        ->firstOrFail();

    $membership->role->permissions()->updateExistingPivot($permission->id, ['enabled' => true]);
}
