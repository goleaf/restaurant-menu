<?php

declare(strict_types=1);

namespace App\Actions\TableSessions;

use App\Enums\TableSessionJoinRequestStatus;
use App\Models\TableSessionJoinRequest;

final class ExpireTableSessionJoinRequestAction
{
    public function handle(TableSessionJoinRequest $joinRequest): TableSessionJoinRequest
    {
        $joinRequest->forceFill(['status' => TableSessionJoinRequestStatus::Expired])->saveOrFail();

        return $joinRequest->refresh();
    }
}
