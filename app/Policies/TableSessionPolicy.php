<?php

declare(strict_types=1);

namespace App\Policies;

use App\Actions\Payments\ResolvePaymentAccessibleBranchIdsAction;
use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\SystemPermission;
use App\Enums\TableSessionStatus;
use App\Models\Branch;
use App\Models\TableSession;
use App\Models\User;

final class TableSessionPolicy
{
    public function __construct(
        private readonly BranchPolicy $branches,
        private readonly ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds,
        private readonly ResolvePaymentAccessibleBranchIdsAction $resolvePaymentAccess,
    ) {}

    public function viewAny(User $user, Branch $branch): bool
    {
        return $this->canViewBranch($user, (int) $branch->id);
    }

    public function view(User $user, TableSession $tableSession): bool
    {
        return $this->canViewBranch($user, (int) $tableSession->branch_id);
    }

    public function viewOrders(User $user, TableSession $tableSession): bool
    {
        return $this->hasPermission($user, $tableSession, SystemPermission::ViewOrders);
    }

    public function viewPayments(User $user, TableSession $tableSession): bool
    {
        return $this->resolvePaymentAccess->canView($user, (int) $tableSession->branch_id);
    }

    public function create(User $user, Branch $branch): bool
    {
        return $this->branches->openTable($user, $branch);
    }

    public function update(User $user, TableSession $tableSession): bool
    {
        return $this->hasPermission($user, $tableSession, SystemPermission::ManageTableSessions);
    }

    public function manageGuests(User $user, TableSession $tableSession): bool
    {
        return $this->update($user, $tableSession);
    }

    public function transfer(User $user, TableSession $tableSession): bool
    {
        return $this->hasPermission($user, $tableSession, SystemPermission::ViewOrders)
            || $this->hasPermission($user, $tableSession, SystemPermission::ConfirmOrders);
    }

    public function merge(User $user, TableSession $tableSession): bool
    {
        return $this->transfer($user, $tableSession);
    }

    public function close(User $user, TableSession $tableSession): bool
    {
        if ($tableSession->status === TableSessionStatus::Paid
            && $this->resolvePaymentAccess->canManage($user, (int) $tableSession->branch_id)) {
            return true;
        }

        return $this->hasPermission($user, $tableSession, SystemPermission::CloseTableSessions);
    }

    public function delete(User $user, TableSession $tableSession): bool
    {
        return false;
    }

    private function canViewBranch(User $user, int $branchId): bool
    {
        return $this->resolveAccessibleBranchIds
            ->handle($user, SystemPermission::ViewOrders)
            ->contains($branchId)
            || $this->resolvePaymentAccess->canView($user, $branchId);
    }

    private function hasPermission(User $user, TableSession $tableSession, SystemPermission $permission): bool
    {
        return $this->resolveAccessibleBranchIds
            ->handle($user, $permission)
            ->contains((int) $tableSession->branch_id);
    }
}
