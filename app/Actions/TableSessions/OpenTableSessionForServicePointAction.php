<?php

declare(strict_types=1);

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
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class OpenTableSessionForServicePointAction
{
    public function __construct(
        public UpdateServicePointStatusAction $updateServicePointStatus,
        public TransitionTableSessionStatusAction $transitionTableSessionStatus,
    ) {}

    public function handle(ServicePoint $servicePoint, User $openedBy): TableSession
    {
        return DB::transaction(function () use ($servicePoint, $openedBy): TableSession {
            $servicePoint = ServicePoint::query()
                ->select(['id', 'branch_id', 'status', 'is_active', 'deleted_at'])
                ->with(['branch:id,organization_id,deleted_at'])
                ->whereKey($servicePoint->id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($openedBy)->authorize('openTable', $servicePoint);

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
                ->whereIn('status', TableSessionStatus::reusableOpenValues())
                ->orderBy('started_at')
                ->orderBy('id')
                ->first();

            if (! $activeTableSession instanceof TableSession) {
                $activeTableSession = $this->activeLinkedTableSession($servicePoint);
            }

            if ($activeTableSession instanceof TableSession) {
                return $activeTableSession->refresh();
            }

            $pendingTableSession = $servicePoint
                ->tableSessions()
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
                ->where('status', TableSessionStatus::Pending->value)
                ->orderBy('started_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($pendingTableSession instanceof TableSession) {
                $pendingTableSession->forceFill([
                    'opened_by_user_id' => $pendingTableSession->opened_by_user_id ?? $openedBy->id,
                ])->save();

                $pendingTableSession = $this->transitionTableSessionStatus->handle(
                    $pendingTableSession,
                    TableSessionStatus::Active,
                );

                $this->updateServicePointStatus->handle($servicePoint, ServicePointStatus::Occupied);

                return $pendingTableSession->refresh();
            }

            if (! $servicePoint->is_active || ! $servicePoint->status->allowsTableOpening()) {
                throw ValidationException::withMessages([
                    'service_point' => __('table_sessions.errors.not_available'),
                ]);
            }

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

            $this->updateServicePointStatus->handle($servicePoint, ServicePointStatus::Occupied);

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
                    ->whereIn('status', TableSessionStatus::reusableOpenValues()),
            ])
            ->active()
            ->where('service_point_id', $servicePoint->id)
            ->first();

        return $link?->tableSession;
    }
}
