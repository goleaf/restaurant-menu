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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

            $items = $this->ticketItemsFor($order);

            if ($items->isEmpty()) {
                return $order;
            }

            $newStatus = $this->statusForItems($items);

            if (! $order->status->canTransitionTo($newStatus)) {
                return $order;
            }

            $this->syncServicePointStatus($order, $newStatus);

            if ($order->status === $newStatus) {
                return $this->reloadOrder($order);
            }

            $previousStatus = $order->status;
            $metadata = $order->metadata ?? [];

            $order
                ->forceFill([
                    'status' => $newStatus,
                    'metadata' => array_merge($metadata, [
                        'ticket_items_status_synced_at' => now()->toISOString(),
                        'ticket_items_status_synced_by_user_id' => $changedBy->id,
                    ]),
                ])
                ->save();

            $this->createOrderStatusLog->handle(
                event: OrderStatusLogEvent::OrderStatusChanged,
                order: $order,
                actorUser: $changedBy,
                previousStatus: $previousStatus,
                newStatus: $newStatus,
                statusType: 'order',
                metadata: [
                    'source' => 'ticket_item_status_sync',
                    'ticket_items_count' => $items->count(),
                    'ready_ticket_items_count' => $items->filter(
                        fn (KitchenTicketItem $item): bool => $this->itemStatus($item) === KitchenTicketItemStatus::Ready,
                    )->count(),
                    'served_ticket_items_count' => $items->filter(
                        fn (KitchenTicketItem $item): bool => $item->served_at !== null,
                    )->count(),
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
                'servicePoint' => fn ($query) => $query->select(['id', 'status']),
            ])
            ->whereKey($order->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function canSync(Order $order): bool
    {
        return in_array($order->status, [
            OrderStatus::SentToKitchenBar,
            OrderStatus::InProgress,
            OrderStatus::Ready,
            OrderStatus::Served,
        ], true);
    }

    /**
     * @return Collection<int, KitchenTicketItem>
     */
    private function ticketItemsFor(Order $order): Collection
    {
        return KitchenTicketItem::query()
            ->select(['id', 'kitchen_ticket_id', 'status', 'served_at'])
            ->whereHas('kitchenTicket', function ($query) use ($order): void {
                $query->where('order_id', $order->id);
            })
            ->where('status', '!=', KitchenTicketItemStatus::Cancelled->value)
            ->orderBy('id')
            ->limit(1000)
            ->get();
    }

    /**
     * @param  Collection<int, KitchenTicketItem>  $items
     */
    private function statusForItems(Collection $items): OrderStatus
    {
        if ($items->every(fn (KitchenTicketItem $item): bool => $item->served_at !== null)) {
            return OrderStatus::Served;
        }

        if ($items->every(fn (KitchenTicketItem $item): bool => $this->itemStatus($item) === KitchenTicketItemStatus::Ready)) {
            return OrderStatus::Ready;
        }

        if ($items->contains(fn (KitchenTicketItem $item): bool => in_array($this->itemStatus($item), [
            KitchenTicketItemStatus::InProgress,
            KitchenTicketItemStatus::Ready,
        ], true))) {
            return OrderStatus::InProgress;
        }

        return OrderStatus::SentToKitchenBar;
    }

    private function itemStatus(KitchenTicketItem $item): KitchenTicketItemStatus
    {
        return $item->status;
    }

    private function syncServicePointStatus(Order $order, OrderStatus $newStatus): void
    {
        $servicePoint = $order->servicePoint;

        if ($servicePoint === null || ! $this->canUpdateServicePoint($servicePoint->status)) {
            return;
        }

        $newServicePointStatus = match ($newStatus) {
            OrderStatus::Ready => ServicePointStatus::ReadyToServe,
            OrderStatus::Served => ServicePointStatus::Occupied,
            default => ServicePointStatus::Cooking,
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
