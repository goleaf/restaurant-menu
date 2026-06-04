<?php

namespace App\Actions\TableSessions;

use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionJoinRequestStatus;
use App\Enums\TableSessionStatus;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\TableSessionJoinRequest;
use App\Notifications\JoinRequestCreatedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class CreateTableSessionJoinRequestAction
{
    public function handle(TableSession $tableSession, string $guestName): ?TableSessionJoinRequest
    {
        $normalizedGuestName = str($guestName)->squish()->toString();

        $joinRequest = DB::transaction(function () use ($tableSession, $normalizedGuestName): ?TableSessionJoinRequest {
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

        if ($joinRequest instanceof TableSessionJoinRequest) {
            $this->notifyActiveGuests($joinRequest);
        }

        return $joinRequest;
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

    private function notifyActiveGuests(TableSessionJoinRequest $joinRequest): void
    {
        $recipients = TableSessionGuest::query()
            ->select([
                'id',
                'table_session_id',
                'guest_name',
                'guest_token',
                'status',
                'joined_at',
                'left_at',
            ])
            ->where('table_session_id', $joinRequest->table_session_id)
            ->where('status', TableSessionGuestStatus::Active->value)
            ->orderBy('guest_name')
            ->orderBy('id')
            ->limit(100)
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new JoinRequestCreatedNotification($joinRequest));
    }
}
