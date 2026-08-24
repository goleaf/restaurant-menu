<?php

declare(strict_types=1);

namespace App\Actions\TableSessions;

use App\Enums\TableSessionJoinRequestStatus;
use App\Models\TableSessionJoinRequest;
use Illuminate\Support\Facades\DB;

final class ExpireTableSessionJoinRequestAction
{
    public function handle(TableSessionJoinRequest $joinRequest): TableSessionJoinRequest
    {
        return DB::transaction(function () use ($joinRequest): TableSessionJoinRequest {
            $joinRequest = TableSessionJoinRequest::query()
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
                ->whereKey($joinRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($joinRequest->status === TableSessionJoinRequestStatus::Pending
                && $joinRequest->expires_at !== null
                && $joinRequest->expires_at->isPast()) {
                $joinRequest->forceFill(['status' => TableSessionJoinRequestStatus::Expired])->saveOrFail();
            }

            return $joinRequest->refresh();
        }, 5);
    }
}
