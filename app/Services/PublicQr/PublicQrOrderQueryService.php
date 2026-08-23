<?php

declare(strict_types=1);

namespace App\Services\PublicQr;

use App\Enums\DraftOrderStatus;
use App\Enums\OrderStatus;
use App\Enums\TableSessionGuestStatus;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\KitchenTicketItem;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Support\MoneyFormatter;
use Illuminate\Support\Collection;

final class PublicQrOrderQueryService
{
    public function guestMenuTableSession(int $tableSessionId): ?TableSession
    {
        if ($tableSessionId < 1) {
            return null;
        }

        return TableSession::query()
            ->select(['id', 'branch_id', 'service_point_id', 'status', 'ended_at'])
            ->whereKey($tableSessionId)
            ->first();
    }

    public function menuItem(int $menuItemId): ?MenuItem
    {
        return MenuItem::query()
            ->select(['id'])
            ->whereKey($menuItemId)
            ->first();
    }

    public function statusTableSession(int $tableSessionId): ?TableSession
    {
        return TableSession::query()
            ->select(['id', 'status'])
            ->whereKey($tableSessionId)
            ->first();
    }

    public function statusDraftOrder(int $tableSessionId): ?DraftOrder
    {
        return DraftOrder::query()
            ->select(['id', 'table_session_id', 'status', 'rejection_reason'])
            ->with([
                'order' => fn ($query) => $query->select([
                    'id',
                    'draft_order_id',
                    'status',
                    'metadata',
                ]),
            ])
            ->where('table_session_id', $tableSessionId)
            ->latest('id')
            ->first();
    }

    /** @return Collection<int, Order> */
    public function recentOrders(int $tableSessionId): Collection
    {
        return Order::query()
            ->select(['id', 'table_session_id', 'draft_order_id', 'status', 'metadata'])
            ->where('table_session_id', $tableSessionId)
            ->latest('id')
            ->limit(20)
            ->get()
            ->sortBy('id')
            ->values();
    }

    /** @return Collection<int, KitchenTicketItem> */
    public function ticketItemsForOrder(?Order $order): Collection
    {
        if (! $order instanceof Order) {
            return collect();
        }

        return KitchenTicketItem::query()
            ->select(['id', 'kitchen_ticket_id', 'status', 'served_at'])
            ->whereHas('kitchenTicket', function ($query) use ($order): void {
                $query->where('order_id', $order->id);
            })
            ->orderBy('id')
            ->limit(200)
            ->get();
    }

    /** @return Collection<int, DraftOrderItem> */
    public function draftItems(DraftOrder $draftOrder): Collection
    {
        return DraftOrderItem::query()
            ->select([
                'id',
                'draft_order_id',
                'table_session_guest_id',
                'item_name',
                'quantity',
                'comment',
            ])
            ->with(['guest:id,guest_name'])
            ->where('draft_order_id', $draftOrder->id)
            ->orderBy('id')
            ->limit(200)
            ->get();
    }

    /** @param Collection<int, Order> $orders @return Collection<int, OrderItem> */
    public function orderItems(Collection $orders): Collection
    {
        $orderIds = $orders->pluck('id');

        if ($orderIds->isEmpty()) {
            return collect();
        }

        return OrderItem::query()
            ->select([
                'id',
                'order_id',
                'table_session_guest_id',
                'guest_name',
                'guest_name_snapshot',
                'item_name',
                'item_name_snapshot',
                'quantity',
                'comment',
                'cancelled_at',
            ])
            ->with([
                'guest:id,guest_name',
                'kitchenTicketItem:id,order_item_id,status,served_at',
            ])
            ->whereIn('order_id', $orderIds->all())
            ->orderBy('order_id')
            ->orderBy('id')
            ->limit(200)
            ->get();
    }

    /** @return Collection<int, TableSessionGuest> */
    public function activeGuestsForDraft(int $tableSessionId): Collection
    {
        return TableSessionGuest::query()
            ->select(['id', 'table_session_id', 'guest_name', 'status', 'ready_at'])
            ->where('table_session_id', $tableSessionId)
            ->where('status', TableSessionGuestStatus::Active->value)
            ->orderBy('guest_name')
            ->orderBy('id')
            ->limit(100)
            ->get();
    }

    public function draftOrderWithCart(int $tableSessionId, bool $includeOrderStatus = true): ?DraftOrder
    {
        $relations = [
            'items' => fn ($query) => $query
                ->select([
                    'id',
                    'draft_order_id',
                    'table_session_guest_id',
                    'menu_item_id',
                    'menu_item_variant_id',
                    'item_name',
                    'variant_name',
                    'variant_type',
                    'quantity',
                    'unit_price_cents',
                    'modifier_total_cents',
                    'total_price_cents',
                    'selected_modifiers',
                    'comment',
                    'created_at',
                ])
                ->with([
                    'guest' => fn ($guestQuery) => $guestQuery->select(['id', 'guest_name', 'status']),
                ])
                ->orderBy('created_at')
                ->orderBy('id'),
        ];

        if ($includeOrderStatus) {
            $relations['order'] = fn ($query) => $query
                ->select(['id', 'draft_order_id', 'status'])
                ->with(['kitchenTickets' => fn ($ticketQuery) => $ticketQuery
                    ->select(['id', 'order_id'])
                    ->with(['items' => fn ($itemQuery) => $itemQuery
                        ->select(['id', 'kitchen_ticket_id', 'status', 'served_at'])])]);
        }

        return DraftOrder::query()
            ->select(['id', 'table_session_id', 'status', 'rejection_reason'])
            ->with($relations)
            ->where('table_session_id', $tableSessionId)
            ->latest('id')
            ->first();
    }

    public function draftOrderWithTotals(int $tableSessionId): ?DraftOrder
    {
        return DraftOrder::query()
            ->select(['id', 'table_session_id', 'status'])
            ->with([
                'items' => fn ($query) => $query
                    ->select(['id', 'draft_order_id', 'table_session_guest_id', 'total_price_cents', 'created_at'])
                    ->with([
                        'guest' => fn ($guestQuery) => $guestQuery->select(['id', 'guest_name', 'status']),
                    ])
                    ->orderBy('created_at')
                    ->orderBy('id'),
            ])
            ->where('table_session_id', $tableSessionId)
            ->latest('id')
            ->first();
    }

    public function confirmedOrdersTotalCents(int $tableSessionId): int
    {
        return (int) Order::query()
            ->select(['id', 'table_session_id', 'status', 'total_price_cents'])
            ->where('table_session_id', $tableSessionId)
            ->whereNotIn('status', [OrderStatus::Cancelled->value])
            ->get()
            ->sum('total_price_cents');
    }

    /** @return list<array{guest_id: int, guest_name: string, total_cents: int}> */
    public function confirmedOrderItemGuestTotals(int $tableSessionId): array
    {
        return OrderItem::query()
            ->select([
                'id',
                'order_id',
                'table_session_guest_id',
                'guest_name',
                'guest_name_snapshot',
                'total_price_cents',
            ])
            ->with(['guest' => fn ($query) => $query->select(['id', 'guest_name'])])
            ->active()
            ->whereHas('order', function ($query) use ($tableSessionId): void {
                $query
                    ->where('table_session_id', $tableSessionId)
                    ->whereNotIn('status', [OrderStatus::Cancelled->value]);
            })
            ->orderBy('id')
            ->get()
            ->groupBy(function (OrderItem $item): string {
                if ((int) $item->table_session_guest_id > 0) {
                    return 'guest-'.$item->table_session_guest_id;
                }

                return 'snapshot-'.$item->historicalGuestName();
            })
            ->map(function (Collection $items): array {
                /** @var OrderItem $firstItem */
                $firstItem = $items->first();

                return [
                    'guest_id' => (int) $firstItem->table_session_guest_id,
                    'guest_name' => $firstItem->table_session_guest_id === null
                        ? ($firstItem->historicalGuestName() ?? (string) __('guest.table.guest'))
                        : $firstItem->guest->guest_name,
                    'total_cents' => (int) $items->sum('total_price_cents'),
                ];
            })
            ->values()
            ->all();
    }

    public function draftOrderForSending(int $tableSessionId): ?DraftOrder
    {
        return DraftOrder::query()
            ->select(['id', 'table_session_id', 'status'])
            ->where('table_session_id', $tableSessionId)
            ->where('status', DraftOrderStatus::Draft->value)
            ->latest('id')
            ->first();
    }

    public function editableDraftOrderItem(
        int $itemId,
        int $currentGuestId,
        int $tableSessionId,
    ): ?DraftOrderItem {
        $draftOrderItem = DraftOrderItem::query()
            ->select([
                'id',
                'draft_order_id',
                'table_session_guest_id',
                'menu_item_id',
                'menu_item_variant_id',
                'item_name',
                'variant_name',
                'variant_type',
                'quantity',
                'unit_price_cents',
                'modifier_total_cents',
                'total_price_cents',
                'selected_modifiers',
                'comment',
            ])
            ->with([
                'draftOrder' => fn ($query) => $query->select(['id', 'table_session_id', 'status']),
                'menuItem' => fn ($query) => $query->select(['id']),
            ])
            ->whereKey($itemId)
            ->where('table_session_guest_id', $currentGuestId)
            ->first();

        if (! $draftOrderItem instanceof DraftOrderItem
            || $draftOrderItem->draftOrder->table_session_id !== $tableSessionId) {
            return null;
        }

        return $draftOrderItem;
    }

    /** @return list<array{id: int, name: string, price_cents: int, formatted_price: string, is_default: bool}> */
    public function availableVariants(MenuItem $menuItem, string $currency): array
    {
        return $menuItem->variants()
            ->select(['id', 'menu_item_id', 'name', 'price_cents', 'is_default', 'sort_order'])
            ->where('is_available', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn ($variant): array => [
                'id' => $variant->id,
                'name' => $variant->name,
                'price_cents' => $variant->price_cents,
                'formatted_price' => MoneyFormatter::formatCents($variant->price_cents, $currency),
                'is_default' => $variant->is_default,
            ])
            ->values()
            ->all();
    }

    /** @return list<array{id: int, name: string, price_cents: int, formatted_price: string}> */
    public function localizedAvailableVariants(MenuItem $menuItem, string $language, string $currency): array
    {
        return $menuItem->variants()
            ->select(['id', 'menu_item_id', 'name', 'price_cents', 'is_default', 'sort_order'])
            ->where('is_available', true)
            ->with(['translations' => fn ($query) => $query
                ->select(['id', 'menu_item_variant_id', 'language_code', 'name'])
                ->where('language_code', $language)])
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn ($variant): array => [
                'id' => $variant->id,
                'name' => $variant->localizedName($language),
                'price_cents' => $variant->price_cents,
                'formatted_price' => MoneyFormatter::formatCents($variant->price_cents, $currency),
            ])
            ->values()
            ->all();
    }
}
