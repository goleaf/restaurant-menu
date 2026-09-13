<?php

declare(strict_types=1);

namespace App\Actions\DraftOrders;

use App\Actions\Orders\CreateOrderStatusLogAction;
use App\Enums\DraftOrderStatus;
use App\Enums\OrderStatusLogEvent;
use App\Models\DraftOrder;
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
            $staleDraftOrderItem = $draftOrderItem;
            $guest = $this->reloadGuest($guest);
            $draftOrderItem = $this->reloadDraftOrderItem($draftOrderItem);

            if (! $draftOrderItem instanceof DraftOrderItem) {
                $staleDraftOrderItem = $this->staleDraftOrderItemForAuthorization($staleDraftOrderItem);
                $this->ensureGuestOwnsEditableDraftItem->handle($staleDraftOrderItem, $guest);

                return;
            }

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

    private function reloadDraftOrderItem(DraftOrderItem $draftOrderItem): ?DraftOrderItem
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
            ->lockForUpdate()
            ->first();
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
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function staleDraftOrderItemForAuthorization(DraftOrderItem $staleDraftOrderItem): DraftOrderItem
    {
        $draftOrder = DraftOrder::query()
            ->select(['id', 'table_session_id', 'status'])
            ->with([
                'tableSession' => fn ($query) => $query
                    ->select(['id', 'service_point_id', 'status', 'ended_at'])
                    ->with([
                        'servicePoint' => fn ($servicePointQuery) => $servicePointQuery->select([
                            'id',
                            'is_active',
                        ]),
                    ]),
            ])
            ->whereKey($staleDraftOrderItem->draft_order_id)
            ->lockForUpdate()
            ->firstOrFail();

        $staleDraftOrderItem->setRelation('draftOrder', $draftOrder);

        return $staleDraftOrderItem;
    }
}
