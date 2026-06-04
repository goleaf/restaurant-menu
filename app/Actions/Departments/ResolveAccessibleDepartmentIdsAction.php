<?php

namespace App\Actions\Departments;

use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\KitchenDepartmentType;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\KitchenDepartment;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ResolveAccessibleDepartmentIdsAction
{
    public function __construct(
        private readonly ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds,
    ) {}

    /**
     * @param  list<KitchenDepartmentType>  $departmentTypes
     * @param  list<SystemRole>  $roleCodes
     * @param  list<SystemPermission>  $permissionCodes
     * @return Collection<int, int>
     */
    public function handle(User $user, array $departmentTypes, array $roleCodes, array $permissionCodes): Collection
    {
        if ($user->isSuperadmin()) {
            return $this->departmentIdQuery($departmentTypes)->pluck('id');
        }

        $branchIds = $this->roleAccessibleBranchIds($user, $roleCodes);

        foreach ($permissionCodes as $permissionCode) {
            $branchIds = $branchIds
                ->merge($this->resolveAccessibleBranchIds->handle($user, $permissionCode))
                ->unique()
                ->values();
        }

        if ($branchIds->isEmpty()) {
            return collect();
        }

        return $this->departmentIdQuery($departmentTypes)
            ->whereIn('branch_id', $branchIds)
            ->pluck('id');
    }

    /**
     * @param  list<KitchenDepartmentType>  $departmentTypes
     * @param  list<SystemRole>  $roleCodes
     * @param  list<SystemPermission>  $permissionCodes
     */
    public function userHasAccess(User $user, array $departmentTypes, array $roleCodes, array $permissionCodes): bool
    {
        return $this->handle($user, $departmentTypes, $roleCodes, $permissionCodes)->isNotEmpty();
    }

    /**
     * @param  list<KitchenDepartmentType>  $departmentTypes
     */
    private function departmentIdQuery(array $departmentTypes): Builder
    {
        return KitchenDepartment::query()
            ->select(['id', 'branch_id', 'type', 'sort_order', 'name', 'is_active'])
            ->when($departmentTypes !== [], function ($query) use ($departmentTypes): void {
                $query->whereIn(
                    'type',
                    array_map(fn (KitchenDepartmentType $type): string => $type->value, $departmentTypes),
                );
            })
            ->where('is_active', true)
            ->orderBy('branch_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id');
    }

    /**
     * @param  list<SystemRole>  $roleCodes
     * @return Collection<int, int>
     */
    private function roleAccessibleBranchIds(User $user, array $roleCodes): Collection
    {
        if ($roleCodes === []) {
            return collect();
        }

        $organizationIds = OrganizationUser::query()
            ->select(['id', 'organization_id', 'role_id'])
            ->where('user_id', $user->id)
            ->where('status', OrganizationUserStatus::Active->value)
            ->whereHas('role', function ($query) use ($roleCodes): void {
                $query->whereIn(
                    'roles.code',
                    array_map(fn (SystemRole $role): string => $role->value, $roleCodes),
                );
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
