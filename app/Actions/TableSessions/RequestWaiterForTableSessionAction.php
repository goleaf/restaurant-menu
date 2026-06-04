<?php

namespace App\Actions\TableSessions;

use App\Actions\ServicePoints\UpdateServicePointStatusAction;
use App\Actions\Waiter\ResolveWaiterNotificationRecipientsAction;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Enums\WaiterCallStatus;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\WaiterCall;
use App\Notifications\WaiterCalledNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class RequestWaiterForTableSessionAction
{
    public function __construct(
        private readonly UpdateServicePointStatusAction $updateServicePointStatus,
        private readonly ResolveWaiterNotificationRecipientsAction $resolveRecipients,
    ) {}

    public function handle(TableSession $tableSession, TableSessionGuest $guest): WaiterCall
    {
        [$waiterCall, $shouldNotify] = DB::transaction(function () use ($tableSession, $guest): array {
            $tableSession = $this->reloadTableSession($tableSession);
            $guest = $this->reloadGuest($guest);

            $this->ensureGuestCanRequestWaiter($tableSession, $guest);

            $existingWaiterCall = $this->pendingWaiterCallFor($tableSession);

            if ($existingWaiterCall instanceof WaiterCall) {
                $this->markServicePointWaiting($tableSession);

                return [$existingWaiterCall->refresh(), false];
            }

            $servicePoint = $tableSession->servicePoint;
            $previousStatus = $servicePoint?->status instanceof ServicePointStatus
                ? $servicePoint->status->value
                : ServicePointStatus::Occupied->value;

            $waiterCall = WaiterCall::query()->create([
                'branch_id' => $tableSession->branch_id,
                'service_point_id' => $tableSession->service_point_id,
                'table_session_id' => $tableSession->id,
                'requested_by_guest_id' => $guest->id,
                'status' => WaiterCallStatus::Pending,
                'requested_at' => now(),
                'metadata' => [
                    'previous_service_point_status' => $previousStatus,
                ],
            ]);

            $this->markServicePointWaiting($tableSession);

            return [$waiterCall->refresh(), true];
        });

        if ($shouldNotify) {
            $waiterCall = $this->reloadWaiterCallForNotification($waiterCall);
            $recipients = $this->resolveRecipients->handle($waiterCall->branch);

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new WaiterCalledNotification($waiterCall));
            }
        }

        return $waiterCall->refresh();
    }

    private function reloadTableSession(TableSession $tableSession): TableSession
    {
        return TableSession::query()
            ->select([
                'id',
                'branch_id',
                'service_point_id',
                'status',
                'ended_at',
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

    private function ensureGuestCanRequestWaiter(TableSession $tableSession, TableSessionGuest $guest): void
    {
        $servicePoint = $tableSession->servicePoint;

        if (! $servicePoint instanceof ServicePoint || ! $servicePoint->is_active) {
            throw ValidationException::withMessages([
                'waiter_call' => __('Это место сейчас недоступно. Пожалуйста, обратитесь к персоналу.'),
            ]);
        }

        if ($guest->table_session_id !== $tableSession->id
            || $guest->status !== TableSessionGuestStatus::Active
            || in_array($tableSession->status, [TableSessionStatus::Closed, TableSessionStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'waiter_call' => __('Только активный гость за этим столом может позвать официанта.'),
            ]);
        }
    }

    private function pendingWaiterCallFor(TableSession $tableSession): ?WaiterCall
    {
        return WaiterCall::query()
            ->select([
                'id',
                'branch_id',
                'service_point_id',
                'active_service_point_id',
                'table_session_id',
                'requested_by_guest_id',
                'status',
                'requested_at',
                'handled_at',
                'handled_by_user_id',
                'metadata',
            ])
            ->where('service_point_id', $tableSession->service_point_id)
            ->where('status', WaiterCallStatus::Pending->value)
            ->orderByDesc('requested_at')
            ->orderByDesc('id')
            ->first();
    }

    private function markServicePointWaiting(TableSession $tableSession): void
    {
        if ($tableSession->servicePoint instanceof ServicePoint) {
            $this->updateServicePointStatus->handle($tableSession->servicePoint, ServicePointStatus::WaitingWaiter);
        }
    }

    private function reloadWaiterCallForNotification(WaiterCall $waiterCall): WaiterCall
    {
        return WaiterCall::query()
            ->select([
                'id',
                'branch_id',
                'service_point_id',
                'active_service_point_id',
                'table_session_id',
                'requested_by_guest_id',
                'status',
                'requested_at',
                'handled_at',
                'handled_by_user_id',
                'metadata',
            ])
            ->with([
                'branch' => fn ($query) => $query->select(['id', 'organization_id', 'name']),
                'servicePoint' => fn ($query) => $query
                    ->select(['id', 'branch_id', 'area_node_id', 'name', 'display_number'])
                    ->with(['areaNode' => fn ($areaQuery) => $areaQuery->select(['id', 'branch_id', 'name'])]),
                'requestedByGuest' => fn ($query) => $query->select(['id', 'guest_name']),
            ])
            ->whereKey($waiterCall->id)
            ->firstOrFail();
    }
}
