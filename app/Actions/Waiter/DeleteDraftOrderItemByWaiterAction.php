<?php

declare(strict_types=1);

namespace App\Actions\Waiter;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Orders\CreateOrderStatusLogAction;
use App\Enums\AuditLogAction;
use App\Enums\OrderStatusLogEvent;
use App\Models\DraftOrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteDraftOrderItemByWaiterAction
{
    public function __construct(
        private readonly EnsureWaiterCanEditDraftOrderAction $ensureWaiterCanEditDraftOrder,
        private readonly CreateOrderStatusLogAction $createOrderStatusLog,
        private readonly RecordAuditLogAction $recordAuditLog,
        private readonly MoveDraftOrderToWaiterReviewAction $moveDraftOrderToWaiterReview,
    ) {}

    public function handle(DraftOrderItem $draftOrderItem, User $editedBy): void
    {
        DB::transaction(function () use ($draftOrderItem, $editedBy): void {
            $draftOrderItem = $this->reloadDraftOrderItem($draftOrderItem);
            $draftOrder = $draftOrderItem->draftOrder;

            $this->ensureWaiterCanEditDraftOrder->handle($draftOrder, $editedBy);
            $previousStatus = $draftOrder->status;
            $this->moveDraftOrderToWaiterReview->handle($draftOrder);
            $oldValues = [
                'operation' => 'waiter_item_deleted',
                'draft_order_id' => $draftOrder->id,
                'quantity' => $draftOrderItem->quantity,
                'total_price_cents' => $draftOrderItem->total_price_cents,
                'comment' => $draftOrderItem->comment,
            ];

            $draftOrderItem->delete();

            $this->createOrderStatusLog->handle(
                event: OrderStatusLogEvent::DraftEdited,
                draftOrder: $draftOrder,
                actorUser: $editedBy,
                previousStatus: $previousStatus,
                newStatus: $draftOrder->status,
                statusType: 'draft_order',
                metadata: [
                    'operation' => 'waiter_item_deleted',
                    'draft_order_item_id' => $draftOrderItem->id,
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
                    'operation' => 'waiter_item_deleted',
                    'draft_order_id' => $draftOrder->id,
                    'deleted' => true,
                ],
            );
        });
    }

    private function reloadDraftOrderItem(DraftOrderItem $draftOrderItem): DraftOrderItem
    {
        return DraftOrderItem::query()
            ->select([
                'id',
                'draft_order_id',
                'quantity',
                'total_price_cents',
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
            ])
            ->whereKey($draftOrderItem->id)
            ->firstOrFail();
    }
}
