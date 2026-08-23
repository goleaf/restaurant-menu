<?php

declare(strict_types=1);

namespace App\Policies;

use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\SystemPermission;
use App\Models\DraftOrder;
use App\Models\TableSession;
use App\Models\User;

final class DraftOrderPolicy
{
    public function __construct(
        private readonly TableSessionPolicy $tableSessions,
        private readonly ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds,
    ) {}

    public function view(User $user, DraftOrder $draftOrder): bool
    {
        $tableSession = $draftOrder->tableSession;

        return $tableSession instanceof TableSession && $this->tableSessions->view($user, $tableSession);
    }

    public function create(User $user, TableSession $tableSession): bool
    {
        return $this->canEdit($user, (int) $tableSession->branch_id);
    }

    public function update(User $user, DraftOrder $draftOrder): bool
    {
        return $this->updateItems($user, $draftOrder);
    }

    public function updateItems(User $user, DraftOrder $draftOrder): bool
    {
        return $this->withTableSession($draftOrder, fn (TableSession $tableSession): bool => $this->canEdit($user, (int) $tableSession->branch_id));
    }

    public function confirm(User $user, DraftOrder $draftOrder): bool
    {
        return $this->withTableSession($draftOrder, fn (TableSession $tableSession): bool => $this->hasPermission($user, (int) $tableSession->branch_id, SystemPermission::ConfirmOrders));
    }

    public function reject(User $user, DraftOrder $draftOrder): bool
    {
        return $this->confirm($user, $draftOrder);
    }

    public function returnRejected(User $user, DraftOrder $draftOrder): bool
    {
        return $this->confirm($user, $draftOrder);
    }

    public function delete(User $user, DraftOrder $draftOrder): bool
    {
        return false;
    }

    private function canEdit(User $user, int $branchId): bool
    {
        return $this->hasPermission($user, $branchId, SystemPermission::ConfirmOrders)
            || $this->hasPermission($user, $branchId, SystemPermission::EditPendingOrders);
    }

    private function hasPermission(User $user, int $branchId, SystemPermission $permission): bool
    {
        return $this->resolveAccessibleBranchIds->handle($user, $permission)->contains($branchId);
    }

    /**
     * @param  callable(TableSession): bool  $authorize
     */
    private function withTableSession(DraftOrder $draftOrder, callable $authorize): bool
    {
        $tableSession = $draftOrder->tableSession;

        return $tableSession instanceof TableSession && $authorize($tableSession);
    }
}
