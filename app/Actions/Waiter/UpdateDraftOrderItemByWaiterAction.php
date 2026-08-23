<?php

declare(strict_types=1);

namespace App\Actions\Waiter;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\DraftOrders\Support\CalculateDraftOrderLinePrice;
use App\Actions\Orders\CreateOrderStatusLogAction;
use App\Enums\AuditLogAction;
use App\Enums\DraftOrderStatus;
use App\Enums\OrderStatusLogEvent;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\User;
use App\Support\PlainText;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateDraftOrderItemByWaiterAction
{
    public function __construct(
        private readonly CalculateDraftOrderLinePrice $calculateLinePrice,
        private readonly EnsureWaiterCanEditDraftOrderAction $ensureWaiterCanEditDraftOrder,
        private readonly CreateOrderStatusLogAction $createOrderStatusLog,
        private readonly RecordAuditLogAction $recordAuditLog,
    ) {}

    /**
     * @param  array<string, mixed>  $selectedModifierOptions
     */
    public function handle(
        DraftOrderItem $draftOrderItem,
        User $editedBy,
        int $quantity,
        array $selectedModifierOptions,
        ?int $menuItemVariantId = null,
        ?string $comment = null,
    ): DraftOrderItem {
        return DB::transaction(function () use ($draftOrderItem, $editedBy, $quantity, $selectedModifierOptions, $menuItemVariantId, $comment): DraftOrderItem {
            $draftOrderItem = $this->reloadDraftOrderItem($draftOrderItem);
            $draftOrder = $draftOrderItem->draftOrder;

            $this->ensureWaiterCanEditDraftOrder->handle($draftOrder, $editedBy);

            $quantity = $this->normalizeQuantity($quantity);
            $linePrice = $this->calculateLinePrice->forDraftOrderItem($draftOrderItem, $selectedModifierOptions, $quantity, $menuItemVariantId);

            $previousStatus = $draftOrder->status;
            $oldValues = [
                'operation' => 'waiter_item_updated',
                'draft_order_id' => $draftOrder->id,
                'quantity' => $draftOrderItem->quantity,
                'total_price_cents' => $draftOrderItem->total_price_cents,
                'comment' => $draftOrderItem->comment,
            ];
            $this->markAsWaiterReview($draftOrder);

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

            $this->recordAuditLog->handle(
                action: AuditLogAction::DraftOrderEditedByWaiter,
                entityType: 'draft_order_item',
                entityId: $draftOrderItem->id,
                actorUser: $editedBy,
                organizationId: $draftOrder->tableSession?->branch?->organization_id,
                branchId: $draftOrder->tableSession?->branch_id,
                oldValues: $oldValues,
                newValues: [
                    'operation' => 'waiter_item_updated',
                    'draft_order_id' => $draftOrder->id,
                    'quantity' => $draftOrderItem->quantity,
                    'total_price_cents' => $draftOrderItem->total_price_cents,
                    'comment' => $draftOrderItem->comment,
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

    private function markAsWaiterReview(DraftOrder $draftOrder): void
    {
        if ($draftOrder->status === DraftOrderStatus::SentToWaiter) {
            $draftOrder
                ->forceFill(['status' => DraftOrderStatus::WaiterReview])
                ->save();
        }
    }
}
