<?php

declare(strict_types=1);

namespace App\Actions\TableSessions;

use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionJoinRequestStatus;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\TableSessionJoinRequest;

final class ExpirePendingJoinRequestsWithoutApproverAction
{
    public function handle(TableSession $tableSession): int
    {
        $hasActiveGuest = TableSessionGuest::query()
            ->where('table_session_id', $tableSession->id)
            ->where('status', TableSessionGuestStatus::Active->value)
            ->exists();

        if ($hasActiveGuest) {
            return 0;
        }

        return TableSessionJoinRequest::query()
            ->where('table_session_id', $tableSession->id)
            ->where('status', TableSessionJoinRequestStatus::Pending->value)
            ->update([
                'status' => TableSessionJoinRequestStatus::Expired->value,
                'expires_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
