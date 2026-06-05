<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\DangerousAction;
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
        ->assertSee(__('staff.actions.update_permissions'));
});

test('staff permission page groups permissions with human labels and descriptions', function () {
    [$manager, $organization] = createPrompt16Organization();
    grantPrompt16Permission($manager, $organization, SystemPermission::ManageStaff);
    $staff = createPrompt16StaffMember($organization, SystemRole::Director);

    Livewire::actingAs($manager)
        ->test(StaffPermissions::class, ['organization' => $organization, 'staffMember' => $staff])
        ->assertSet('showTechnicalPermissionKeys', false)
        ->assertSee(__('permissions.groups.restaurant'))
        ->assertSee(__('permissions.groups.branches'))
        ->assertSee(__('permissions.groups.zones'))
        ->assertSee(__('permissions.groups.service_points'))
        ->assertSee(__('permissions.groups.qr'))
        ->assertSee(__('permissions.groups.menu'))
        ->assertSee(__('permissions.groups.orders'))
        ->assertSee(__('permissions.groups.departments'))
        ->assertSee(__('permissions.groups.payments'))
        ->assertSee(__('permissions.groups.reports'))
        ->assertSee(__('permissions.groups.staff'))
        ->assertSee(__('permissions.groups.history'))
        ->assertSee(__('permissions.labels.manage_menu'))
        ->assertSee(__('permissions.labels.change_prices'))
        ->assertSee(__('permissions.labels.confirm_orders'))
        ->assertSee(__('permissions.labels.send_to_departments'))
        ->assertSee(__('permissions.labels.manage_payments'))
        ->assertSee(__('permissions.labels.view_order_history'))
        ->assertSee(__('permissions.descriptions.manage_service_points'))
        ->assertSee(__('permissions.descriptions.manage_menu'))
        ->assertDontSee(SystemPermission::ManageServicePoints->value)
        ->assertDontSee(SystemPermission::ManageMenu->value);
});

test('superadmin can see technical permission keys in permission UI', function () {
    [, $organization] = createPrompt16Organization();
    $superadmin = createPrompt16StaffMember($organization, SystemRole::Superadmin);
    $staff = createPrompt16StaffMember($organization, SystemRole::Waiter);

    Livewire::actingAs($superadmin)
        ->test(StaffPermissions::class, ['organization' => $organization, 'staffMember' => $staff])
        ->assertSet('showTechnicalPermissionKeys', true)
        ->assertSee(SystemPermission::ManageServicePoints->value)
        ->assertSee(SystemPermission::ManageMenu->value);
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
        ->assertSee(__('permissions.states.allowed_by_override'));

    expect($staff->fresh()->hasPermission(SystemPermission::ViewOrders, $organization))->toBeTrue();
    expect((bool) $staff->fresh()->permissionOverrides()->where('permissions.id', $viewOrders->id)->firstOrFail()->pivot->enabled)->toBeTrue();

    grantRolePermission(SystemRole::Waiter, SystemPermission::ConfirmOrders);

    expect($staff->fresh()->hasPermission(SystemPermission::ConfirmOrders, $organization))->toBeTrue();

    Livewire::actingAs($manager)
        ->test(StaffPermissions::class, ['organization' => $organization, 'staffMember' => $staff])
        ->call('setPermissionState', $confirmOrders->id, 'deny')
        ->assertSee(__('permissions.states.denied_by_override'));

    expect($staff->fresh()->hasPermission(SystemPermission::ConfirmOrders, $organization))->toBeFalse();
    expect((bool) $staff->fresh()->permissionOverrides()->where('permissions.id', $confirmOrders->id)->firstOrFail()->pivot->enabled)->toBeFalse();

    Livewire::actingAs($manager)
        ->test(StaffPermissions::class, ['organization' => $organization, 'staffMember' => $staff])
        ->call('setPermissionState', $confirmOrders->id, 'default')
        ->assertSee(__('permissions.states.role_default'));

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
        ->set('criticalPermissionChangeReason', 'Temporary access reduction during audit.')
        ->call('setPermissionState', $manageStaff->id, 'deny')
        ->assertSee(__('permissions.messages.critical_permission_changed'));
});

test('critical permission changes require a reason', function () {
    [$manager, $organization] = createPrompt16Organization();
    grantPrompt16Permission($manager, $organization, SystemPermission::ManageStaff);
    $staff = createPrompt16StaffMember($organization, SystemRole::Director);
    $manageStaff = Permission::query()->where('code', SystemPermission::ManageStaff->value)->firstOrFail();

    Livewire::actingAs($manager)
        ->test(StaffPermissions::class, ['organization' => $organization, 'staffMember' => $staff])
        ->assertSee(DangerousAction::ChangeCriticalPermission->title())
        ->call('setPermissionState', $manageStaff->id, 'deny')
        ->assertHasErrors(['criticalPermissionChangeReason' => 'required']);
});

test('staff cannot edit their own permission overrides', function () {
    [$manager, $organization] = createPrompt16Organization();
    grantPrompt16Permission($manager, $organization, SystemPermission::ManageStaff);
    $manageStaff = Permission::query()->where('code', SystemPermission::ManageStaff->value)->firstOrFail();

    Livewire::actingAs($manager)
        ->test(StaffPermissions::class, ['organization' => $organization, 'staffMember' => $manager])
        ->assertSee(__('permissions.messages.self_edit_disabled'))
        ->call('setPermissionState', $manageStaff->id, 'deny')
        ->assertSee(__('permissions.messages.self_edit_disabled'));

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
        ->assertSee(__('permissions.messages.superadmin_full_access'))
        ->assertSee(__('permissions.states.allowed'));

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

    $organization->users()->syncWithoutDetachingOrFail([
        $user->id => [
            'role_id' => $roleModel->id,
            'status' => OrganizationUserStatus::Active,
            'joined_at' => now(),
        ],
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
