<?php

namespace App\Actions\TableSessions;

use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionJoinRequestStatus;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\TableSessionJoinRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RejectTableSessionJoinRequestAction
{
    public function handle(TableSessionJoinRequest $joinRequest, TableSessionGuest $rejectedByGuest): TableSessionJoinRequest
    {
        $this->expireIfNeeded($this->reloadJoinRequest($joinRequest));

        return DB::transaction(function () use ($joinRequest, $rejectedByGuest): TableSessionJoinRequest {
            $joinRequest = $this->reloadJoinRequest($joinRequest);
            $rejectedByGuest = $this->reloadGuest($rejectedByGuest);

            $this->ensurePendingAndFresh($joinRequest);
            $this->ensureActiveGuestCanModerate($joinRequest, $rejectedByGuest);

            $joinRequest
                ->forceFill([
                    'status' => TableSessionJoinRequestStatus::Rejected,
                    'rejected_by_guest_id' => $rejectedByGuest->id,
                ])
                ->save();

            return $joinRequest->refresh();
        });
    }

    private function reloadJoinRequest(TableSessionJoinRequest $joinRequest): TableSessionJoinRequest
    {
        return TableSessionJoinRequest::query()
            ->select([
                'id',
                'table_session_id',
                'guest_name',
                'guest_token',
                'status',
                'approved_by_guest_id',
                'rejected_by_guest_id',
                'approved_by_user_id',
                'rejected_by_user_id',
                'expires_at',
            ])
            ->with([
                'tableSession' => fn ($query) => $query->select([
                    'id',
                    'branch_id',
                    'service_point_id',
                    'status',
                ]),
            ])
            ->whereKey($joinRequest->id)
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

    private function ensurePendingAndFresh(TableSessionJoinRequest $joinRequest): void
    {
        if ($joinRequest->status !== TableSessionJoinRequestStatus::Pending) {
            throw ValidationException::withMessages([
                'join_request' => __('This join request is no longer pending.'),
            ]);
        }

        if ($joinRequest->expires_at !== null && $joinRequest->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'join_request' => __('This join request has expired.'),
            ]);
        }
    }

    private function expireIfNeeded(TableSessionJoinRequest $joinRequest): void
    {
        if ($joinRequest->status !== TableSessionJoinRequestStatus::Pending
            || $joinRequest->expires_at === null
            || ! $joinRequest->expires_at->isPast()) {
            return;
        }

        $joinRequest
            ->forceFill(['status' => TableSessionJoinRequestStatus::Expired])
            ->save();

        throw ValidationException::withMessages([
            'join_request' => __('This join request has expired.'),
        ]);
    }

    private function ensureActiveGuestCanModerate(
        TableSessionJoinRequest $joinRequest,
        TableSessionGuest $guest,
    ): void {
        $tableSession = $joinRequest->tableSession;

        if (! $tableSession instanceof TableSession
            || $guest->table_session_id !== $tableSession->id
            || $guest->status !== TableSessionGuestStatus::Active) {
            throw ValidationException::withMessages([
                'guest' => __('Only an active guest at this table can reject join requests.'),
            ]);
        }
    }
}
