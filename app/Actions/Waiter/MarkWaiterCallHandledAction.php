<?php

namespace App\Actions\Waiter;

use App\Actions\ServicePoints\UpdateServicePointStatusAction;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\WaiterCallStatus;
use App\Models\User;
use App\Models\WaiterCall;
use App\Notifications\WaiterCalledNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkWaiterCallHandledAction
{
    public function __construct(
        private readonly ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds,
        private readonly UpdateServicePointStatusAction $updateServicePointStatus,
    ) {}

    public function handle(WaiterCall $waiterCall, User $handledBy): WaiterCall
    {
        return DB::transaction(function () use ($waiterCall, $handledBy): WaiterCall {
            $waiterCall = $this->reloadWaiterCall($waiterCall);
            $this->ensureCanHandle($waiterCall, $handledBy);

            if ($waiterCall->status === WaiterCallStatus::Handled) {
                return $waiterCall;
            }

            $waiterCall
                ->forceFill([
                    'status' => WaiterCallStatus::Handled,
                    'handled_at' => now(),
                    'handled_by_user_id' => $handledBy->id,
                ])
                ->save();

            $this->restoreServicePointStatus($waiterCall);
            $this->markNotificationsRead($waiterCall);

            return $waiterCall->refresh();
        });
    }

    private function reloadWaiterCall(WaiterCall $waiterCall): WaiterCall
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
                'servicePoint' => fn ($query) => $query->select(['id', 'status']),
            ])
            ->whereKey($waiterCall->id)
            ->firstOrFail();
    }

    private function ensureCanHandle(WaiterCall $waiterCall, User $handledBy): void
    {
        $branchIds = $this->resolveAccessibleBranchIds->handle($handledBy, SystemPermission::ViewOrders);

        if (! $branchIds->contains((int) $waiterCall->branch_id)) {
            throw ValidationException::withMessages([
                'waiter_call' => __('ui.actions.waiter.markwaitercallhandledaction.u_vas_net_dostupa_k_vyzovam_o'),
            ]);
        }
    }

    private function restoreServicePointStatus(WaiterCall $waiterCall): void
    {
        $servicePoint = $waiterCall->servicePoint;

        if ($servicePoint->status !== ServicePointStatus::WaitingWaiter) {
            return;
        }

        $status = ServicePointStatus::tryFrom((string) data_get(
            $waiterCall->metadata,
            'previous_service_point_status',
            ServicePointStatus::Occupied->value,
        )) ?? ServicePointStatus::Occupied;

        $this->updateServicePointStatus->handle($servicePoint, $status);
    }

    private function markNotificationsRead(WaiterCall $waiterCall): void
    {
        DatabaseNotification::query()
            ->select(['id', 'type', 'data', 'read_at', 'created_at', 'updated_at'])
            ->whereIn('type', [WaiterCalledNotification::class, 'waiter_called'])
            ->whereNull('read_at')
            ->latest()
            ->limit(1000)
            ->get()
            ->filter(fn (DatabaseNotification $notification): bool => (int) data_get($notification->data, 'waiter_call_id') === $waiterCall->id)
            ->each(function (DatabaseNotification $notification): void {
                $notification->markAsRead();
            });
    }
}
