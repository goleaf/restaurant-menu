<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\PermissionRole;
use App\Models\PermissionUserOverride;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

final class PermissionQueryService
{
    public function membership(Organization $organization, User $user): OrganizationUser
    {
        return OrganizationUser::query()
            ->select(['id', 'organization_id', 'user_id', 'role_id', 'status', 'joined_at', 'invited_by_user_id', 'created_at', 'updated_at'])
            ->with(['role' => fn ($query) => $query->select(['id', 'code', 'name', 'sort_order'])])
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->firstOrFail();
    }

    public function permission(int $permissionId): Permission
    {
        return Permission::query()
            ->select(['id', 'code', 'name', 'sort_order'])
            ->whereKey($permissionId)
            ->firstOrFail();
    }

    /** @return Collection<int, bool> */
    public function roleDefaults(int $roleId): Collection
    {
        return PermissionRole::query()
            ->select(['permission_id', 'enabled'])
            ->where('role_id', $roleId)
            ->get()
            ->mapWithKeys(fn (PermissionRole $assignment): array => [
                $assignment->permission_id => $assignment->enabled,
            ]);
    }

    /** @return Collection<int, bool> */
    public function userOverrides(User $user): Collection
    {
        return PermissionUserOverride::query()
            ->select(['permission_id', 'enabled'])
            ->where('user_id', $user->id)
            ->get()
            ->mapWithKeys(fn (PermissionUserOverride $override): array => [
                $override->permission_id => $override->enabled,
            ]);
    }

    /** @return EloquentCollection<int, Permission> */
    public function permissions(): EloquentCollection
    {
        return Permission::query()
            ->select(['id', 'code', 'name', 'sort_order'])
            ->orderBy('sort_order')
            ->get();
    }
}
