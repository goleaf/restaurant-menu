<?php

namespace App\Actions\Payments;

use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Support\Collection;

class ResolvePaymentAccessibleBranchIdsAction
{
    public function __construct(
        private readonly ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds,
    ) {}

    /**
     * @return Collection<int, int>
     */
    public function viewableBranchIds(User $user): Collection
    {
        return $this->resolveAccessibleBranchIds
            ->handle($user, SystemPermission::ViewPayments)
            ->merge($this->manageableBranchIds($user))
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, int>
     */
    public function manageableBranchIds(User $user): Collection
    {
        return $this->resolveAccessibleBranchIds
            ->handle($user, SystemPermission::ManagePayments)
            ->merge($this->cashierBranchIds($user))
            ->unique()
            ->values();
    }

    public function canView(User $user, int $branchId): bool
    {
        return $this->viewableBranchIds($user)->contains($branchId);
    }

    public function canManage(User $user, int $branchId): bool
    {
        return $this->manageableBranchIds($user)->contains($branchId);
    }

    /**
     * @return Collection<int, int>
     */
    private function cashierBranchIds(User $user): Collection
    {
        if ($user->isSuperadmin()) {
            return Branch::query()
                ->select(['id'])
                ->orderBy('id')
                ->pluck('id');
        }

        $memberships = OrganizationUser::query()
            ->select(['id', 'organization_id', 'role_id'])
            ->where('user_id', $user->id)
            ->where('status', OrganizationUserStatus::Active->value)
            ->whereHas('role', function ($query): void {
                $query->where('roles.code', SystemRole::Cashier->value);
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
