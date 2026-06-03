<?php

namespace App\Actions\TableSessions;

use App\Enums\TableSessionJoinRequestStatus;
use App\Enums\TableSessionStatus;
use App\Models\TableSession;
use App\Models\TableSessionJoinRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateTableSessionJoinRequestAction
{
    public function handle(TableSession $tableSession, string $guestName): ?TableSessionJoinRequest
    {
        $normalizedGuestName = str($guestName)->squish()->toString();

        return DB::transaction(function () use ($tableSession, $normalizedGuestName): ?TableSessionJoinRequest {
            $tableSession = $this->reloadTableSession($tableSession);

            if (! in_array($tableSession->status, [TableSessionStatus::Pending, TableSessionStatus::Active], true)) {
                return null;
            }

            if (! $tableSession->activeGuests()->exists()) {
                return null;
            }

            return $tableSession->joinRequests()->create([
                'guest_name' => $normalizedGuestName,
                'guest_token' => Str::random(64),
                'status' => TableSessionJoinRequestStatus::Pending,
                'expires_at' => now()->addMinutes(30),
            ]);
        });
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
                'opened_by_user_id',
                'opened_by_guest_id',
                'status',
                'source',
                'started_at',
                'ended_at',
                'closed_by_user_id',
                'metadata',
                'created_at',
                'updated_at',
            ])
            ->whereKey($tableSession->id)
            ->firstOrFail();
    }
}
