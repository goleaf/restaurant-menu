<?php

namespace App\Actions\Waiter;

use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Collection;

class ResolveWaiterAccessibleBranchIdsAction
{
    /**
     * @return Collection<int, int>
     */
    public function handle(User $user): Collection
    {
        if ($user->isSuperadmin()) {
            return Branch::query()
                ->select(['id'])
                ->orderBy('id')
                ->pluck('id');
        }

        $permission = Permission::query()
            ->select(['id', 'code'])
            ->where('code', SystemPermission::ViewOrders->value)
            ->first();

        if (! $permission instanceof Permission) {
            return collect();
        }

        $override = $user->permissionOverrides()
            ->where('permissions.id', $permission->id)
            ->first();

        if ($override instanceof Permission && ! (bool) $override->pivot->enabled) {
            return collect();
        }

        $memberships = OrganizationUser::query()
            ->select(['id', 'organization_id', 'role_id'])
            ->where('user_id', $user->id)
            ->where('status', OrganizationUserStatus::Active->value)
            ->when(! $override instanceof Permission, function ($query) use ($permission): void {
                $query->whereHas('role.permissions', function ($permissionQuery) use ($permission): void {
                    $permissionQuery
                        ->where('permissions.id', $permission->id)
                        ->where('permission_role.enabled', true);
                });
            })
            ->orderBy('organization_id')
            ->get();

        if ($memberships->isEmpty()) {
            return collect();
        }

        $organizationIds = $memberships->pluck('organization_id')->unique()->values();
        $branchIds = Branch::query()
            ->select(['id', 'organization_id'])
            ->whereIn('organization_id', $organizationIds)
            ->orderBy('id')
            ->pluck('id');

        $assignedBranchIds = BranchUser::query()
            ->select(['id', 'organization_id', 'branch_id', 'user_id', 'status'])
            ->where('user_id', $user->id)
            ->where('status', OrganizationUserStatus::Active->value)
            ->whereIn('organization_id', $organizationIds)
            ->orderBy('branch_id')
            ->pluck('branch_id')
            ->unique()
            ->values();

        if ($assignedBranchIds->isEmpty()) {
            return $branchIds;
        }

        return $branchIds
            ->intersect($assignedBranchIds)
            ->values();
    }
}
