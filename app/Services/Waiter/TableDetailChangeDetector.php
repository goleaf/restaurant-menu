<?php

declare(strict_types=1);

namespace App\Services\Waiter;

use App\Models\DraftOrder;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TableSessionGuest;

final class TableDetailChangeDetector
{
    public function draftReviewFingerprint(int $tableSessionId): string
    {
        $draftOrder = DraftOrder::query()
            ->select(['id', 'table_session_id', 'status', 'updated_at'])
            ->where('table_session_id', $tableSessionId)
            ->latest('id')
            ->first();
        $draftOrderId = $draftOrder?->id;

        return $this->fingerprint([
            'draft_id' => $draftOrderId,
            'draft_status' => $draftOrder?->status?->value,
            'draft_updated_at' => $draftOrder?->updated_at?->toJSON(),
            'item_count' => $draftOrderId === null ? 0 : $draftOrder->items()->count(),
            'item_updated_at' => $draftOrderId === null ? null : $draftOrder->items()->max('updated_at'),
            'guest_count' => TableSessionGuest::query()->where('table_session_id', $tableSessionId)->count(),
            'guest_updated_at' => TableSessionGuest::query()->where('table_session_id', $tableSessionId)->max('updated_at'),
        ]);
    }

    public function orderFulfilmentFingerprint(int $tableSessionId): string
    {
        $orderQuery = Order::query()->where('table_session_id', $tableSessionId);
        $order = (clone $orderQuery)
            ->select(['id', 'table_session_id', 'status', 'updated_at'])
            ->latest('id')
            ->first();
        $ticketQuery = KitchenTicket::query()
            ->whereHas('order', fn ($query) => $query->where('table_session_id', $tableSessionId));
        $ticketItemQuery = KitchenTicketItem::query()
            ->whereHas('kitchenTicket.order', fn ($query) => $query->where('table_session_id', $tableSessionId));
        $orderItemQuery = OrderItem::query()
            ->whereHas('order', fn ($query) => $query->where('table_session_id', $tableSessionId));

        return $this->fingerprint([
            'order_id' => $order?->id,
            'order_status' => $order?->status?->value,
            'order_updated_at' => $order?->updated_at?->toJSON(),
            'order_count' => (clone $orderQuery)->count(),
            'orders_updated_at' => (clone $orderQuery)->max('updated_at'),
            'order_item_count' => $orderItemQuery->count(),
            'order_item_updated_at' => $orderItemQuery->max('updated_at'),
            'ticket_count' => $ticketQuery->count(),
            'ticket_updated_at' => $ticketQuery->max('updated_at'),
            'ticket_item_count' => $ticketItemQuery->count(),
            'ticket_item_updated_at' => $ticketItemQuery->max('updated_at'),
        ]);
    }

    /** @param array<string, int|string|null> $values */
    private function fingerprint(array $values): string
    {
        return hash('sha256', serialize($values));
    }
}
