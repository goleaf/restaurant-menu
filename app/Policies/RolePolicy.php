<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;

final class RolePolicy
{
    public function assign(User $user, Role $role, Organization $organization): bool
    {
        return $this->canManageRole($user, $role, $organization, SystemPermission::ManageStaff);
    }

    public function managePermissions(User $user, Role $role, Organization $organization): bool
    {
        return $this->canManageRole($user, $role, $organization, SystemPermission::ManagePermissions);
    }

    private function canManageRole(
        User $user,
        Role $role,
        Organization $organization,
        SystemPermission $permission,
    ): bool {
        if ($role->code === SystemRole::Superadmin) {
            return false;
        }

        if ($user->isSuperadmin()) {
            return true;
        }

        if (! $user->hasPermission($permission, $organization)) {
            return false;
        }

        $membership = OrganizationUser::query()
            ->select(['id', 'organization_id', 'user_id', 'role_id', 'status'])
            ->with(['role:id,code,name,sort_order'])
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->where('status', OrganizationUserStatus::Active->value)
            ->first();

        return $membership?->role instanceof Role
            && $membership->role->sort_order < $role->sort_order;
    }
}
