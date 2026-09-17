<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Staff\Show as StaffPermissions;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('staff permission page requires manage permissions permission', function () {
    [$manager, $organization] = createPrompt16Organization();
    $staff = createPrompt16StaffMember($organization, SystemRole::Waiter);

    $this->actingAs($manager)
        ->get(route('organizations.staff.permissions', [$organization, $staff]))
        ->assertForbidden();

    grantPrompt16Permission($manager, $organization, SystemPermission::ManagePermissions);

    $this->actingAs($manager)
        ->get(route('organizations.staff.permissions', [$organization, $staff]))
        ->assertOk()
        ->assertSee(__('team.card.access'));
});

test('staff permission page groups permissions with human labels and descriptions', function () {
    [$manager, $organization] = createPrompt16Organization();
    grantPrompt16Permission($manager, $organization, SystemPermission::ManagePermissions);
    $staff = createPrompt16StaffMember($organization, SystemRole::Waiter);

    permissionCard($manager, $organization, $staff)
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

test('organization access explanations follow actual policies when a capability alone differs', function (): void {
    [$manager, $organization] = createPrompt16Organization();
    grantPrompt16Permission($manager, $organization, SystemPermission::ManagePermissions);
    $staff = createPrompt16StaffMember($organization, SystemRole::Waiter);
    $edit = Permission::query()->where('code', SystemPermission::EditRestaurant->value)->firstOrFail();
    $view = Permission::query()->where('code', SystemPermission::ViewRestaurant->value)->firstOrFail();
    $staff->permissionOverrides($organization->id)->attach($edit, ['enabled' => true]);
    $staff->permissionOverrides($organization->id)->attach($view, ['enabled' => false]);

    $component = permissionCard($manager, $organization, $staff);
    $rows = collect($component->viewData('permissionRows'))->keyBy('code');

    expect($rows[SystemPermission::EditRestaurant->value]['effective_allowed'])
        ->toBe(Gate::forUser($staff)->inspect('update', $organization)->allowed())
        ->and($rows[SystemPermission::ViewRestaurant->value]['effective_allowed'])
        ->toBe(Gate::forUser($staff)->inspect('view', $organization)->allowed())
        ->and($rows[SystemPermission::EditRestaurant->value]['effective_reason'])->toBe(__('permissions.sources.policy_denied'))
        ->and($rows[SystemPermission::ViewRestaurant->value]['effective_reason'])->toBe(__('permissions.sources.policy_allowed'));
});

test('an opened permission editor rechecks a revoked administrator before mutation', function (): void {
    [$manager, $organization] = createPrompt16Organization();
    grantPrompt16Permission($manager, $organization, SystemPermission::ManagePermissions);
    $staff = createPrompt16StaffMember($organization, SystemRole::Waiter);
    $permission = Permission::query()->where('code', SystemPermission::ManageMenu->value)->firstOrFail();
    $component = permissionCard($manager, $organization, $staff)->call('openPermissions')
        ->set('permissionForm.states.'.$permission->id, 'allow')->call('previewPermissions');
    OrganizationUser::query()->where('organization_id', $organization->id)->where('user_id', $manager->id)
        ->firstOrFail()->forceFill(['status' => OrganizationUserStatus::Suspended])->save();

    $component->call('applyPermissions')->assertForbidden();
    expect($staff->permissionOverrides($organization->id)->exists())->toBeFalse();
});

test('superadmin can see technical permission keys in permission UI', function () {
    [, $organization] = createPrompt16Organization();
    $superadmin = createPrompt16StaffMember($organization, SystemRole::Superadmin);
    $staff = createPrompt16StaffMember($organization, SystemRole::Waiter);

    permissionCard($superadmin, $organization, $staff)
        ->assertSee(SystemPermission::ManageServicePoints->value)
        ->assertSee(SystemPermission::ManageMenu->value);
});

test('staff permission overrides can allow deny and return to default', function () {
    [$manager, $organization] = createPrompt16Organization();
    grantPrompt16Permission($manager, $organization, SystemPermission::ManagePermissions);
    $staff = createPrompt16StaffMember($organization, SystemRole::Waiter);
    $changePrices = Permission::query()->where('code', SystemPermission::ChangePrices->value)->firstOrFail();
    $confirmOrders = Permission::query()->where('code', SystemPermission::ConfirmOrders->value)->firstOrFail();

    expect($staff->fresh()->hasPermission(SystemPermission::ChangePrices, $organization))->toBeFalse();

    permissionCard($manager, $organization, $staff)
        ->call('openPermissions')->set('permissionForm.states.'.$changePrices->id, 'allow')
        ->call('previewPermissions')->call('applyPermissions')->assertHasNoErrors()
        ->assertSee(__('team.card.state_allow'));

    expect($staff->fresh()->hasPermission(SystemPermission::ChangePrices, $organization))->toBeTrue();
    expect((bool) $staff->fresh()->permissionOverrides($organization->id)->where('permissions.id', $changePrices->id)->firstOrFail()->pivot->enabled)->toBeTrue();

    expect($staff->fresh()->hasPermission(SystemPermission::ConfirmOrders, $organization))->toBeTrue();

    permissionCard($manager, $organization, $staff)
        ->call('openPermissions')->set('permissionForm.states.'.$confirmOrders->id, 'deny')
        ->call('previewPermissions')->call('applyPermissions')->assertHasNoErrors()
        ->assertSee(__('team.card.state_deny'));

    expect($staff->fresh()->hasPermission(SystemPermission::ConfirmOrders, $organization))->toBeFalse();
    expect((bool) $staff->fresh()->permissionOverrides($organization->id)->where('permissions.id', $confirmOrders->id)->firstOrFail()->pivot->enabled)->toBeFalse();

    permissionCard($manager, $organization, $staff)
        ->call('openPermissions')->set('permissionForm.states.'.$confirmOrders->id, 'default')
        ->call('previewPermissions')->call('applyPermissions')->assertHasNoErrors()
        ->assertSee(__('team.card.state_default'));

    expect($staff->fresh()->hasPermission(SystemPermission::ConfirmOrders, $organization))->toBeTrue();
    expect($staff->fresh()->permissionOverrides($organization->id)->where('permissions.id', $confirmOrders->id)->exists())->toBeFalse();
});

test('critical permission changes show a warning', function () {
    [$manager, $organization] = createPrompt16Organization();
    grantPrompt16Permission($manager, $organization, SystemPermission::ManagePermissions);
    $staff = createPrompt16StaffMember($organization, SystemRole::Waiter);
    $manageStaff = Permission::query()->where('code', SystemPermission::ManageStaff->value)->firstOrFail();

    $component = permissionCard($manager, $organization, $staff)
        ->call('openPermissions')
        ->set('permissionForm.states.'.$manageStaff->id, 'deny')
        ->set('permissionForm.reason', 'Temporary access reduction during audit.')
        ->set('permissionForm.confirmed', true)
        ->call('previewPermissions')
        ->assertSet('preview.requires_confirmation', true)
        ->assertSee(__('permissions.draft.confirm'));
    expect($staff->permissionOverrides($organization->id)->exists())->toBeFalse();
    $component->call('applyPermissions')->assertHasNoErrors()
        ->assertSee(__('staff.workspace.updated'));
    expect($staff->permissionOverrides($organization->id)->where('permissions.id', $manageStaff->id)->firstOrFail()->pivot->enabled)->toBeFalse();
});

test('critical permission changes require a reason', function () {
    [$manager, $organization] = createPrompt16Organization();
    grantPrompt16Permission($manager, $organization, SystemPermission::ManagePermissions);
    $staff = createPrompt16StaffMember($organization, SystemRole::Waiter);
    $manageStaff = Permission::query()->where('code', SystemPermission::ManageStaff->value)->firstOrFail();

    permissionCard($manager, $organization, $staff)
        ->call('openPermissions')
        ->set('permissionForm.states.'.$manageStaff->id, 'deny')
        ->set('permissionForm.confirmed', true)
        ->call('previewPermissions')->assertSet('preview.requires_confirmation', true)
        ->call('applyPermissions')
        ->assertHasErrors(['permissionForm.reason' => 'required']);
    expect($staff->permissionOverrides($organization->id)->exists())->toBeFalse();
});

test('staff cannot edit their own permission overrides', function () {
    [$manager, $organization] = createPrompt16Organization();
    grantPrompt16Permission($manager, $organization, SystemPermission::ManagePermissions);
    grantPrompt16Permission($manager, $organization, SystemPermission::ManageStaff);
    $manageStaff = Permission::query()->where('code', SystemPermission::ManageStaff->value)->firstOrFail();

    permissionCard($manager, $organization, $manager)
        ->assertSee(__('permissions.messages.self_edit_disabled'))
        ->call('openPermissions')->assertForbidden();

    expect($manager->fresh()->permissionOverrides($organization->id)->where('permissions.id', $manageStaff->id)->exists())->toBeFalse();
    expect($manager->fresh()->hasPermission(SystemPermission::ManageStaff, $organization))->toBeTrue();
});

test('regular staff cannot open their own permission page without manage permissions', function () {
    [, $organization] = createPrompt16Organization();
    $staff = createPrompt16StaffMember($organization, SystemRole::Waiter);

    $this->actingAs($staff)
        ->get(route('organizations.staff.permissions', [$organization, $staff]))
        ->assertForbidden();
});

test('superadmin staff member keeps full computed access', function () {
    [$manager, $organization] = createPrompt16Organization();
    grantPrompt16Permission($manager, $organization, SystemPermission::ManagePermissions);
    $superadmin = createPrompt16StaffMember($organization, SystemRole::Superadmin);

    permissionCard($manager, $organization, $superadmin)
        ->assertSee(__('permissions.messages.superadmin_full_access'))
        ->assertSee(__('permissions.states.allowed'));

    expect($superadmin->fresh()->hasPermission(SystemPermission::ExportData, $organization))->toBeTrue();
});

function createPrompt16Organization(): array
{
    $manager = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($manager, ['name' => 'Permission Overrides Group']);
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

function permissionCard(User $actor, Organization $organization, User $subject): Testable
{
    $member = OrganizationUser::query()->where('organization_id', $organization->id)->where('user_id', $subject->id)->firstOrFail();

    return Livewire::actingAs($actor)->test(StaffPermissions::class, ['organization' => $organization, 'member' => $member])->call('selectSection', 'access');
}
