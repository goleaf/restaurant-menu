<?php

namespace App\Actions\TableSessions;

use App\Actions\ServicePoints\UpdateServicePointStatusAction;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionSource;
use App\Enums\TableSessionStatus;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionServicePoint;
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
                ->whereIn('status', [
                    TableSessionStatus::Active->value,
                    TableSessionStatus::PaymentRequested->value,
                ])
                ->orderBy('started_at')
                ->orderBy('id')
                ->first();

            if (! $activeTableSession instanceof TableSession) {
                $activeTableSession = $this->activeLinkedTableSession($servicePoint);
            }

            if (! $activeTableSession instanceof TableSession) {
                $activeTableSession = $servicePoint->tableSessions()->make([
                    'source' => TableSessionSource::WaiterOpened,
                    'started_at' => now(),
                    'metadata' => [],
                ]);
                $activeTableSession->forceFill([
                    'branch_id' => $servicePoint->branch_id,
                    'opened_by_user_id' => $openedBy->id,
                    'status' => TableSessionStatus::Active,
                ])->save();
            }

            $this->updateServicePointStatus->handle(
                $servicePoint,
                $activeTableSession->status === TableSessionStatus::PaymentRequested
                    ? ServicePointStatus::PaymentRequested
                    : ServicePointStatus::Occupied,
            );

            return $activeTableSession->refresh();
        });
    }

    private function activeLinkedTableSession(ServicePoint $servicePoint): ?TableSession
    {
        $link = TableSessionServicePoint::query()
            ->select(['id', 'table_session_id', 'service_point_id', 'unlinked_at'])
            ->with([
                'tableSession' => fn ($query) => $query
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
                    ->whereIn('status', [
                        TableSessionStatus::Active->value,
                        TableSessionStatus::PaymentRequested->value,
                    ]),
            ])
            ->active()
            ->where('service_point_id', $servicePoint->id)
            ->first();

        return $link?->tableSession;
    }
}
