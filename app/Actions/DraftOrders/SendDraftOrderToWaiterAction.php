<?php

namespace App\Actions\DraftOrders;

use App\Actions\Orders\CreateOrderStatusLogAction;
use App\Actions\ServicePoints\UpdateServicePointStatusAction;
use App\Enums\DraftOrderStatus;
use App\Enums\OrderStatusLogEvent;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Models\DraftOrder;
use App\Models\TableSessionGuest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SendDraftOrderToWaiterAction
{
    public function __construct(
        private UpdateServicePointStatusAction $updateServicePointStatus,
        private readonly CreateOrderStatusLogAction $createOrderStatusLog,
    ) {}

    public function handle(DraftOrder $draftOrder, TableSessionGuest $sentByGuest): DraftOrder
    {
        return DB::transaction(function () use ($draftOrder, $sentByGuest): DraftOrder {
            $draftOrder = $this->reloadDraftOrder($draftOrder);
            $sentByGuest = $this->reloadGuest($sentByGuest);

            $this->ensureDraftCanBeSent($draftOrder, $sentByGuest);
            $previousStatus = $draftOrder->status;

            $draftOrder
                ->forceFill([
                    'status' => DraftOrderStatus::SentToWaiter,
                    'sent_to_waiter_at' => now(),
                    'sent_by_guest_id' => $sentByGuest->id,
                ])
                ->save();

            $draftOrder->tableSession?->activeGuests()->update([
                'ready_at' => null,
            ]);

            $servicePoint = $draftOrder->tableSession?->servicePoint;

            if ($servicePoint !== null) {
                $this->updateServicePointStatus->handle($servicePoint, ServicePointStatus::HasNewOrder);
            }

            $this->createOrderStatusLog->handle(
                event: OrderStatusLogEvent::DraftSentToWaiter,
                draftOrder: $draftOrder,
                actorGuest: $sentByGuest,
                previousStatus: $previousStatus,
                newStatus: DraftOrderStatus::SentToWaiter,
                statusType: 'draft_order',
                metadata: ['items_count' => (int) $draftOrder->items_count],
            );

            return $draftOrder->refresh();
        });
    }

    private function reloadDraftOrder(DraftOrder $draftOrder): DraftOrder
    {
        return DraftOrder::query()
            ->select([
                'id',
                'table_session_id',
                'status',
                'sent_to_waiter_at',
                'sent_by_guest_id',
            ])
            ->withCount('items')
            ->with([
                'tableSession' => fn ($query) => $query
                    ->select([
                        'id',
                        'service_point_id',
                        'status',
                        'ended_at',
                    ])
                    ->with([
                        'servicePoint' => fn ($servicePointQuery) => $servicePointQuery->select([
                            'id',
                            'status',
                        ]),
                    ]),
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
                'guest_token',
                'status',
                'ready_at',
                'joined_at',
                'left_at',
            ])
            ->whereKey($guest->id)
            ->firstOrFail();
    }

    private function ensureDraftCanBeSent(DraftOrder $draftOrder, TableSessionGuest $guest): void
    {
        $tableSession = $draftOrder->tableSession;

        if ($tableSession === null
            || $guest->table_session_id !== $tableSession->id
            || $guest->status !== TableSessionGuestStatus::Active
            || in_array($tableSession->status, [TableSessionStatus::Closed, TableSessionStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'send_draft' => __('Только активный гость за этим столом может отправить заказ официанту.'),
            ]);
        }

        if ($draftOrder->status !== DraftOrderStatus::Draft) {
            throw ValidationException::withMessages([
                'send_draft' => __('Этот черновик уже отправлен официанту.'),
            ]);
        }

        if ((int) $draftOrder->items_count < 1) {
            throw ValidationException::withMessages([
                'send_draft' => __('Добавьте хотя бы одну позицию перед отправкой официанту.'),
            ]);
        }
    }
}
