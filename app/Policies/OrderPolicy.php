<?php

declare(strict_types=1);

namespace App\Policies;

use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\Order;
use App\Models\TableSession;
use App\Models\User;

final class OrderPolicy
{
    public function __construct(
        private readonly TableSessionPolicy $tableSessions,
        private readonly ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds,
    ) {}

    public function viewAny(User $user, Branch $branch): bool
    {
        return $this->hasPermission($user, (int) $branch->id, SystemPermission::ViewOrders);
    }

    public function view(User $user, Order $order): bool
    {
        $tableSession = $order->tableSession;

        return $tableSession instanceof TableSession && $this->tableSessions->view($user, $tableSession);
    }

    public function create(User $user, TableSession $tableSession): bool
    {
        return $this->hasPermission($user, (int) $tableSession->branch_id, SystemPermission::ConfirmOrders);
    }

    public function update(User $user, Order $order): bool
    {
        return $this->editPending($user, $order);
    }

    public function editPending(User $user, Order $order): bool
    {
        return $this->hasPermission($user, (int) $order->branch_id, SystemPermission::ConfirmOrders)
            || $this->hasPermission($user, (int) $order->branch_id, SystemPermission::EditPendingOrders);
    }

    public function confirm(User $user, Order $order): bool
    {
        return $this->hasPermission($user, (int) $order->branch_id, SystemPermission::ConfirmOrders);
    }

    public function sendToDepartments(User $user, Order $order): bool
    {
        return $this->hasPermission($user, (int) $order->branch_id, SystemPermission::SendToDepartments);
    }

    public function sendToKitchen(User $user, Order $order): bool
    {
        return $this->hasPermission($user, (int) $order->branch_id, SystemPermission::SendToKitchen);
    }

    public function cancel(User $user, Order $order): bool
    {
        if ($this->hasPermission($user, (int) $order->branch_id, SystemPermission::CancelOrders)) {
            return true;
        }

        $branch = $order->branch;

        return $branch instanceof Branch
            && ($user->hasOrganizationRole((int) $branch->organization_id, SystemRole::Director)
                || $user->hasOrganizationRole((int) $branch->organization_id, SystemRole::ShiftManager));
    }

    public function markServed(User $user, Order $order): bool
    {
        return $this->hasPermission($user, (int) $order->branch_id, SystemPermission::MarkOrderServed);
    }

    public function viewHistory(User $user, Order $order): bool
    {
        return $this->hasPermission($user, (int) $order->branch_id, SystemPermission::ViewOrderHistory);
    }

    public function delete(User $user, Order $order): bool
    {
        return false;
    }

    private function hasPermission(User $user, int $branchId, SystemPermission $permission): bool
    {
        return $this->resolveAccessibleBranchIds->handle($user, $permission)->contains($branchId);
    }
}
