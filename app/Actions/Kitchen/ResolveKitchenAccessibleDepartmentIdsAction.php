<?php

namespace App\Actions\Kitchen;

use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\KitchenDepartment;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Support\Collection;

class ResolveKitchenAccessibleDepartmentIdsAction
{
    public function __construct(
        private readonly ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds,
    ) {}

    /**
     * @return Collection<int, int>
     */
    public function handle(User $user): Collection
    {
        if ($user->isSuperadmin()) {
            return KitchenDepartment::query()
                ->select(['id'])
                ->where('is_active', true)
                ->orderBy('branch_id')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->orderBy('id')
                ->pluck('id');
        }

        $branchIds = $this->roleAccessibleBranchIds($user)
            ->merge($this->resolveAccessibleBranchIds->handle($user, SystemPermission::ViewKitchen))
            ->unique()
            ->values();

        if ($branchIds->isEmpty()) {
            return collect();
        }

        return KitchenDepartment::query()
            ->select(['id', 'branch_id', 'sort_order', 'name', 'is_active'])
            ->whereIn('branch_id', $branchIds)
            ->where('is_active', true)
            ->orderBy('branch_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->pluck('id');
    }

    public function userHasAccess(User $user): bool
    {
        return $this->handle($user)->isNotEmpty();
    }

    /**
     * @return Collection<int, int>
     */
    private function roleAccessibleBranchIds(User $user): Collection
    {
        $organizationIds = OrganizationUser::query()
            ->select(['id', 'organization_id', 'role_id'])
            ->where('user_id', $user->id)
            ->where('status', OrganizationUserStatus::Active->value)
            ->whereHas('role', function ($query): void {
                $query->whereIn('roles.code', [
                    SystemRole::HeadChef->value,
                    SystemRole::Cook->value,
                ]);
            })
            ->orderBy('organization_id')
            ->pluck('organization_id')
            ->unique()
            ->values();

        if ($organizationIds->isEmpty()) {
            return collect();
        }

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
