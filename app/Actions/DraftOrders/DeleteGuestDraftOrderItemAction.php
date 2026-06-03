<?php

namespace App\Actions\DraftOrders;

use App\Enums\DraftOrderStatus;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Models\DraftOrderItem;
use App\Models\TableSessionGuest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteGuestDraftOrderItemAction
{
    public function handle(DraftOrderItem $draftOrderItem, TableSessionGuest $guest): void
    {
        DB::transaction(function () use ($draftOrderItem, $guest): void {
            $draftOrderItem = $this->reloadDraftOrderItem($draftOrderItem);
            $guest = $this->reloadGuest($guest);

            $this->ensureGuestCanDeleteItem($draftOrderItem, $guest);

            $draftOrderItem->delete();
        });
    }

    private function reloadDraftOrderItem(DraftOrderItem $draftOrderItem): DraftOrderItem
    {
        return DraftOrderItem::query()
            ->select([
                'id',
                'draft_order_id',
                'table_session_guest_id',
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
                            'status',
                            'ended_at',
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

    private function ensureGuestCanDeleteItem(DraftOrderItem $draftOrderItem, TableSessionGuest $guest): void
    {
        $draftOrder = $draftOrderItem->draftOrder;
        $tableSession = $draftOrder?->tableSession;

        if ($draftOrderItem->table_session_guest_id !== $guest->id
            || $draftOrder?->table_session_id !== $guest->table_session_id
            || $guest->status !== TableSessionGuestStatus::Active
            || $tableSession === null
            || in_array($tableSession->status, [TableSessionStatus::Closed, TableSessionStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'draft_item' => __('Можно удалить только свою позицию за этим столом.'),
            ]);
        }

        if ($draftOrder->status !== DraftOrderStatus::Draft) {
            throw ValidationException::withMessages([
                'draft_order' => __('Этот черновик уже отправлен официанту. Изменения заблокированы.'),
            ]);
        }
    }
}
