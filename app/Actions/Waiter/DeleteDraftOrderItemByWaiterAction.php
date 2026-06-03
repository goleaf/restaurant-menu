<?php

namespace App\Actions\Waiter;

use App\Enums\DraftOrderStatus;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteDraftOrderItemByWaiterAction
{
    public function __construct(
        private readonly EnsureWaiterCanEditDraftOrderAction $ensureWaiterCanEditDraftOrder,
    ) {}

    public function handle(DraftOrderItem $draftOrderItem, User $editedBy): void
    {
        DB::transaction(function () use ($draftOrderItem, $editedBy): void {
            $draftOrderItem = $this->reloadDraftOrderItem($draftOrderItem);
            $draftOrder = $draftOrderItem->draftOrder;

            if ($draftOrder === null) {
                throw ValidationException::withMessages([
                    'draft_edit' => __('Позиция больше не связана с черновиком.'),
                ]);
            }

            $this->ensureWaiterCanEditDraftOrder->handle($draftOrder, $editedBy);
            $this->markAsWaiterReview($draftOrder);

            $draftOrderItem->delete();
        });
    }

    private function reloadDraftOrderItem(DraftOrderItem $draftOrderItem): DraftOrderItem
    {
        return DraftOrderItem::query()
            ->select([
                'id',
                'draft_order_id',
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

    private function markAsWaiterReview(DraftOrder $draftOrder): void
    {
        if ($draftOrder->status === DraftOrderStatus::SentToWaiter) {
            $draftOrder
                ->forceFill(['status' => DraftOrderStatus::WaiterReview])
                ->save();
        }
    }
}
