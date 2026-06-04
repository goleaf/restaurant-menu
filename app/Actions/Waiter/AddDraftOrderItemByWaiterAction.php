<?php

namespace App\Actions\Waiter;

use App\Actions\DraftOrders\Support\BuildDraftOrderItemModifierSnapshots;
use App\Actions\Menus\GetMenuAvailabilityStatusAction;
use App\Actions\Orders\CreateOrderStatusLogAction;
use App\Enums\DraftOrderStatus;
use App\Enums\MenuStatus;
use App\Enums\OrderStatusLogEvent;
use App\Enums\TableSessionGuestStatus;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\MenuItem;
use App\Models\TableSessionGuest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddDraftOrderItemByWaiterAction
{
    public function __construct(
        private readonly BuildDraftOrderItemModifierSnapshots $modifierSnapshots,
        private readonly EnsureWaiterCanEditDraftOrderAction $ensureWaiterCanEditDraftOrder,
        private readonly CreateOrderStatusLogAction $createOrderStatusLog,
    ) {}

    /**
     * @param  array<string, mixed>  $selectedModifierOptions
     */
    public function handle(
        DraftOrder $draftOrder,
        TableSessionGuest $guest,
        MenuItem $menuItem,
        User $editedBy,
        int $quantity,
        array $selectedModifierOptions,
        ?string $comment = null,
        ?string $itemName = null,
    ): DraftOrderItem {
        return DB::transaction(function () use ($draftOrder, $guest, $menuItem, $editedBy, $quantity, $selectedModifierOptions, $comment, $itemName): DraftOrderItem {
            $draftOrder = $this->reloadDraftOrder($draftOrder);
            $guest = $this->reloadGuest($guest);
            $menuItem = $this->reloadMenuItem($menuItem);

            $this->ensureWaiterCanEditDraftOrder->handle($draftOrder, $editedBy);
            $this->ensureGuestCanReceiveItem($draftOrder, $guest);
            $this->ensureMenuItemCanBeAdded($draftOrder, $menuItem);

            $quantity = $this->normalizeQuantity($quantity);
            $modifierGroups = $this->modifierSnapshots->groupsFor($menuItem);
            $selectedModifiers = $this->modifierSnapshots->snapshotsFor($modifierGroups, $selectedModifierOptions);
            $unitPriceCents = self::decimalToCents($menuItem->price);
            $modifierTotalCents = $this->modifierSnapshots->modifierTotalCents($selectedModifiers);
            $lineUnitTotalCents = max(0, $unitPriceCents + $modifierTotalCents);

            $previousStatus = $draftOrder->status;
            $this->markAsWaiterReview($draftOrder);

            $draftOrderItem = $draftOrder->items()->create([
                'table_session_guest_id' => $guest->id,
                'menu_item_id' => $menuItem->id,
                'item_name' => $this->snapshotName($itemName, $menuItem),
                'quantity' => $quantity,
                'unit_price' => self::centsToDecimal($unitPriceCents),
                'modifier_total' => self::centsToDecimal($modifierTotalCents),
                'total_price' => self::centsToDecimal($lineUnitTotalCents * $quantity),
                'selected_modifiers' => $selectedModifiers,
                'comment' => $this->normalizeComment($comment),
            ])->refresh();

            $this->createOrderStatusLog->handle(
                event: OrderStatusLogEvent::DraftEdited,
                draftOrder: $draftOrder,
                actorUser: $editedBy,
                previousStatus: $previousStatus,
                newStatus: $draftOrder->status,
                statusType: 'draft_order',
                metadata: [
                    'operation' => 'waiter_item_added',
                    'draft_order_item_id' => $draftOrderItem->id,
                    'guest_id' => $guest->id,
                    'menu_item_id' => $menuItem->id,
                    'quantity' => $draftOrderItem->quantity,
                ],
            );

            return $draftOrderItem;
        });
    }

    private function reloadDraftOrder(DraftOrder $draftOrder): DraftOrder
    {
        return DraftOrder::query()
            ->select([
                'id',
                'table_session_id',
                'status',
            ])
            ->with([
                'tableSession' => fn ($query) => $query
                    ->select(['id', 'branch_id', 'status'])
                    ->with(['branch:id,organization_id']),
            ])
            ->whereKey($draftOrder->id)
            ->firstOrFail();
    }

    private function reloadGuest(TableSessionGuest $guest): TableSessionGuest
    {
        return TableSessionGuest::query()
            ->select([
                'id',
                'table_session_id',
                'guest_name',
                'status',
            ])
            ->whereKey($guest->id)
            ->firstOrFail();
    }

    private function reloadMenuItem(MenuItem $menuItem): MenuItem
    {
        return MenuItem::query()
            ->select([
                'id',
                'menu_id',
                'category_id',
                'name',
                'price',
                'is_available',
            ])
            ->with([
                'menu' => fn ($query) => $query->select([
                    'id',
                    'branch_id',
                    'status',
                ])->with([
                    'branch' => fn ($branchQuery) => $branchQuery->select(['id', 'timezone']),
                    'availabilitySchedules' => fn ($scheduleQuery) => $scheduleQuery->select([
                        'id',
                        'menu_id',
                        'day_of_week',
                        'starts_at',
                        'ends_at',
                    ]),
                ]),
                'category' => fn ($query) => $query->select([
                    'id',
                    'menu_id',
                    'is_active',
                ]),
            ])
            ->whereKey($menuItem->id)
            ->firstOrFail();
    }

    private function ensureGuestCanReceiveItem(DraftOrder $draftOrder, TableSessionGuest $guest): void
    {
        if ($guest->table_session_id !== $draftOrder->table_session_id || $guest->status !== TableSessionGuestStatus::Active) {
            throw ValidationException::withMessages([
                'addingGuestId' => __('Выберите активного гостя за этим столом.'),
            ]);
        }
    }

    private function ensureMenuItemCanBeAdded(DraftOrder $draftOrder, MenuItem $menuItem): void
    {
        $tableSession = $draftOrder->tableSession;

        if ($tableSession === null
            || $menuItem->menu?->branch_id !== $tableSession->branch_id
            || $menuItem->menu?->status !== MenuStatus::Active
            || ! $menuItem->category?->is_active
            || ! $menuItem->is_available) {
            throw ValidationException::withMessages([
                'addingMenuItemId' => __('Это блюдо сейчас недоступно для этого филиала.'),
            ]);
        }

        $availability = app(GetMenuAvailabilityStatusAction::class)->handle($menuItem->menu);

        if (! $availability['is_available']) {
            throw ValidationException::withMessages([
                'addingMenuItemId' => __(':label. :detail', [
                    'label' => $availability['label'],
                    'detail' => $availability['detail'],
                ]),
            ]);
        }
    }

    private function normalizeQuantity(int $quantity): int
    {
        if ($quantity < 1 || $quantity > 99) {
            throw ValidationException::withMessages([
                'addingQuantity' => __('Количество должно быть от 1 до 99.'),
            ]);
        }

        return $quantity;
    }

    private function snapshotName(?string $itemName, MenuItem $menuItem): string
    {
        $normalizedItemName = str((string) $itemName)->squish()->toString();

        return $normalizedItemName === '' ? $menuItem->name : $normalizedItemName;
    }

    private function normalizeComment(?string $comment): ?string
    {
        $normalizedComment = trim((string) $comment);

        if ($normalizedComment === '') {
            return null;
        }

        if (mb_strlen($normalizedComment) > 500) {
            throw ValidationException::withMessages([
                'addingComment' => __('Комментарий слишком длинный.'),
            ]);
        }

        return $normalizedComment;
    }

    private function markAsWaiterReview(DraftOrder $draftOrder): void
    {
        if ($draftOrder->status === DraftOrderStatus::SentToWaiter) {
            $draftOrder
                ->forceFill(['status' => DraftOrderStatus::WaiterReview])
                ->save();
        }
    }

    private static function decimalToCents(string|int|float|null $amount): int
    {
        return (int) round(((float) ($amount ?? 0)) * 100);
    }

    private static function centsToDecimal(int $amount): string
    {
        $negative = $amount < 0;
        $absoluteAmount = abs($amount);
        $formatted = intdiv($absoluteAmount, 100).'.'.str_pad((string) ($absoluteAmount % 100), 2, '0', STR_PAD_LEFT);

        return $negative ? '-'.$formatted : $formatted;
    }
}
