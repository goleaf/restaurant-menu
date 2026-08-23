<?php

declare(strict_types=1);

namespace App\Services\Waiter;

use App\Actions\Menus\GetMenuAvailabilityStatusAction;
use App\Enums\MenuStatus;
use App\Models\Branch;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\KitchenTicketItem;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\WaiterCall;
use App\Support\MoneyFormatter;

final class WaiterTableQueryService
{
    public function __construct(
        private readonly GetMenuAvailabilityStatusAction $getMenuAvailabilityStatus,
    ) {}

    public function tableSession(int $tableSessionId): TableSession
    {
        return TableSession::query()
            ->select(['id', 'branch_id'])
            ->whereKey($tableSessionId)
            ->firstOrFail();
    }

    public function servicePoint(int $servicePointId): ?ServicePoint
    {
        if ($servicePointId < 1) {
            return null;
        }

        return ServicePoint::query()
            ->select(['id', 'branch_id'])
            ->whereKey($servicePointId)
            ->first();
    }

    public function waiterCall(int $waiterCallId): WaiterCall
    {
        return WaiterCall::query()
            ->select(['id'])
            ->whereKey($waiterCallId)
            ->firstOrFail();
    }

    public function branch(int $branchId): Branch
    {
        return Branch::query()
            ->select(['id', 'timezone', 'is_temporarily_closed', 'temporary_closed_reason', 'temporary_closed_until'])
            ->whereKey($branchId)
            ->firstOrFail();
    }

    public function orderItemForTable(int $orderItemId, int $tableSessionId): ?OrderItem
    {
        return OrderItem::query()
            ->select(['id', 'order_id'])
            ->whereKey($orderItemId)
            ->whereHas('order', fn ($query) => $query->where('table_session_id', $tableSessionId))
            ->first();
    }

    public function kitchenTicketItem(int $ticketItemId): ?KitchenTicketItem
    {
        return KitchenTicketItem::query()
            ->select(['id'])
            ->whereKey($ticketItemId)
            ->first();
    }

    public function currentOrder(int $tableSessionId): ?Order
    {
        $tableSession = TableSession::query()
            ->select(['id'])
            ->with([
                'draftOrder' => fn ($query) => $query
                    ->select(['draft_orders.id', 'draft_orders.table_session_id'])
                    ->with(['order' => fn ($orderQuery) => $orderQuery->select(['id', 'draft_order_id', 'status'])]),
            ])
            ->whereKey($tableSessionId)
            ->firstOrFail();

        return $tableSession->draftOrder?->order;
    }

    public function currentDraftOrder(int $tableSessionId): ?DraftOrder
    {
        $tableSession = TableSession::query()
            ->select(['id'])
            ->with(['draftOrder' => fn ($query) => $query->select(['draft_orders.id', 'draft_orders.table_session_id', 'draft_orders.status'])])
            ->whereKey($tableSessionId)
            ->firstOrFail();

        return $tableSession->draftOrder;
    }

    public function guestForTable(int $guestId, int $tableSessionId, bool $includeName = false): ?TableSessionGuest
    {
        if ($guestId < 1) {
            return null;
        }

        $columns = $includeName
            ? ['id', 'table_session_id', 'guest_name']
            : ['id'];

        return TableSessionGuest::query()
            ->select($columns)
            ->where('table_session_id', $tableSessionId)
            ->whereKey($guestId)
            ->first();
    }

    public function draftOrderItemForTable(int $itemId, int $tableSessionId): ?DraftOrderItem
    {
        $draftOrderItem = DraftOrderItem::query()
            ->select([
                'id', 'draft_order_id', 'table_session_guest_id', 'menu_item_id', 'menu_item_variant_id', 'item_name',
                'variant_name', 'variant_type', 'quantity',
                'unit_price_cents', 'modifier_total_cents', 'total_price_cents', 'selected_modifiers', 'comment',
            ])
            ->with([
                'draftOrder' => fn ($query) => $query->select(['id', 'table_session_id', 'status']),
                'menuItem' => fn ($query) => $query->select(['id']),
            ])
            ->whereKey($itemId)
            ->first();

        if (! $draftOrderItem instanceof DraftOrderItem
            || $draftOrderItem->draftOrder->table_session_id !== $tableSessionId) {
            return null;
        }

        return $draftOrderItem;
    }

    /** @return list<array{value: string, label: string, price: string}> */
    public function menuItemOptionsForBranch(int $branchId): array
    {
        if ($branchId < 1) {
            return [];
        }

        return MenuItem::query()
            ->select(['id', 'menu_id', 'category_id', 'name', 'price_cents', 'is_available', 'sort_order'])
            ->with([
                'menu' => fn ($query) => $query->select(['id', 'branch_id', 'status', 'name'])
                    ->with([
                        'branch' => fn ($branchQuery) => $branchQuery->select(['id', 'timezone']),
                        'availabilitySchedules' => fn ($scheduleQuery) => $scheduleQuery->select([
                            'id', 'menu_id', 'day_of_week', 'starts_at', 'ends_at',
                        ]),
                    ]),
                'category' => fn ($query) => $query->select(['id', 'menu_id', 'name', 'is_active']),
            ])
            ->whereHas('menu', function ($query) use ($branchId): void {
                $query->where('branch_id', $branchId)->where('status', MenuStatus::Active->value);
            })
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->where('is_available', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->filter(function (MenuItem $menuItem): bool {
                return $menuItem->menu !== null
                    && $this->getMenuAvailabilityStatus->handle($menuItem->menu)['is_available'];
            })
            ->map(fn (MenuItem $menuItem): array => [
                'value' => (string) $menuItem->id,
                'label' => trim(($menuItem->category->name ? $menuItem->category->name.' · ' : '').$menuItem->name),
                'price' => MoneyFormatter::centsToDecimal($menuItem->price_cents),
            ])
            ->values()
            ->all();
    }

    /** @param list<int> $allowedMenuItemIds */
    public function configuredMenuItem(int $menuItemId, array $allowedMenuItemIds): ?MenuItem
    {
        if ($menuItemId < 1 || ! in_array($menuItemId, $allowedMenuItemIds, true)) {
            return null;
        }

        return MenuItem::query()
            ->select(['id', 'menu_id', 'category_id', 'name', 'price_cents', 'is_available'])
            ->whereKey($menuItemId)
            ->first();
    }

    /**
     * @return list<array{id: int, name: string, price_cents: int, formatted_price: string, is_default: bool}>
     */
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
}
