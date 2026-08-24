<?php

declare(strict_types=1);

namespace App\Actions\TableSessions;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Enums\TableSessionGuestStatus;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LeaveTableSessionAction
{
    public function __construct(
        private readonly ExpirePendingJoinRequestsWithoutApproverAction $expirePendingJoinRequests,
        private readonly RecordAuditLogAction $recordAuditLog,
    ) {}

    public function handle(TableSessionGuest $guest, string $guestToken): TableSessionGuest
    {
        return DB::transaction(function () use ($guest, $guestToken): TableSessionGuest {
            $tableSession = TableSession::query()
                ->select(['id', 'branch_id', 'status'])
                ->with(['branch:id,organization_id'])
                ->whereKey($guest->table_session_id)
                ->lockForUpdate()
                ->firstOrFail();
            $guest = TableSessionGuest::query()
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
                ->where('table_session_id', $tableSession->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureCredentialMatches($guest, $guestToken);

            if (! $tableSession->status->allowsGuestViewing()) {
                throw ValidationException::withMessages([
                    'guest' => __('errors.types.session_closed.message'),
                ]);
            }

            if ($guest->status === TableSessionGuestStatus::Left) {
                return $guest;
            }

            if ($guest->status !== TableSessionGuestStatus::Active) {
                throw ValidationException::withMessages([
                    'guest' => __('errors.types.guest_rejected_removed.message'),
                ]);
            }

            $guest->forceFill([
                'status' => TableSessionGuestStatus::Left,
                'ready_at' => null,
                'left_at' => now(),
            ])->save();

            $this->expirePendingJoinRequests->handle($tableSession);
            $this->recordAuditLog->handle(
                action: AuditLogAction::TableSessionGuestLeft,
                entityType: 'table_session_guest',
                entityId: $guest->id,
                actorGuest: $guest,
                organizationId: $tableSession->branch->organization_id,
                branchId: $tableSession->branch_id,
                oldValues: ['status' => TableSessionGuestStatus::Active],
                newValues: ['status' => TableSessionGuestStatus::Left],
            );

            return $guest->refresh();
        }, attempts: 3);
    }

    private function ensureCredentialMatches(TableSessionGuest $guest, string $guestToken): void
    {
        if (strlen($guestToken) === 64 && hash_equals($guest->guest_token, $guestToken)) {
            return;
        }

        throw ValidationException::withMessages([
            'guest' => __('errors.types.guest_rejected_removed.message'),
        ]);
    }
}
