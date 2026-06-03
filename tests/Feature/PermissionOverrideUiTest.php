<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Staff\Permissions as StaffPermissions;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('staff permission page requires manage staff permission', function () {
    [$manager, $organization] = createPrompt16Organization();
    $staff = createPrompt16StaffMember($organization, SystemRole::Waiter);

    $this->actingAs($manager)
        ->get(route('organizations.staff.permissions', [$organization, $staff]))
        ->assertForbidden();

    grantPrompt16Permission($manager, $organization, SystemPermission::ManageStaff);

    $this->actingAs($manager)
        ->get(route('organizations.staff.permissions', [$organization, $staff]))
        ->assertOk()
        ->assertSee('Permission overrides');
});

test('staff permission overrides can allow deny and return to default', function () {
    [$manager, $organization] = createPrompt16Organization();
    grantPrompt16Permission($manager, $organization, SystemPermission::ManageStaff);
    $staff = createPrompt16StaffMember($organization, SystemRole::Waiter);
    $viewOrders = Permission::query()->where('code', SystemPermission::ViewOrders->value)->firstOrFail();
    $confirmOrders = Permission::query()->where('code', SystemPermission::ConfirmOrders->value)->firstOrFail();

    expect($staff->fresh()->hasPermission(SystemPermission::ViewOrders, $organization))->toBeFalse();

    Livewire::actingAs($manager)
        ->test(StaffPermissions::class, ['organization' => $organization, 'staffMember' => $staff])
        ->call('setPermissionState', $viewOrders->id, 'allow')
        ->assertSee('Allowed by override');

    expect($staff->fresh()->hasPermission(SystemPermission::ViewOrders, $organization))->toBeTrue();
    expect((bool) $staff->fresh()->permissionOverrides()->where('permissions.id', $viewOrders->id)->firstOrFail()->pivot->enabled)->toBeTrue();

    grantRolePermission(SystemRole::Waiter, SystemPermission::ConfirmOrders);

    expect($staff->fresh()->hasPermission(SystemPermission::ConfirmOrders, $organization))->toBeTrue();

    Livewire::actingAs($manager)
        ->test(StaffPermissions::class, ['organization' => $organization, 'staffMember' => $staff])
        ->call('setPermissionState', $confirmOrders->id, 'deny')
        ->assertSee('Denied by override');

    expect($staff->fresh()->hasPermission(SystemPermission::ConfirmOrders, $organization))->toBeFalse();
    expect((bool) $staff->fresh()->permissionOverrides()->where('permissions.id', $confirmOrders->id)->firstOrFail()->pivot->enabled)->toBeFalse();

    Livewire::actingAs($manager)
        ->test(StaffPermissions::class, ['organization' => $organization, 'staffMember' => $staff])
        ->call('setPermissionState', $confirmOrders->id, 'default')
        ->assertSee('Role default');

    expect($staff->fresh()->hasPermission(SystemPermission::ConfirmOrders, $organization))->toBeTrue();
    expect($staff->fresh()->permissionOverrides()->where('permissions.id', $confirmOrders->id)->exists())->toBeFalse();
});

test('critical permission changes show a warning', function () {
    [$manager, $organization] = createPrompt16Organization();
    grantPrompt16Permission($manager, $organization, SystemPermission::ManageStaff);
    $staff = createPrompt16StaffMember($organization, SystemRole::Director);
    $manageStaff = Permission::query()->where('code', SystemPermission::ManageStaff->value)->firstOrFail();

    Livewire::actingAs($manager)
        ->test(StaffPermissions::class, ['organization' => $organization, 'staffMember' => $staff])
        ->call('setPermissionState', $manageStaff->id, 'deny')
        ->assertSee('Critical permission changed');
});

test('staff cannot edit their own permission overrides', function () {
    [$manager, $organization] = createPrompt16Organization();
    grantPrompt16Permission($manager, $organization, SystemPermission::ManageStaff);
    $manageStaff = Permission::query()->where('code', SystemPermission::ManageStaff->value)->firstOrFail();

    Livewire::actingAs($manager)
        ->test(StaffPermissions::class, ['organization' => $organization, 'staffMember' => $manager])
        ->assertSee('Self-edit is disabled')
        ->call('setPermissionState', $manageStaff->id, 'deny')
        ->assertSee('Self-edit is disabled');

    expect($manager->fresh()->permissionOverrides()->where('permissions.id', $manageStaff->id)->exists())->toBeFalse();
    expect($manager->fresh()->hasPermission(SystemPermission::ManageStaff, $organization))->toBeTrue();
});

test('regular staff cannot open their own permission page without manage staff', function () {
    [, $organization] = createPrompt16Organization();
    $staff = createPrompt16StaffMember($organization, SystemRole::Waiter);

    $this->actingAs($staff)
        ->get(route('organizations.staff.permissions', [$organization, $staff]))
        ->assertForbidden();
});

test('superadmin staff member keeps full computed access', function () {
    [$manager, $organization] = createPrompt16Organization();
    grantPrompt16Permission($manager, $organization, SystemPermission::ManageStaff);
    $superadmin = createPrompt16StaffMember($organization, SystemRole::Superadmin);

    Livewire::actingAs($manager)
        ->test(StaffPermissions::class, ['organization' => $organization, 'staffMember' => $superadmin])
        ->assertSee('Superadmin always has full access')
        ->assertSee('Allowed');

    expect($superadmin->fresh()->hasPermission(SystemPermission::ExportData, $organization))->toBeTrue();
});

function createPrompt16Organization(): array
{
    $manager = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($manager, ['name' => 'Permission Overrides Group']);

    return [$manager->fresh(), $organization];
}

function createPrompt16StaffMember(Organization $organization, SystemRole $role): User
{
    $user = User::factory()->create();
    $roleModel = Role::query()->where('code', $role->value)->firstOrFail();

    $user->roles()->syncWithoutDetachingOrFail([$roleModel->id]);

    OrganizationUser::query()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role_id' => $roleModel->id,
        'status' => OrganizationUserStatus::Active,
        'joined_at' => now(),
    ]);

    return $user;
}

function grantPrompt16Permission(User $user, Organization $organization, SystemPermission $permission): void
{
    $membership = OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $user->id)
        ->firstOrFail();
    $permissionModel = Permission::query()
        ->where('code', $permission->value)
        ->firstOrFail();

    $membership->role->permissions()->updateExistingPivot($permissionModel->id, ['enabled' => true]);
}

function grantRolePermission(SystemRole $role, SystemPermission $permission): void
{
    $roleModel = Role::query()->where('code', $role->value)->firstOrFail();
    $permissionModel = Permission::query()->where('code', $permission->value)->firstOrFail();

    $roleModel->permissions()->updateExistingPivot($permissionModel->id, ['enabled' => true]);
}
