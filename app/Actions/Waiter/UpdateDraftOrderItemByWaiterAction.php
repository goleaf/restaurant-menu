<?php

namespace App\Actions\Waiter;

use App\Actions\DraftOrders\Support\BuildDraftOrderItemModifierSnapshots;
use App\Actions\Orders\CreateOrderStatusLogAction;
use App\Enums\DraftOrderStatus;
use App\Enums\OrderStatusLogEvent;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateDraftOrderItemByWaiterAction
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
        DraftOrderItem $draftOrderItem,
        User $editedBy,
        int $quantity,
        array $selectedModifierOptions,
        ?string $comment = null,
    ): DraftOrderItem {
        return DB::transaction(function () use ($draftOrderItem, $editedBy, $quantity, $selectedModifierOptions, $comment): DraftOrderItem {
            $draftOrderItem = $this->reloadDraftOrderItem($draftOrderItem);
            $draftOrder = $draftOrderItem->draftOrder;

            if ($draftOrder === null) {
                throw ValidationException::withMessages([
                    'draft_edit' => __('Позиция больше не связана с черновиком.'),
                ]);
            }

            $this->ensureWaiterCanEditDraftOrder->handle($draftOrder, $editedBy);

            $quantity = $this->normalizeQuantity($quantity);
            $selectedModifiers = $draftOrderItem->selected_modifiers ?? [];
            $modifierTotalCents = self::decimalToCents($draftOrderItem->modifier_total);

            if ($draftOrderItem->menuItem !== null) {
                $modifierGroups = $this->modifierSnapshots->groupsFor($draftOrderItem->menuItem);
                $selectedModifiers = $this->modifierSnapshots->snapshotsFor($modifierGroups, $selectedModifierOptions);
                $modifierTotalCents = $this->modifierSnapshots->modifierTotalCents($selectedModifiers);
            }

            $unitPriceCents = self::decimalToCents($draftOrderItem->unit_price);
            $lineUnitTotalCents = max(0, $unitPriceCents + $modifierTotalCents);

            $previousStatus = $draftOrder->status;
            $this->markAsWaiterReview($draftOrder);

            $draftOrderItem->update([
                'quantity' => $quantity,
                'modifier_total' => self::centsToDecimal($modifierTotalCents),
                'total_price' => self::centsToDecimal($lineUnitTotalCents * $quantity),
                'selected_modifiers' => $selectedModifiers,
                'comment' => $this->normalizeComment($comment),
            ]);

            $this->createOrderStatusLog->handle(
                event: OrderStatusLogEvent::DraftEdited,
                draftOrder: $draftOrder,
                actorUser: $editedBy,
                previousStatus: $previousStatus,
                newStatus: $draftOrder->status,
                statusType: 'draft_order',
                metadata: [
                    'operation' => 'waiter_item_updated',
                    'draft_order_item_id' => $draftOrderItem->id,
                    'quantity' => $quantity,
                ],
            );

            return $draftOrderItem->refresh();
        });
    }

    private function reloadDraftOrderItem(DraftOrderItem $draftOrderItem): DraftOrderItem
    {
        return DraftOrderItem::query()
            ->select([
                'id',
                'draft_order_id',
                'table_session_guest_id',
                'menu_item_id',
                'item_name',
                'quantity',
                'unit_price',
                'modifier_total',
                'total_price',
                'selected_modifiers',
                'comment',
            ])
            ->with([
                'draftOrder' => fn ($query) => $query
                    ->select([
                        'id',
                        'table_session_id',
                        'status',
                    ])
                    ->with([
                        'tableSession' => fn ($tableSessionQuery) => $tableSessionQuery
                            ->select([
                                'id',
                                'branch_id',
                                'status',
                            ])
                            ->with(['branch:id,organization_id']),
                    ]),
                'menuItem' => fn ($query) => $query->select(['id']),
            ])
            ->whereKey($draftOrderItem->id)
            ->firstOrFail();
    }

    private function normalizeQuantity(int $quantity): int
    {
        if ($quantity < 1 || $quantity > 99) {
            throw ValidationException::withMessages([
                'editingQuantity' => __('Количество должно быть от 1 до 99.'),
            ]);
        }

        return $quantity;
    }

    private function normalizeComment(?string $comment): ?string
    {
        $normalizedComment = trim((string) $comment);

        if ($normalizedComment === '') {
            return null;
        }

        if (mb_strlen($normalizedComment) > 500) {
            throw ValidationException::withMessages([
                'editingComment' => __('Комментарий слишком длинный.'),
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
