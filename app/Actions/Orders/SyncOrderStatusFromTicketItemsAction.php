<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\ServicePoints\UpdateServicePointStatusAction;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderStatusLogEvent;
use App\Enums\ServicePointStatus;
use App\Models\KitchenTicketItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SyncOrderStatusFromTicketItemsAction
{
    public function __construct(
        private readonly UpdateServicePointStatusAction $updateServicePointStatus,
        private readonly CreateOrderStatusLogAction $createOrderStatusLog,
    ) {}

    public function handle(Order $order, User $changedBy): Order
    {
        return DB::transaction(function () use ($order, $changedBy): Order {
            $order = $this->reloadOrder($order);

            if (! $this->canSync($order)) {
                return $order;
            }

            if ((int) $order->getAttribute('active_item_count') === 0) {
                return $order;
            }

            $newStatus = $this->statusForItems($order);

            if (! $order->status->canTransitionTo($newStatus)) {
                return $order;
            }

            if ($order->status === $newStatus) {
                $this->syncServicePointStatus($order);

                return $this->reloadOrder($order);
            }

            $previousStatus = $order->status;
            $metadata = $order->metadata ?? [];

            $saved = $order
                ->forceFill([
                    'status' => $newStatus,
                    'metadata' => array_merge($metadata, [
                        'ticket_items_status_synced_at' => now()->toISOString(),
                        'ticket_items_status_synced_by_user_id' => $changedBy->id,
                    ]),
                ])
                ->save();

            if (! $saved) {
                throw new RuntimeException('The synchronized order status could not be persisted.');
            }

            $this->syncServicePointStatus($order);

            $this->createOrderStatusLog->handle(
                event: OrderStatusLogEvent::OrderStatusChanged,
                order: $order,
                actorUser: $changedBy,
                previousStatus: $previousStatus,
                newStatus: $newStatus,
                statusType: 'order',
                metadata: [
                    'source' => 'ticket_item_status_sync',
                    'ticket_items_count' => (int) $order->getAttribute('active_item_count'),
                    'ready_ticket_items_count' => (int) $order->getAttribute('ready_item_count'),
                    'served_ticket_items_count' => (int) $order->getAttribute('served_item_count'),
                ],
            );

            return $this->reloadOrder($order);
        }, attempts: 3);
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
                'metadata',
            ])
            ->with([
                'tableSession' => fn ($query) => $query
                    ->select(['id', 'branch_id', 'service_point_id', 'status'])
                    ->with(['servicePoint' => fn ($pointQuery) => $pointQuery->select(['id', 'branch_id', 'status'])]),
            ])
            ->withCount([
                'kitchenTicketItems as active_item_count' => fn ($query) => $query
                    ->where('kitchen_ticket_items.status', '!=', KitchenTicketItemStatus::Cancelled->value),
                'kitchenTicketItems as ready_item_count' => fn ($query) => $query
                    ->where('kitchen_ticket_items.status', KitchenTicketItemStatus::Ready->value),
                'kitchenTicketItems as served_item_count' => fn ($query) => $query
                    ->where('kitchen_ticket_items.status', '!=', KitchenTicketItemStatus::Cancelled->value)
                    ->whereNotNull('kitchen_ticket_items.served_at'),
                'kitchenTicketItems as started_item_count' => fn ($query) => $query
                    ->whereIn('kitchen_ticket_items.status', [KitchenTicketItemStatus::InProgress->value, KitchenTicketItemStatus::Ready->value]),
            ])
            ->whereKey($order->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function canSync(Order $order): bool
    {
        return $order->tableSession !== null
            && ! $order->tableSession->status->locksOrderChanges()
            && in_array($order->status, [
                OrderStatus::SentToKitchenBar,
                OrderStatus::InProgress,
                OrderStatus::Ready,
                OrderStatus::Served,
            ], true);
    }

    private function statusForItems(Order $order): OrderStatus
    {
        $total = (int) $order->getAttribute('active_item_count');

        if ((int) $order->getAttribute('served_item_count') === $total) {
            return OrderStatus::Served;
        }

        if ((int) $order->getAttribute('ready_item_count') === $total) {
            return OrderStatus::Ready;
        }

        if ((int) $order->getAttribute('started_item_count') > 0) {
            return OrderStatus::InProgress;
        }

        return OrderStatus::SentToKitchenBar;
    }

    private function syncServicePointStatus(Order $order): void
    {
        $servicePoint = $order->tableSession?->servicePoint;

        if ($servicePoint === null || ! $this->canUpdateServicePoint($servicePoint->status)) {
            return;
        }

        $items = KitchenTicketItem::query()
            ->whereNull('served_at')
            ->where('status', '!=', KitchenTicketItemStatus::Cancelled->value)
            ->whereHas('kitchenTicket', fn ($query) => $query
                ->where('branch_id', $order->branch_id)
                ->where('table_session_id', $order->table_session_id)
                ->whereHas('order', fn ($orderQuery) => $orderQuery->whereIn('status', [
                    OrderStatus::SentToKitchenBar->value,
                    OrderStatus::InProgress->value,
                    OrderStatus::Ready->value,
                ])));
        $newServicePointStatus = match (true) {
            (clone $items)->where('status', KitchenTicketItemStatus::Ready->value)->exists() => ServicePointStatus::ReadyToServe,
            $items->exists() => ServicePointStatus::Cooking,
            default => ServicePointStatus::Occupied,
        };

        if ($servicePoint->status === $newServicePointStatus) {
            return;
        }

        $this->updateServicePointStatus->handle($servicePoint, $newServicePointStatus);
    }

    private function canUpdateServicePoint(ServicePointStatus|string|null $status): bool
    {
        $servicePointStatus = $status instanceof ServicePointStatus
            ? $status
            : ServicePointStatus::tryFrom((string) $status);

        return in_array($servicePointStatus, [
            ServicePointStatus::Occupied,
            ServicePointStatus::HasNewOrder,
            ServicePointStatus::Cooking,
            ServicePointStatus::ReadyToServe,
        ], true);
    }
}
