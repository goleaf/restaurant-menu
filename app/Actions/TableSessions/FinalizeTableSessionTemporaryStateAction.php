<?php

declare(strict_types=1);

namespace App\Actions\TableSessions;

use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionJoinRequestStatus;
use App\Enums\WaiterCallStatus;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\TableSessionJoinRequest;
use App\Models\User;
use App\Models\WaiterCall;

final class FinalizeTableSessionTemporaryStateAction
{
    public function handle(TableSession $tableSession, ?User $handledBy = null): void
    {
        $tableSession->forceFill([
            'guest_invite_token_hash' => null,
            'guest_invite_created_at' => null,
            'guest_invite_expires_at' => null,
            'guest_invite_created_by_guest_id' => null,
        ])->save();

        TableSessionGuest::query()
            ->where('table_session_id', $tableSession->id)
            ->where('status', TableSessionGuestStatus::Active->value)
            ->update([
                'status' => TableSessionGuestStatus::Left->value,
                'ready_at' => null,
                'left_at' => now(),
                'updated_at' => now(),
            ]);

        TableSessionJoinRequest::query()
            ->where('table_session_id', $tableSession->id)
            ->where('status', TableSessionJoinRequestStatus::Pending->value)
            ->update([
                'status' => TableSessionJoinRequestStatus::Expired->value,
                'expires_at' => now(),
                'updated_at' => now(),
            ]);

        WaiterCall::query()
            ->where('table_session_id', $tableSession->id)
            ->where('status', WaiterCallStatus::Pending->value)
            ->update([
                'active_service_point_id' => null,
                'status' => WaiterCallStatus::Handled->value,
                'handled_at' => now(),
                'handled_by_user_id' => $handledBy?->id,
                'updated_at' => now(),
            ]);
    }
}
