<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Staff\AddBranchStaffMemberAction;
use App\Actions\Staff\AddOrganizationStaffMemberAction;
use App\Actions\Staff\SetBranchStaffStatusAction;
use App\Actions\Staff\SetOrganizationStaffStatusAction;
use App\Actions\Staff\SetUserPermissionOverrideAction;
use App\Actions\Staff\UpdateBranchStaffRoleAction;
use App\Actions\Staff\UpdateOrganizationStaffRoleAction;
use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\AuditLogAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\PermissionOverrideState;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
});

test('a permission decision in one organization never changes another organization', function (): void {
    $owner = User::factory()->create();
    $a = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'First Fictional Restaurant']);
    $b = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'Second Fictional Restaurant']);
    $staff = User::factory()->create();
    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    OrganizationUser::factory()->forOrganization($a)->forUser($staff)->forRole($role)->active()->create();
    OrganizationUser::factory()->forOrganization($b)->forUser($staff)->forRole($role)->active()->create();
    $permission = Permission::query()->where('code', SystemPermission::ManageMenu->value)->firstOrFail();

    app(SetUserPermissionOverrideAction::class)->handle($staff, $permission, PermissionOverrideState::Allow, $owner, $a->id);

    expect($staff->hasPermission(SystemPermission::ManageMenu, $a))->toBeTrue()
        ->and($staff->hasPermission(SystemPermission::ManageMenu, $b))->toBeFalse()
        ->and($staff->hasPermission(SystemPermission::ManageMenu))->toBeFalse();
});

test('ambiguous legacy grants are preserved but not applied across organizations', function (): void {
    [$owner, $organization, $member] = teamAccessMember();
    $permission = Permission::query()->where('code', SystemPermission::ManageMenu->value)->firstOrFail();
    $member->user->permissionOverrides()->attach($permission, ['enabled' => true]);
    expect($member->user->hasPermission(SystemPermission::ManageMenu, $organization))->toBeTrue();
    $other = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'Other Fictional Restaurant']);
    OrganizationUser::factory()->forOrganization($other)->forUser($member->user)->forRole($member->role)->active()->create();
    expect($member->user->hasPermission(SystemPermission::ManageMenu, $organization))->toBeFalse()
        ->and($member->user->hasPermission(SystemPermission::ManageMenu, $other))->toBeFalse()
        ->and($member->user->permissionOverrides()->count())->toBe(1);
});

test('scoped decisions can explicitly review a legacy deny without changing another tenant', function (): void {
    [$owner, $organization, $member] = teamAccessMember();
    $permission = Permission::query()->where('code', SystemPermission::ViewOrders->value)->firstOrFail();
    $other = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'Other Fictional Restaurant']);
    OrganizationUser::factory()->forOrganization($other)->forUser($member->user)->forRole($member->role)->active()->create();
    $member->user->permissionOverrides()->attach($permission, ['enabled' => false]);
    app(SetUserPermissionOverrideAction::class)->handle($member->user, $permission, PermissionOverrideState::Allow, $owner, $organization->id);
    expect($member->user->hasPermission(SystemPermission::ViewOrders, $organization))->toBeTrue()
        ->and($member->user->hasPermission(SystemPermission::ViewOrders, $other))->toBeFalse();
    app(SetUserPermissionOverrideAction::class)->handle($member->user, $permission, PermissionOverrideState::Default, $owner, $organization->id);
    expect($member->user->hasPermission(SystemPermission::ViewOrders, $organization))->toBeFalse()
        ->and($member->user->permissionOverrides()->count())->toBe(1);
});

test('permission explanation shares the server decision including inactive participation', function (): void {
    [$owner, $organization, $member] = teamAccessMember();
    $permission = Permission::query()->where('code', SystemPermission::ManageMenu->value)->firstOrFail();
    app(SetUserPermissionOverrideAction::class)->handle($member->user, $permission, PermissionOverrideState::Allow, $owner, $organization->id);
    $decision = $member->user->organizationPermissionDecisions($organization, [$permission->code]);
    expect($decision[$permission->code])->toBe(['allowed' => true, 'source' => 'explicit_allow']);
    $member->forceFill(['status' => OrganizationUserStatus::Suspended])->save();
    expect($member->user->organizationPermissionDecisions($organization, [$permission->code])[$permission->code])
        ->toBe(['allowed' => false, 'source' => 'inactive_membership'])
        ->and($member->user->hasPermission($permission->code, $organization))->toBeFalse();
});

test('critical permission reason is enforced at the action boundary and audit rollback is atomic', function (): void {
    [$owner, $organization, $member] = teamAccessMember();
    $permission = Permission::query()->where('code', SystemPermission::ManagePermissions->value)->firstOrFail();
    expect(fn () => app(SetUserPermissionOverrideAction::class)->handle($member->user, $permission, PermissionOverrideState::Allow, $owner, $organization->id))
        ->toThrow(ValidationException::class);
    AuditLog::creating(fn () => false);
    try {
        expect(fn () => app(SetUserPermissionOverrideAction::class)->handle($member->user, $permission, PermissionOverrideState::Allow, $owner, $organization->id, 'Authorized adjustment.'))
            ->toThrow(RuntimeException::class);
    } finally {
        AuditLog::flushEventListeners();
    }
    expect($member->user->permissionOverrides($organization->id)->exists())->toBeFalse();
});

test('obsolete manual provisioning cannot create or reactivate an account', function (): void {
    [$owner, $organization, $member] = teamAccessMember();
    $count = User::query()->count();
    expect(fn () => app(AddOrganizationStaffMemberAction::class)->handle($organization, $member->role, $owner, ['name' => 'Fictional Invitee', 'email' => 'invite-only@example.test']))
        ->toThrow(ValidationException::class);
    $member->forceFill(['status' => OrganizationUserStatus::Suspended])->save();
    expect(fn () => app(AddOrganizationStaffMemberAction::class)->handle($organization, $member->role, $owner, ['name' => 'Replacement', 'email' => $member->user->email]))
        ->toThrow(ValidationException::class)
        ->and(User::query()->count())->toBe($count)
        ->and($member->fresh()->status)->toBe(OrganizationUserStatus::Suspended);
});

test('branch assignment preserves accepted identity and organization role and never silently restores', function (): void {
    [$owner, $organization, $member, $branch] = teamAccessMember();
    $name = $member->user->name;
    $hash = $member->user->password;
    $role = Role::query()->where('code', SystemRole::Bartender->value)->firstOrFail();
    $action = app(AddBranchStaffMemberAction::class);
    $action->handle($organization, $branch, $role, $owner, ['name' => 'Replacement', 'email' => $member->user->email]);
    $assignment = BranchUser::query()->where('branch_id', $branch->id)->where('user_id', $member->user_id)->sole();
    expect($assignment->role_id)->toBe($role->id)
        ->and($member->fresh()->role_id)->toBe($member->role_id)
        ->and($member->user->fresh()->name)->toBe($name)
        ->and($member->user->fresh()->password)->toBe($hash);
    $assignment->forceFill(['status' => OrganizationUserStatus::Suspended])->save();
    expect(fn () => $action->handle($organization, $branch, $role, $owner, ['email' => $member->user->email]))
        ->toThrow(ValidationException::class);
});

test('role changes reject stale versions and cannot manage a higher ranking existing member', function (): void {
    [$owner, $organization, $member] = teamAccessMember();
    $original = $member->replicate()->setRawAttributes($member->getAttributes(), true);
    $original->exists = true;
    $bartender = Role::query()->where('code', SystemRole::Bartender->value)->firstOrFail();
    $cook = Role::query()->where('code', SystemRole::Cook->value)->firstOrFail();
    $action = app(UpdateOrganizationStaffRoleAction::class);
    $action->handle($owner, $organization, $member, $bartender, 'Assigned to bar.', expectedVersion: 0);
    expect(fn () => $action->handle($owner, $organization, $original, $cook, 'Assigned to kitchen.', expectedVersion: 0))
        ->toThrow(ValidationException::class)
        ->and($member->fresh()->role_id)->toBe($bartender->id)
        ->and($member->fresh()->access_version)->toBe(1);
    $action->handle($owner, $organization, $original, $bartender, 'Retry after response loss.', expectedVersion: 0);
    expect(AuditLog::query()->where('action', AuditLogAction::StaffRoleChanged->value)->count())->toBe(1);
});

test('suspension and explicit restoration preserve other organizations and audit exactly once', function (): void {
    [$owner, $organization, $member] = teamAccessMember();
    $other = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'Other Fictional Restaurant']);
    $otherMember = OrganizationUser::factory()->forOrganization($other)->forUser($member->user)->forRole($member->role)->active()->create();
    $action = app(SetOrganizationStaffStatusAction::class);
    $action->suspend($member, $owner, 'Temporary access review.', expectedVersion: 0);
    $action->suspend($member, $owner, 'Retry after response loss.', expectedVersion: 0);
    expect($member->fresh()->status)->toBe(OrganizationUserStatus::Suspended)
        ->and($otherMember->fresh()->status)->toBe(OrganizationUserStatus::Active)
        ->and($member->user->canAccessOrganization($organization))->toBeFalse()
        ->and($member->user->canAccessOrganization($other))->toBeTrue()
        ->and(AuditLog::query()->where('action', AuditLogAction::StaffDeactivated->value)->count())->toBe(1);
    expect(fn () => $action->activate($member, $owner, expectedVersion: 0))->toThrow(ValidationException::class);
    $action->activate($member->fresh(), $owner, 'Review complete.', expectedVersion: 1);
    expect($member->fresh()->status)->toBe(OrganizationUserStatus::Active)
        ->and(AuditLog::query()->where('action', AuditLogAction::StaffReactivated->value)->count())->toBe(1);
});

test('membership and audit veto roll back the version and status together', function (): void {
    [$owner, , $member] = teamAccessMember();
    OrganizationUser::updating(fn () => false);
    try {
        expect(fn () => app(SetOrganizationStaffStatusAction::class)->suspend($member, $owner, 'Temporary review.'))
            ->toThrow(RuntimeException::class);
    } finally {
        OrganizationUser::flushEventListeners();
    }
    expect($member->fresh()->status)->toBe(OrganizationUserStatus::Active)->and($member->fresh()->access_version)->toBe(0);
    AuditLog::creating(fn () => false);
    try {
        expect(fn () => app(SetOrganizationStaffStatusAction::class)->suspend($member, $owner, 'Temporary review.'))
            ->toThrow(RuntimeException::class);
    } finally {
        AuditLog::flushEventListeners();
    }
    expect($member->fresh()->status)->toBe(OrganizationUserStatus::Active)->and($member->fresh()->access_version)->toBe(0);
});

/** @return array{User, Organization, OrganizationUser, Branch} */
function teamAccessMember(): array
{
    $owner = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'Fictional Team Restaurant']);
    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    $member = OrganizationUser::factory()->forOrganization($organization)->forRole($role)->active()->create();
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();

    return [$owner, $organization, $member, $branch];
}

test('superadmin cannot remove the only eligible manager even when a denied owner remains', function (): void {
    [$owner, $organization, $member] = teamAccessMember();
    $superadmin = User::factory()->create();
    $superadmin->roles()->attach(Role::query()->where('code', SystemRole::Superadmin->value)->firstOrFail());
    $director = Role::query()->where('code', SystemRole::Director->value)->firstOrFail();
    $member->forceFill(['role_id' => $director->id])->save();
    $permission = Permission::query()->where('code', SystemPermission::ManageStaff->value)->firstOrFail();
    $owner->permissionOverrides($organization->id)->attach($permission, ['enabled' => false]);
    expect(fn () => app(SetOrganizationStaffStatusAction::class)->suspend($member, $superadmin, 'Reviewing manager access.'))
        ->toThrow(ValidationException::class)
        ->and($member->fresh()->status)->toBe(OrganizationUserStatus::Active)
        ->and($member->fresh()->access_version)->toBe(0);
    expect(fn () => app(UpdateOrganizationStaffRoleAction::class)->handle($superadmin, $organization, $member, Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail(), 'Reviewing manager role.'))
        ->toThrow(ValidationException::class)
        ->and($member->fresh()->role_id)->toBe($director->id);
    expect(fn () => app(SetUserPermissionOverrideAction::class)->handle($member->user, $permission, PermissionOverrideState::Deny, $superadmin, $organization->id, 'Reviewing manager permissions.'))
        ->toThrow(ValidationException::class)
        ->and($member->user->permissionOverrides($organization->id)->exists())->toBeFalse();
});

test('suspended branch access cannot fall back to another active assignment or alter it', function (): void {
    [$owner, $organization, $member, $branch] = teamAccessMember();
    $other = Branch::factory()->for($organization)->for($branch->brand)->create();
    $current = BranchUser::factory()->forBranch($branch)->forUser($member->user)->forRole($member->role)->active()->create();
    $otherAssignment = BranchUser::factory()->forBranch($other)->forUser($member->user)->forRole($member->role)->active()->create();
    $action = app(SetBranchStaffStatusAction::class);
    $action->suspend($current, $owner, 'Review this branch only.', expectedVersion: 0);
    expect($member->user->canAccessBranch($branch))->toBeFalse()
        ->and($member->user->canAccessBranch($other))->toBeTrue()
        ->and($otherAssignment->fresh()->status)->toBe(OrganizationUserStatus::Active)
        ->and($member->fresh()->status)->toBe(OrganizationUserStatus::Active);
    $action->activate($current->fresh(), $owner, 'Branch review complete.', expectedVersion: 1);
    expect($member->user->canAccessBranch($branch))->toBeTrue();
});

test('batched waiter access agrees with scoped authorization after a permission change', function (): void {
    [$owner, $organization, $member, $branch] = teamAccessMember();
    $other = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'Other Operations Restaurant']);
    $brand = Brand::factory()->for($other)->create();
    $otherBranch = Branch::factory()->for($other)->for($brand)->create();
    OrganizationUser::factory()->forOrganization($other)->forUser($member->user)->forRole($member->role)->active()->create();
    $permission = Permission::query()->where('code', SystemPermission::ViewOrders->value)->firstOrFail();
    app(SetUserPermissionOverrideAction::class)->handle($member->user, $permission, PermissionOverrideState::Deny, $owner, $organization->id);
    $resolver = app(ResolveWaiterAccessibleBranchIdsAction::class);
    expect($resolver->handle($member->user)->all())->toBe([$otherBranch->id])
        ->and($resolver->handleMany($member->user, [SystemPermission::ViewOrders])[SystemPermission::ViewOrders->value]->all())->toBe([$otherBranch->id]);
});

test('direct caller cannot change a higher role or use an actor whose access was revoked', function (): void {
    [$owner, $organization, $member] = teamAccessMember();
    $admin = User::factory()->create();
    $adminRole = Role::query()->where('code', SystemRole::RestaurantAdmin->value)->firstOrFail();
    $adminMembership = OrganizationUser::factory()->forOrganization($organization)->forUser($admin)->forRole($adminRole)->active()->create();
    $director = Role::query()->where('code', SystemRole::Director->value)->firstOrFail();
    $member->forceFill(['role_id' => $director->id])->save();
    $waiter = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    expect(fn () => app(UpdateOrganizationStaffRoleAction::class)->handle($admin, $organization, $member, $waiter, 'Attempt to demote a superior.'))
        ->toThrow(AuthorizationException::class);
    $adminMembership->forceFill(['status' => OrganizationUserStatus::Suspended])->save();
    expect(fn () => app(SetOrganizationStaffStatusAction::class)->suspend($member, $admin, 'Attempt with old access.'))
        ->toThrow(AuthorizationException::class);
});

test('an already opened branch role form cannot mutate an archived branch', function (): void {
    [$owner, $organization, $member, $branch] = teamAccessMember();
    $assignment = BranchUser::factory()->forBranch($branch)->forUser($member->user)->forRole($member->role)->active()->create();
    BranchUser::factory()->forBranch($branch)->forUser($owner)->forRole(Role::query()->where('code', SystemRole::Owner->value)->firstOrFail())->active()->create();
    $branch->delete();
    $staleBranch = $branch->replicate()->setRawAttributes($branch->getAttributes(), true);
    $staleBranch->id = $branch->id;
    $staleBranch->deleted_at = null;
    $staleBranch->exists = true;
    expect(fn () => app(UpdateBranchStaffRoleAction::class)->handle($owner, $staleBranch, $assignment, Role::query()->where('code', SystemRole::Cook->value)->firstOrFail(), 'Attempt after archive.'))
        ->toThrow(ModelNotFoundException::class)
        ->and($assignment->fresh()->role_id)->toBe($member->role_id);
});
