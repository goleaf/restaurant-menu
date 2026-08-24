<?php

declare(strict_types=1);

namespace App\Actions\DraftOrders;

use App\Actions\Orders\CreateOrderStatusLogAction;
use App\Enums\DraftOrderStatus;
use App\Enums\OrderStatusLogEvent;
use App\Models\DraftOrderItem;
use App\Models\TableSessionGuest;
use Illuminate\Support\Facades\DB;

class DeleteGuestDraftOrderItemAction
{
    public function __construct(
        private readonly CreateOrderStatusLogAction $createOrderStatusLog,
        private readonly EnsureGuestOwnsEditableDraftItemAction $ensureGuestOwnsEditableDraftItem,
    ) {}

    public function handle(DraftOrderItem $draftOrderItem, TableSessionGuest $guest): void
    {
        DB::transaction(function () use ($draftOrderItem, $guest): void {
            $draftOrderItem = $this->reloadDraftOrderItem($draftOrderItem);
            $guest = $this->reloadGuest($guest);

            $this->ensureGuestOwnsEditableDraftItem->handle($draftOrderItem, $guest);

            $draftOrderItem->delete();

            $this->createOrderStatusLog->handle(
                event: OrderStatusLogEvent::DraftEdited,
                draftOrder: $draftOrderItem->draftOrder,
                actorGuest: $guest,
                previousStatus: DraftOrderStatus::Draft,
                newStatus: DraftOrderStatus::Draft,
                statusType: 'draft_order',
                metadata: [
                    'operation' => 'guest_item_deleted',
                    'draft_order_item_id' => $draftOrderItem->id,
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
}
