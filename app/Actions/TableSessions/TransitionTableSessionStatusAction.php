<?php

declare(strict_types=1);

namespace App\Actions\TableSessions;

use App\Enums\TableSessionStatus;
use App\Models\TableSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransitionTableSessionStatusAction
{
    public function handle(TableSession $tableSession, TableSessionStatus $targetStatus): TableSession
    {
        return DB::transaction(function () use ($tableSession, $targetStatus): TableSession {
            $currentTableSession = TableSession::query()
                ->select([
                    'id',
                    'service_point_id',
                    'active_service_point_id',
                    'pending_service_point_id',
                    'status',
                ])
                ->whereKey($tableSession->id)
                ->lockForUpdate()
                ->firstOrFail();
            $currentStatus = $currentTableSession->status;

            if ($currentStatus === $targetStatus) {
                return $tableSession->refresh();
            }

            if (! $currentStatus->canTransitionTo($targetStatus)) {
                throw ValidationException::withMessages([
                    'table_session' => __('table_sessions.errors.invalid_transition'),
                ]);
            }

            $currentTableSession->forceFill(['status' => $targetStatus])->save();

            return $tableSession->refresh();
        }, attempts: 3);
    }
}
