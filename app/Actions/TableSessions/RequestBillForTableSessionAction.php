<?php

namespace App\Actions\TableSessions;

use App\Actions\Orders\TransitionTableOrdersAction;
use App\Actions\ServicePoints\UpdateServicePointStatusAction;
use App\Actions\Waiter\ResolveWaiterNotificationRecipientsAction;
use App\Enums\OrderStatus;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Notifications\BillRequestedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class RequestBillForTableSessionAction
{
    public function __construct(
        private readonly UpdateServicePointStatusAction $updateServicePointStatus,
        private readonly ResolveWaiterNotificationRecipientsAction $resolveRecipients,
        private readonly TransitionTableOrdersAction $transitionTableOrders,
        private readonly CanRequestBillForTableSessionAction $canRequestBill,
        private readonly TransitionTableSessionStatusAction $transitionTableSessionStatus,
    ) {}

    public function handle(TableSession $tableSession, TableSessionGuest $guest): TableSession
    {
        [$tableSession, $shouldNotify] = DB::transaction(function () use ($tableSession, $guest): array {
            $tableSession = $this->reloadTableSession($tableSession);
            $guest = $this->reloadGuest($guest);

            $this->ensureGuestCanRequestBill($tableSession, $guest);

            if (! $this->canRequestBill->handle($tableSession)) {
                throw ValidationException::withMessages([
                    'bill_request' => __('orders.errors.table_has_unfinished_work'),
                ]);
            }

            if ($tableSession->status === TableSessionStatus::PaymentRequested) {
                $this->transitionTableOrders->handle(
                    tableSession: $tableSession,
                    targetStatus: OrderStatus::PaymentRequested,
                    actorGuest: $guest,
                    errorField: 'bill_request',
                );
                $this->markServicePointPaymentRequested($tableSession);

                return [$tableSession->refresh(), false];
            }

            $metadata = (array) ($tableSession->metadata ?? []);
            $metadata['bill_requested_at'] = now()->toISOString();
            $metadata['bill_requested_by_guest_id'] = $guest->id;

            $this->transitionTableSessionStatus->handle($tableSession, TableSessionStatus::PaymentRequested);
            $tableSession->forceFill(['metadata' => $metadata])->save();

            $this->transitionTableOrders->handle(
                tableSession: $tableSession,
                targetStatus: OrderStatus::PaymentRequested,
                actorGuest: $guest,
                errorField: 'bill_request',
            );

            $this->markServicePointPaymentRequested($tableSession);

            return [$tableSession->refresh(), true];
        });

        if ($shouldNotify) {
            $tableSession = $this->reloadTableSessionForNotification($tableSession);
            $guest = $this->reloadGuest($guest);
            $recipients = $this->resolveRecipients->handle($tableSession->branch);

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new BillRequestedNotification($tableSession, $guest));
            }
        }

        return $tableSession->refresh();
    }

    private function reloadTableSession(TableSession $tableSession): TableSession
    {
        return TableSession::query()
            ->select([
                'id',
                'branch_id',
                'service_point_id',
                'active_service_point_id',
                'pending_service_point_id',
                'status',
                'ended_at',
                'metadata',
            ])
            ->with([
                'branch' => fn ($query) => $query->select(['id', 'organization_id', 'name']),
                'servicePoint' => fn ($query) => $query->select([
                    'id',
                    'branch_id',
                    'area_node_id',
                    'name',
                    'display_number',
                    'status',
                    'is_active',
                ]),
            ])
            ->whereKey($tableSession->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function reloadTableSessionForNotification(TableSession $tableSession): TableSession
    {
        return TableSession::query()
            ->select([
                'id',
                'branch_id',
                'service_point_id',
                'status',
                'started_at',
                'metadata',
            ])
            ->with([
                'branch' => fn ($query) => $query->select(['id', 'organization_id', 'name']),
                'servicePoint' => fn ($query) => $query
                    ->select(['id', 'branch_id', 'area_node_id', 'name', 'display_number'])
                    ->with(['areaNode' => fn ($areaQuery) => $areaQuery->select(['id', 'branch_id', 'name'])]),
            ])
            ->whereKey($tableSession->id)
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
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensureGuestCanRequestBill(TableSession $tableSession, TableSessionGuest $guest): void
    {
        $servicePoint = $tableSession->servicePoint;

        if (! $servicePoint->is_active) {
            throw ValidationException::withMessages([
                'bill_request' => __('ui.actions.draftorders.addguestdraftorderitemaction.eto_mesto_seicas_nedost'),
            ]);
        }

        if ($guest->table_session_id !== $tableSession->id
            || $guest->status !== TableSessionGuestStatus::Active
            || ($tableSession->status !== TableSessionStatus::PaymentRequested
                && $tableSession->status->locksOrderChanges())) {
            throw ValidationException::withMessages([
                'bill_request' => __('ui.actions.tablesessions.requestbillfortablesessionaction.tolko_aktivnyi_go'),
            ]);
        }
    }

    private function markServicePointPaymentRequested(TableSession $tableSession): void
    {
        $this->updateServicePointStatus->handle($tableSession->servicePoint, ServicePointStatus::PaymentRequested);
    }
}
