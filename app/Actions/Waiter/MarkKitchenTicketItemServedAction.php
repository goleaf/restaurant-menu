<?php

declare(strict_types=1);

namespace App\Actions\Waiter;

use App\Actions\Orders\CreateOrderStatusLogAction;
use App\Actions\Orders\SyncOrderStatusFromTicketItemsAction;
use App\Enums\BusinessRuleCode;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderStatusLogEvent;
use App\Exceptions\BusinessRuleViolation;
use App\Models\KitchenTicketItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MarkKitchenTicketItemServedAction
{
    public function __construct(
        private readonly SyncOrderStatusFromTicketItemsAction $syncOrderStatus,
        private readonly CreateOrderStatusLogAction $createOrderStatusLog,
    ) {}

    public function handle(KitchenTicketItem $item, User $servedBy): KitchenTicketItem
    {
        return DB::transaction(function () use ($item, $servedBy): KitchenTicketItem {
            $item = $this->reloadItem($item);
            $order = $item->kitchenTicket->order;

            $this->ensureCanServe($order, $servedBy);

            if ($order->status === OrderStatus::Cancelled) {
                throw BusinessRuleViolation::for(
                    BusinessRuleCode::OrderAlreadyCancelled,
                    'order_service',
                    __('ui.actions.waiter.markkitchenticketitemservedaction.zakaz_otmenen_pozicii_b'),
                );
            }

            if ($item->status === KitchenTicketItemStatus::Cancelled) {
                throw BusinessRuleViolation::for(
                    BusinessRuleCode::OrderItemAlreadyCancelled,
                    'order_service',
                    __('orders.items.errors.kitchen_cancelled'),
                );
            }

            if ($item->served_at !== null) {
                return $item;
            }

            if ($this->itemStatus($item) !== KitchenTicketItemStatus::Ready) {
                throw ValidationException::withMessages([
                    'order_service' => __('ui.actions.waiter.markkitchenticketitemservedaction.podat_mozno_tolko_gotov'),
                ]);
            }

            $item
                ->forceFill([
                    'served_at' => now(),
                    'served_by_user_id' => $servedBy->id,
                ])
                ->save();

            $this->createOrderStatusLog->handle(
                event: OrderStatusLogEvent::TicketItemServed,
                order: $order,
                actorUser: $servedBy,
                previousStatus: KitchenTicketItemStatus::Ready,
                newStatus: 'served',
                statusType: 'kitchen_ticket_item',
                metadata: [
                    'kitchen_ticket_id' => $item->kitchen_ticket_id,
                    'kitchen_ticket_item_id' => $item->id,
                    'served_by_user_id' => $servedBy->id,
                ],
            );

            $this->syncOrderStatus->handle($order, $servedBy);

            return $item->refresh();
        });
    }

    private function reloadItem(KitchenTicketItem $item): KitchenTicketItem
    {
        return KitchenTicketItem::query()
            ->select([
                'id',
                'kitchen_ticket_id',
                'status',
                'served_at',
                'served_by_user_id',
            ])
            ->with([
                'kitchenTicket' => fn ($query) => $query
                    ->select(['id', 'order_id', 'branch_id'])
                    ->with(['order' => fn ($orderQuery) => $orderQuery->select([
                        'id',
                        'branch_id',
                        'service_point_id',
                        'table_session_id',
                        'draft_order_id',
                        'status',
                        'metadata',
                    ])]),
            ])
            ->whereKey($item->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensureCanServe(Order $order, User $user): void
    {
        if (Gate::forUser($user)->denies('markServed', $order)) {
            throw BusinessRuleViolation::for(
                BusinessRuleCode::BranchInaccessible,
                'order_service',
                __('ui.actions.waiter.markkitchenticketitemservedaction.u_vas_net_dostupa_k_pod'),
            );
        }
    }

    private function itemStatus(KitchenTicketItem $item): KitchenTicketItemStatus
    {
        return $item->status;
    }
}
