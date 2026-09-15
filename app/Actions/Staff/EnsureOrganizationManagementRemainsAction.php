<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\PermissionUserOverride;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Validation\ValidationException;

final class EnsureOrganizationManagementRemainsAction
{
    public function handle(Organization $organization): void
    {
        $permission = Permission::query()->select(['id', 'code'])->where('code', SystemPermission::ManageStaff->value)->firstOrFail();
        $memberships = OrganizationUser::query()->select(['id', 'user_id', 'role_id', 'organization_id'])
            ->where('organization_id', $organization->id)->where('status', OrganizationUserStatus::Active->value)
            ->with([
                'role.permissions' => fn ($query) => $query->where('permissions.id', $permission->id),
                'user' => fn ($query) => $query->select(['id'])->withCount('organizationMemberships'),
                'user.roles:id,code',
                'user.permissionOverrideRecords' => fn ($query) => $query->select(['id', 'user_id', 'permission_id', 'organization_id', 'scope_key', 'enabled'])
                    ->where('permission_id', $permission->id)->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $organization->id)),
            ])->lazyById(100);
        foreach ($memberships as $membership) {
            if ($membership->user->roles->contains(fn ($role): bool => $role->code === SystemRole::Superadmin)) {
                return;
            }
            $overrides = PermissionUserOverride::effectiveForOrganization($membership->user->permissionOverrideRecords, $organization->id, $membership->user->organization_memberships_count === 1);
            $allowed = $overrides->has($permission->id) ? (bool) $overrides[$permission->id]
                : (bool) $membership->role?->permissions->contains(fn (Permission $candidate): bool => $candidate->getRelation('pivot') instanceof Pivot && (bool) $candidate->getRelation('pivot')->getAttribute('enabled'));
            if ($allowed) {
                return;
            }
        }

        throw ValidationException::withMessages(['reason' => __('staff.errors.last_manager_change_blocked')]);
    }
}
