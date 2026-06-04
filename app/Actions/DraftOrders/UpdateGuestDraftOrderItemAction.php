<?php

namespace App\Actions\DraftOrders;

use App\Actions\DraftOrders\Support\BuildDraftOrderItemModifierSnapshots;
use App\Actions\Orders\CreateOrderStatusLogAction;
use App\Enums\DraftOrderStatus;
use App\Enums\OrderStatusLogEvent;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Models\DraftOrderItem;
use App\Models\ServicePoint;
use App\Models\TableSessionGuest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateGuestDraftOrderItemAction
{
    public function __construct(
        private BuildDraftOrderItemModifierSnapshots $modifierSnapshots,
        private readonly CreateOrderStatusLogAction $createOrderStatusLog,
    ) {}

    /**
     * @param  array<string, mixed>  $selectedModifierOptions
     */
    public function handle(
        DraftOrderItem $draftOrderItem,
        TableSessionGuest $guest,
        int $quantity,
        array $selectedModifierOptions,
        ?string $comment = null,
    ): DraftOrderItem {
        return DB::transaction(function () use ($draftOrderItem, $guest, $quantity, $selectedModifierOptions, $comment): DraftOrderItem {
            $draftOrderItem = $this->reloadDraftOrderItem($draftOrderItem);
            $guest = $this->reloadGuest($guest);
            $this->ensureGuestCanEditItem($draftOrderItem, $guest);

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

            $draftOrderItem->update([
                'quantity' => $quantity,
                'modifier_total' => self::centsToDecimal($modifierTotalCents),
                'total_price' => self::centsToDecimal($lineUnitTotalCents * $quantity),
                'selected_modifiers' => $selectedModifiers,
                'comment' => $this->normalizeComment($comment),
            ]);

            $this->createOrderStatusLog->handle(
                event: OrderStatusLogEvent::DraftEdited,
                draftOrder: $draftOrderItem->draftOrder,
                actorGuest: $guest,
                previousStatus: DraftOrderStatus::Draft,
                newStatus: DraftOrderStatus::Draft,
                statusType: 'draft_order',
                metadata: [
                    'operation' => 'guest_item_updated',
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
                        'tableSession' => fn ($tableSessionQuery) => $tableSessionQuery->select([
                            'id',
                            'service_point_id',
                            'status',
                            'ended_at',
                        ])
                            ->with([
                                'servicePoint' => fn ($servicePointQuery) => $servicePointQuery->select([
                                    'id',
                                    'is_active',
                                ]),
                            ]),
                    ]),
                'menuItem' => fn ($query) => $query->select(['id']),
            ])
            ->whereKey($draftOrderItem->id)
            ->firstOrFail();
    }

    private function reloadGuest(TableSessionGuest $guest): TableSessionGuest
    {
        return TableSessionGuest::query()
            ->select([
                'id',
                'table_session_id',
                'guest_name',
                'guest_token',
                'status',
                'joined_at',
                'left_at',
            ])
            ->whereKey($guest->id)
            ->firstOrFail();
    }

    private function ensureGuestCanEditItem(DraftOrderItem $draftOrderItem, TableSessionGuest $guest): void
    {
        $draftOrder = $draftOrderItem->draftOrder;
        $tableSession = $draftOrder?->tableSession;
        $servicePoint = $tableSession?->servicePoint;

        if ($draftOrderItem->table_session_guest_id !== $guest->id
            || $draftOrder?->table_session_id !== $guest->table_session_id
            || $guest->status !== TableSessionGuestStatus::Active
            || $tableSession === null
            || ! $servicePoint instanceof ServicePoint
            || ! $servicePoint->is_active
            || in_array($tableSession->status, [TableSessionStatus::Closed, TableSessionStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'draft_item' => __('Можно изменять только свои позиции за этим столом.'),
            ]);
        }

        if ($draftOrder->status !== DraftOrderStatus::Draft) {
            throw ValidationException::withMessages([
                'draft_order' => __('Этот черновик уже отправлен официанту. Изменения заблокированы.'),
            ]);
        }
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
