<?php

declare(strict_types=1);

namespace App\Actions\DraftOrders;

use App\Actions\Orders\CreateOrderStatusLogAction;
use App\Actions\ServicePoints\UpdateServicePointStatusAction;
use App\Actions\TableSessions\TransitionTableSessionStatusAction;
use App\Actions\Waiter\ResolveWaiterNotificationRecipientsAction;
use App\Enums\DraftOrderStatus;
use App\Enums\OrderStatusLogEvent;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Models\DraftOrder;
use App\Models\ServicePoint;
use App\Models\TableSessionGuest;
use App\Notifications\DraftOrderSentToWaiterNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class SendDraftOrderToWaiterAction
{
    public function __construct(
        private readonly UpdateServicePointStatusAction $updateServicePointStatus,
        private readonly CreateOrderStatusLogAction $createOrderStatusLog,
        private readonly ResolveWaiterNotificationRecipientsAction $resolveRecipients,
        private readonly EnsureDraftMenuItemAvailableAction $ensureMenuItemAvailable,
        private readonly TransitionTableSessionStatusAction $transitionTableSessionStatus,
    ) {}

    public function handle(DraftOrder $draftOrder, TableSessionGuest $sentByGuest): DraftOrder
    {
        $shouldNotifyWaiters = false;

        $draftOrder = DB::transaction(function () use ($draftOrder, $sentByGuest, &$shouldNotifyWaiters): DraftOrder {
            $draftOrder = $this->reloadDraftOrder($draftOrder);
            $sentByGuest = $this->reloadGuest($sentByGuest);

            $this->ensureGuestBelongsToDraft($draftOrder, $sentByGuest);

            if ($draftOrder->status === DraftOrderStatus::SentToWaiter) {
                return $draftOrder;
            }

            $this->ensureDraftCanBeSent($draftOrder);
            $previousStatus = $draftOrder->status;

            $draftOrder
                ->forceFill([
                    'status' => DraftOrderStatus::SentToWaiter,
                    'sent_to_waiter_at' => now(),
                    'sent_by_guest_id' => $sentByGuest->id,
                ])
                ->save();

            $this->transitionTableSessionStatus->handle(
                $draftOrder->tableSession,
                TableSessionStatus::WaitingWaiterConfirmation,
            );

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
            $shouldNotifyWaiters = true;

            return $draftOrder->refresh();
        }, attempts: 3);

        if ($shouldNotifyWaiters) {
            $this->notifyWaiterRecipients($draftOrder);
        }

        return $draftOrder->refresh();
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
                        'branch_id',
                        'service_point_id',
                        'status',
                        'ended_at',
                    ])
                    ->with([
                        'servicePoint' => fn ($servicePointQuery) => $servicePointQuery->select([
                            'id',
                            'status',
                            'is_active',
                        ]),
                    ]),
            ])
            ->whereKey($draftOrder->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function reloadDraftOrderForNotification(DraftOrder $draftOrder): DraftOrder
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
                'sentByGuest' => fn ($query) => $query->select(['id', 'guest_name']),
                'tableSession' => fn ($query) => $query
                    ->select([
                        'id',
                        'branch_id',
                        'service_point_id',
                    ])
                    ->with([
                        'branch' => fn ($branchQuery) => $branchQuery->select(['id', 'organization_id', 'name']),
                        'servicePoint' => fn ($servicePointQuery) => $servicePointQuery
                            ->select(['id', 'branch_id', 'area_node_id', 'name', 'display_number'])
                            ->with(['areaNode' => fn ($areaQuery) => $areaQuery->select(['id', 'branch_id', 'name'])]),
                    ]),
            ])
            ->whereKey($draftOrder->id)
            ->firstOrFail();
    }

    private function notifyWaiterRecipients(DraftOrder $draftOrder): void
    {
        $draftOrder = $this->reloadDraftOrderForNotification($draftOrder);
        $tableSession = $draftOrder->tableSession;

        $recipients = $this->resolveRecipients->handle($tableSession->branch);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new DraftOrderSentToWaiterNotification($draftOrder));
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
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensureGuestBelongsToDraft(DraftOrder $draftOrder, TableSessionGuest $guest): void
    {
        $tableSession = $draftOrder->tableSession;
        $servicePoint = $tableSession?->servicePoint;

        if ($tableSession === null
            || ! $servicePoint instanceof ServicePoint
            || ! $servicePoint->is_active
            || $guest->table_session_id !== $tableSession->id
            || $guest->status !== TableSessionGuestStatus::Active) {
            throw ValidationException::withMessages([
                'send_draft' => __('ui.actions.draftorders.senddraftordertowaiteraction.tolko_aktivnyi_gost_za'),
            ]);
        }
    }

    private function ensureDraftCanBeSent(DraftOrder $draftOrder): void
    {
        $tableSession = $draftOrder->tableSession;
        $servicePoint = $tableSession?->servicePoint;

        if ($tableSession === null
            || ! $servicePoint instanceof ServicePoint
            || ! $tableSession->status->allowsGuestParticipation()) {
            throw ValidationException::withMessages([
                'send_draft' => __('ui.actions.draftorders.senddraftordertowaiteraction.tolko_aktivnyi_gost_za'),
            ]);
        }

        if (! $draftOrder->status->isGuestEditable()
            || ! $draftOrder->status->canTransitionTo(DraftOrderStatus::SentToWaiter)) {
            throw ValidationException::withMessages([
                'send_draft' => __('ui.actions.draftorders.addguestdraftorderitemaction.etot_cernovik_uze_otpra'),
            ]);
        }

        if ((int) $draftOrder->items_count < 1) {
            throw ValidationException::withMessages([
                'send_draft' => __('ui.actions.draftorders.senddraftordertowaiteraction.dobavte_xotia_by_odnu_p'),
            ]);
        }

        $this->ensureMenuItemAvailable->draft($draftOrder, CarbonImmutable::now(), 'send_draft');
    }
}
