<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Enums\BusinessRuleCode;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderStatusLogEvent;
use App\Enums\TableSessionStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\KitchenTicketItem;
use App\Models\ManualPayment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TableSession;
use App\Models\User;
use App\Support\PlainText;
use App\Support\Validation\RestaurantValidationRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class CancelOrderItemAction
{
    public function __construct(
        private readonly CreateOrderStatusLogAction $createOrderStatusLog,
        private readonly RecordAuditLogAction $recordAuditLog,
        private readonly SyncOrderStatusFromTicketItemsAction $syncOrderStatus,
        private readonly ChangeOrderStatusAction $changeOrderStatus,
    ) {}

    public function handle(OrderItem $orderItem, User $cancelledBy, string $reason): OrderItem
    {
        return DB::transaction(function () use ($orderItem, $cancelledBy, $reason): OrderItem {
            $orderIdentity = $this->orderIdentity($orderItem);
            $tableSession = $this->lockedTableSession((int) $orderIdentity->table_session_id);
            $order = $this->lockedOrder((int) $orderIdentity->id);
            $orderItem = $this->lockedOrderItem($orderItem, $order);
            $orderItem->setRelation('order', $order);

            $this->authorizeCancellation($orderItem, $cancelledBy);
            $this->ensureCancellable($orderItem, $order, $tableSession);

            $normalizedReason = $this->validatedReason($reason);
            $cancelledAt = now();
            $previousOrderTotalCents = $order->total_price_cents;
            $previousTicketStatus = $orderItem->kitchenTicketItem?->status;

            $orderItem->forceFill([
                'cancelled_at' => $cancelledAt,
                'cancelled_by_user_id' => $cancelledBy->id,
                'cancellation_reason' => $normalizedReason,
            ])->save();

            if ($orderItem->kitchenTicketItem instanceof KitchenTicketItem) {
                $orderItem->kitchenTicketItem->forceFill([
                    'status' => KitchenTicketItemStatus::Cancelled,
                ])->save();
            }

            $remainingItemCount = $this->remainingItemCount($order);
            $newOrderTotalCents = $this->remainingOrderTotalCents($order);

            $order->forceFill(['total_price_cents' => $newOrderTotalCents])->save();

            $this->createOrderStatusLog->handle(
                event: OrderStatusLogEvent::OrderItemCancelled,
                order: $order,
                actorUser: $cancelledBy,
                previousStatus: 'active',
                newStatus: 'cancelled',
                statusType: 'order_item',
                reason: $normalizedReason,
                metadata: [
                    'order_item_id' => $orderItem->id,
                    'kitchen_ticket_item_id' => $orderItem->kitchenTicketItem?->id,
                    'item_name' => $orderItem->historicalItemName(),
                    'quantity' => $orderItem->quantity,
                    'line_total_cents' => $orderItem->total_price_cents,
                    'remaining_order_items_count' => $remainingItemCount,
                    'previous_order_total_cents' => $previousOrderTotalCents,
                    'new_order_total_cents' => $newOrderTotalCents,
                ],
            );

            $this->recordAuditLog->handle(
                action: AuditLogAction::OrderItemVoided,
                entityType: 'order_item',
                entityId: $orderItem->id,
                actorUser: $cancelledBy,
                organizationId: $order->branch->organization_id,
                branchId: $order->branch_id,
                oldValues: [
                    'cancelled_at' => null,
                    'cancelled_by_user_id' => null,
                    'cancellation_reason' => null,
                    'kitchen_ticket_item_status' => $previousTicketStatus,
                    'order_total_cents' => $previousOrderTotalCents,
                ],
                newValues: [
                    'cancelled_at' => $cancelledAt,
                    'cancelled_by_user_id' => $cancelledBy->id,
                    'reason' => $normalizedReason,
                    'kitchen_ticket_item_status' => $orderItem->kitchenTicketItem?->status,
                    'order_total_cents' => $newOrderTotalCents,
                ],
            );

            $this->syncOrderAfterCancellation($order, $cancelledBy, $normalizedReason, $remainingItemCount);

            return $orderItem->refresh();
        }, attempts: 3);
    }

    private function orderIdentity(OrderItem $orderItem): Order
    {
        return Order::query()
            ->select(['id', 'table_session_id'])
            ->whereHas('items', fn ($query) => $query->whereKey($orderItem->id))
            ->firstOrFail();
    }

    private function lockedOrderItem(OrderItem $orderItem, Order $order): OrderItem
    {
        return OrderItem::query()
            ->select([
                'id',
                'order_id',
                'item_name',
                'item_name_snapshot',
                'quantity',
                'total_price_cents',
                'cancelled_at',
                'cancelled_by_user_id',
                'cancellation_reason',
            ])
            ->with(['kitchenTicketItem:id,kitchen_ticket_id,order_item_id,status,served_at'])
            ->whereKey($orderItem->id)
            ->where('order_id', $order->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockedOrder(int $orderId): Order
    {
        return Order::query()
            ->select([
                'id',
                'branch_id',
                'service_point_id',
                'table_session_id',
                'draft_order_id',
                'status',
                'total_price_cents',
                'metadata',
            ])
            ->with(['branch:id,organization_id'])
            ->whereKey($orderId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function authorizeCancellation(OrderItem $orderItem, User $cancelledBy): void
    {
        if (Gate::forUser($cancelledBy)->denies('cancel', $orderItem)) {
            throw BusinessRuleViolation::for(
                BusinessRuleCode::BranchInaccessible,
                'order_item_cancellation',
                __('orders.items.errors.permission_denied'),
            );
        }
    }

    private function lockedTableSession(int $tableSessionId): TableSession
    {
        return TableSession::query()
            ->select(['id', 'status'])
            ->whereKey($tableSessionId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensureCancellable(OrderItem $orderItem, Order $order, TableSession $tableSession): void
    {
        if ($orderItem->isCancelled()) {
            throw BusinessRuleViolation::for(
                BusinessRuleCode::OrderItemAlreadyCancelled,
                'order_item_cancellation',
            );
        }

        if (in_array($order->status, [
            OrderStatus::Cancelled,
            OrderStatus::Paid,
            OrderStatus::Closed,
        ], true)) {
            throw BusinessRuleViolation::for(
                BusinessRuleCode::OrderItemNotCancellable,
                'order_item_cancellation',
            );
        }

        if (in_array($tableSession->status, [
            TableSessionStatus::Paid,
            TableSessionStatus::Closed,
            TableSessionStatus::Cancelled,
        ], true)) {
            throw BusinessRuleViolation::for(
                BusinessRuleCode::SessionClosed,
                'order_item_cancellation',
                __('orders.items.errors.not_cancellable'),
            );
        }

        if (ManualPayment::query()->where('table_session_id', $order->table_session_id)->exists()) {
            throw BusinessRuleViolation::for(
                BusinessRuleCode::PaymentAlreadyRecorded,
                'order_item_cancellation',
            );
        }
    }

    private function validatedReason(string $reason): string
    {
        $validated = Validator::make(
            ['orderItemCancellationReason' => $reason],
            RestaurantValidationRules::auditReason('orderItemCancellationReason'),
            [
                'orderItemCancellationReason.required' => __('orders.items.errors.reason_required'),
                'orderItemCancellationReason.min' => __('orders.items.errors.reason_min'),
            ],
        )->validate();

        return PlainText::required($validated['orderItemCancellationReason'], 500);
    }

    private function remainingItemCount(Order $order): int
    {
        return OrderItem::query()
            ->where('order_id', $order->id)
            ->active()
            ->count();
    }

    private function remainingOrderTotalCents(Order $order): int
    {
        $total = OrderItem::query()
            ->where('order_id', $order->id)
            ->active()
            ->sum('total_price_cents');

        return (int) $total;
    }

    private function syncOrderAfterCancellation(Order $order, User $cancelledBy, string $reason, int $remainingItemCount): void
    {
        if ($remainingItemCount === 0) {
            $this->changeOrderStatus->handle(
                order: $order,
                newStatus: OrderStatus::Cancelled,
                changedBy: $cancelledBy,
                reason: $reason,
                metadata: ['source' => 'all_order_items_cancelled'],
            );

            return;
        }

        $this->syncOrderStatus->handle($order, $cancelledBy);
    }
}
