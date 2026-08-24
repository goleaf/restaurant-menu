<?php

declare(strict_types=1);

namespace App\Actions\TableSessions;

use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionJoinRequestStatus;
use App\Models\TableSessionGuest;
use App\Models\TableSessionJoinRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveTableSessionJoinRequestAction
{
    public function handle(TableSessionJoinRequest $joinRequest, TableSessionGuest $approvedByGuest): TableSessionGuest
    {
        $this->expireIfNeeded($this->reloadJoinRequest($joinRequest));

        return DB::transaction(function () use ($joinRequest, $approvedByGuest): TableSessionGuest {
            $joinRequest = $this->reloadJoinRequest($joinRequest);
            $approvedByGuest = $this->reloadGuest($approvedByGuest);

            $this->ensureActiveGuestCanModerate($joinRequest, $approvedByGuest);

            if ($joinRequest->status === TableSessionJoinRequestStatus::Approved) {
                return $this->approvedGuest($joinRequest);
            }

            $this->ensurePendingAndFresh($joinRequest);

            $tableSession = $joinRequest->tableSession;

            $guest = $tableSession->guests()->make([
                'guest_name' => $joinRequest->guest_name,
                'locale' => $joinRequest->locale,
                'joined_at' => now(),
                'metadata' => [],
            ]);
            $guest->forceFill([
                'guest_token' => $joinRequest->guest_token,
                'status' => TableSessionGuestStatus::Active,
            ])->save();

            $joinRequest
                ->forceFill([
                    'status' => TableSessionJoinRequestStatus::Approved,
                    'approved_by_guest_id' => $approvedByGuest->id,
                ])
                ->save();

            return $guest->refresh();
        }, 5);
    }

    private function reloadJoinRequest(TableSessionJoinRequest $joinRequest): TableSessionJoinRequest
    {
        return TableSessionJoinRequest::query()
            ->select([
                'id',
                'table_session_id',
                'guest_name',
                'guest_token',
                'locale',
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
            ->lockForUpdate()
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
                'locale',
                'status',
                'joined_at',
                'left_at',
            ])
            ->whereKey($guest->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensurePendingAndFresh(TableSessionJoinRequest $joinRequest): void
    {
        if ($joinRequest->status !== TableSessionJoinRequestStatus::Pending) {
            throw ValidationException::withMessages([
                'join_request' => __('ui.actions.tablesessions.approvetablesessionjoinrequestaction.this_join_req'),
            ]);
        }

        if ($joinRequest->expires_at !== null && $joinRequest->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'join_request' => __('guest.table.join_request_expired'),
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
            'join_request' => __('guest.table.join_request_expired'),
        ]);
    }

    private function ensureActiveGuestCanModerate(
        TableSessionJoinRequest $joinRequest,
        TableSessionGuest $guest,
    ): void {
        $tableSession = $joinRequest->tableSession;

        if ($guest->table_session_id !== $tableSession->id
            || ! $tableSession->status->allowsGuestParticipation()
            || $guest->status !== TableSessionGuestStatus::Active) {
            throw ValidationException::withMessages([
                'guest' => __('ui.actions.tablesessions.approvetablesessionjoinrequestaction.only_an_activ'),
            ]);
        }
    }

    private function approvedGuest(TableSessionJoinRequest $joinRequest): TableSessionGuest
    {
        $guest = TableSessionGuest::query()
            ->select([
                'id',
                'table_session_id',
                'guest_name',
                'guest_token',
                'locale',
                'status',
                'joined_at',
                'left_at',
            ])
            ->where('table_session_id', $joinRequest->table_session_id)
            ->where('guest_token', $joinRequest->guest_token)
            ->first();

        if ($guest instanceof TableSessionGuest) {
            return $guest;
        }

        throw ValidationException::withMessages([
            'join_request' => __('ui.actions.tablesessions.approvetablesessionjoinrequestaction.this_join_req'),
        ]);
    }
}
