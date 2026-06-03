<?php

namespace App\Actions\TableSessions;

use App\Actions\ServicePoints\UpdateServicePointStatusAction;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionSource;
use App\Enums\TableSessionStatus;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OpenTableSessionForServicePointAction
{
    public function __construct(
        public UpdateServicePointStatusAction $updateServicePointStatus,
    ) {}

    public function handle(ServicePoint $servicePoint, User $openedBy): TableSession
    {
        return DB::transaction(function () use ($servicePoint, $openedBy): TableSession {
            $activeTableSession = $servicePoint
                ->tableSessions()
                ->select([
                    'id',
                    'branch_id',
                    'service_point_id',
                    'active_service_point_id',
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
                ->where('status', TableSessionStatus::Active->value)
                ->orderBy('started_at')
                ->orderBy('id')
                ->first();

            if (! $activeTableSession instanceof TableSession) {
                $activeTableSession = $servicePoint->tableSessions()->create([
                    'branch_id' => $servicePoint->branch_id,
                    'opened_by_user_id' => $openedBy->id,
                    'status' => TableSessionStatus::Active,
                    'source' => TableSessionSource::WaiterOpened,
                    'started_at' => now(),
                    'metadata' => [],
                ]);
            }

            $this->updateServicePointStatus->handle($servicePoint, ServicePointStatus::Occupied);

            return $activeTableSession->refresh();
        });
    }
}
