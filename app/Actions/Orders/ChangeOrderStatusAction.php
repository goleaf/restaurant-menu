<?php

namespace App\Actions\Orders;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\AuditLogAction;
use App\Enums\OrderStatus;
use App\Enums\OrderStatusLogEvent;
use App\Enums\SystemPermission;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeOrderStatusAction
{
    public function __construct(
        private readonly ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds,
        private readonly CreateOrderStatusLogAction $createOrderStatusLog,
        private readonly RecordAuditLogAction $recordAuditLog,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function handle(Order $order, OrderStatus $newStatus, User $changedBy, ?string $reason = null, array $metadata = []): Order
    {
        return DB::transaction(function () use ($order, $newStatus, $changedBy, $reason, $metadata): Order {
            $order = $this->reloadOrder($order);
            $this->ensureCanChangeStatus($order, $newStatus, $changedBy);

            $previousStatus = $order->status;

            if ($previousStatus === $newStatus) {
                return $order;
            }

            $order
                ->forceFill(['status' => $newStatus])
                ->save();

            $this->createOrderStatusLog->handle(
                event: $this->eventFor($newStatus),
                order: $order,
                actorUser: $changedBy,
                previousStatus: $previousStatus,
                newStatus: $newStatus,
                statusType: 'order',
                reason: $this->normalizeReason($reason),
                metadata: ['source' => 'manual_status_change'] + $metadata,
            );

            if ($newStatus === OrderStatus::Cancelled) {
                $this->recordAuditLog->handle(
                    action: AuditLogAction::OrderCancelled,
                    entityType: 'order',
                    entityId: $order->id,
                    actorUser: $changedBy,
                    organizationId: $order->branch?->organization_id,
                    branchId: $order->branch_id,
                    oldValues: [
                        'status' => $previousStatus,
                    ],
                    newValues: [
                        'status' => $newStatus,
                        'reason' => $this->normalizeReason($reason),
                    ],
                );
            }

            return $order->refresh();
        });
    }

    private function reloadOrder(Order $order): Order
    {
        return Order::query()
            ->select([
                'id',
                'branch_id',
                'service_point_id',
                'table_session_id',
                'draft_order_id',
                'status',
            ])
            ->with(['branch:id,organization_id'])
            ->whereKey($order->id)
            ->firstOrFail();
    }

    private function ensureCanChangeStatus(Order $order, OrderStatus $newStatus, User $user): void
    {
        $permission = $this->permissionFor($newStatus);
        $branchIds = $this->resolveAccessibleBranchIds->handle($user, $permission);

        if (! $branchIds->contains((int) $order->branch_id)) {
            throw ValidationException::withMessages([
                'order_status' => __('У вас нет права менять этот статус заказа в этом филиале.'),
            ]);
        }
    }

    private function permissionFor(OrderStatus $newStatus): SystemPermission
    {
        return match ($newStatus) {
            OrderStatus::SentToKitchenBar => SystemPermission::SendToKitchen,
            OrderStatus::Cancelled => SystemPermission::CancelOrders,
            default => SystemPermission::ConfirmOrders,
        };
    }

    private function eventFor(OrderStatus $newStatus): OrderStatusLogEvent
    {
        return match ($newStatus) {
            OrderStatus::SentToKitchenBar => OrderStatusLogEvent::OrderSentToKitchenBar,
            OrderStatus::Cancelled => OrderStatusLogEvent::OrderCancelled,
            default => OrderStatusLogEvent::OrderStatusChanged,
        };
    }

    private function normalizeReason(?string $reason): ?string
    {
        $normalizedReason = trim((string) $reason);

        if ($normalizedReason === '') {
            return null;
        }

        return mb_substr($normalizedReason, 0, 500);
    }
}
