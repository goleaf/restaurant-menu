<?php

declare(strict_types=1);

namespace App\Actions\DraftOrders;

use App\Actions\DraftOrders\Support\CalculateDraftOrderLinePrice;
use App\Actions\Orders\CreateOrderStatusLogAction;
use App\Enums\DraftOrderStatus;
use App\Enums\OrderStatusLogEvent;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Models\DraftOrderItem;
use App\Models\MenuItem;
use App\Models\TableSessionGuest;
use App\Support\PlainText;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateGuestDraftOrderItemAction
{
    public function __construct(
        private readonly CalculateDraftOrderLinePrice $calculateLinePrice,
        private readonly CreateOrderStatusLogAction $createOrderStatusLog,
        private readonly EnsureDraftMenuItemAvailableAction $ensureMenuItemAvailable,
    ) {}

    /**
     * @param  array<string, mixed>  $selectedModifierOptions
     */
    public function handle(
        DraftOrderItem $draftOrderItem,
        TableSessionGuest $guest,
        int $quantity,
        array $selectedModifierOptions,
        ?int $menuItemVariantId = null,
        ?string $comment = null,
        ?string $languageCode = null,
    ): DraftOrderItem {
        return DB::transaction(function () use ($draftOrderItem, $guest, $quantity, $selectedModifierOptions, $menuItemVariantId, $comment, $languageCode): DraftOrderItem {
            $draftOrderItem = $this->reloadDraftOrderItem($draftOrderItem);
            $guest = $this->reloadGuest($guest);
            $this->ensureGuestCanEditItem($draftOrderItem, $guest);
            $menuItem = $draftOrderItem->menuItem;

            if (! $menuItem instanceof MenuItem) {
                throw ValidationException::withMessages([
                    'draft_item' => __('menu.guest.item_no_longer_available'),
                ]);
            }

            $this->ensureMenuItemAvailable->handle(
                $menuItem,
                (int) $draftOrderItem->draftOrder->tableSession->branch_id,
            );

            $quantity = $this->normalizeQuantity($quantity);
            $linePrice = $this->calculateLinePrice->forDraftOrderItem($draftOrderItem, $selectedModifierOptions, $quantity, $menuItemVariantId, $languageCode);

            $draftOrderItem->update([
                'quantity' => $quantity,
                'menu_item_variant_id' => $linePrice['menu_item_variant_id'],
                'variant_name' => $linePrice['variant_name'],
                'variant_type' => $linePrice['variant_type'],
                'unit_price_cents' => $linePrice['unit_price_cents'],
                'modifier_total_cents' => $linePrice['modifier_total_cents'],
                'total_price_cents' => $linePrice['total_price_cents'],
                'selected_modifiers' => $linePrice['selected_modifiers'],
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
                'draftOrder' => fn ($query) => $query
                    ->select([
                        'id',
                        'table_session_id',
                        'status',
                    ])
                    ->with([
                        'tableSession' => fn ($tableSessionQuery) => $tableSessionQuery->select([
                            'id',
                            'branch_id',
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
                'menuItem' => fn ($query) => $query
                    ->select(['id', 'menu_id', 'category_id', 'is_available', 'hidden_until'])
                    ->with([
                        'category' => fn ($categoryQuery) => $categoryQuery->select(['id', 'menu_id', 'is_active']),
                        'menu' => fn ($menuQuery) => $menuQuery
                            ->select(['id', 'branch_id', 'status'])
                            ->with([
                                'branch' => fn ($branchQuery) => $branchQuery->select(['id', 'timezone']),
                                'availabilitySchedules' => fn ($scheduleQuery) => $scheduleQuery->select([
                                    'id',
                                    'menu_id',
                                    'day_of_week',
                                    'starts_at',
                                    'ends_at',
                                ]),
                            ]),
                    ]),
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
        $tableSession = $draftOrder->tableSession;
        $servicePoint = $tableSession->servicePoint;

        if ($draftOrderItem->table_session_guest_id !== $guest->id
            || $draftOrder->table_session_id !== $guest->table_session_id
            || $guest->status !== TableSessionGuestStatus::Active
            || ! $servicePoint->is_active
            || in_array($tableSession->status, [TableSessionStatus::Closed, TableSessionStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'draft_item' => __('ui.actions.draftorders.updateguestdraftorderitemaction.mozno_izmeniat_tolko'),
            ]);
        }

        if ($draftOrder->status !== DraftOrderStatus::Draft) {
            throw ValidationException::withMessages([
                'draft_order' => __('ui.actions.draftorders.deleteguestdraftorderitemaction.etot_cernovik_uze_ot'),
            ]);
        }
    }

    private function normalizeQuantity(int $quantity): int
    {
        if ($quantity < 1 || $quantity > 99) {
            throw ValidationException::withMessages([
                'editingQuantity' => __('ui.actions.draftorders.updateguestdraftorderitemaction.kolicestvo_dolzno_by'),
            ]);
        }

        return $quantity;
    }

    private function normalizeComment(?string $comment): ?string
    {
        $normalizedComment = PlainText::optional($comment, 500);

        if ($normalizedComment === null) {
            return null;
        }

        return $normalizedComment;
    }
}
