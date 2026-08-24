<?php

declare(strict_types=1);

namespace App\Actions\TableSessions;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Enums\TableSessionGuestStatus;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class RemoveTableSessionGuestAction
{
    public function __construct(
        private readonly ExpirePendingJoinRequestsWithoutApproverAction $expirePendingJoinRequests,
        private readonly RecordAuditLogAction $recordAuditLog,
    ) {}

    public function handle(TableSession $tableSession, TableSessionGuest $guest, User $removedBy): TableSessionGuest
    {
        return DB::transaction(function () use ($tableSession, $guest, $removedBy): TableSessionGuest {
            $tableSession = TableSession::query()
                ->select(['id', 'branch_id', 'status'])
                ->with(['branch:id,organization_id'])
                ->whereKey($tableSession->id)
                ->lockForUpdate()
                ->firstOrFail();
            $guest = TableSessionGuest::query()
                ->select([
                    'id',
                    'table_session_id',
                    'guest_name',
                    'status',
                    'ready_at',
                    'joined_at',
                    'left_at',
                ])
                ->whereKey($guest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureCanRemove($tableSession, $guest, $removedBy);

            if ($guest->status === TableSessionGuestStatus::Removed) {
                return $guest;
            }

            if ($guest->status !== TableSessionGuestStatus::Active) {
                throw ValidationException::withMessages([
                    'guest' => __('errors.types.guest_rejected_removed.message'),
                ]);
            }

            $guest->forceFill([
                'status' => TableSessionGuestStatus::Removed,
                'ready_at' => null,
                'left_at' => now(),
            ])->save();

            $this->expirePendingJoinRequests->handle($tableSession);
            $this->recordAuditLog->handle(
                action: AuditLogAction::TableSessionGuestRemoved,
                entityType: 'table_session_guest',
                entityId: $guest->id,
                actorUser: $removedBy,
                organizationId: $tableSession->branch->organization_id,
                branchId: $tableSession->branch_id,
                oldValues: ['status' => TableSessionGuestStatus::Active],
                newValues: [
                    'status' => TableSessionGuestStatus::Removed,
                    'removed_by_user_id' => $removedBy->id,
                ],
            );

            return $guest->refresh();
        }, attempts: 3);
    }

    private function ensureCanRemove(TableSession $tableSession, TableSessionGuest $guest, User $removedBy): void
    {
        if ($guest->table_session_id !== $tableSession->id) {
            throw ValidationException::withMessages([
                'guest' => __('payments.errors.guest_not_found'),
            ]);
        }

        if (! $tableSession->status->allowsGuestViewing()) {
            throw ValidationException::withMessages([
                'guest' => __('errors.types.session_closed.message'),
            ]);
        }

        if (! Gate::forUser($removedBy)->allows('manageGuests', $tableSession)) {
            throw ValidationException::withMessages([
                'guest' => __('ui.actions.tablesessions.closetablesessionaction.u_vas_net_prava_zakryvat_e'),
            ]);
        }
    }
}
