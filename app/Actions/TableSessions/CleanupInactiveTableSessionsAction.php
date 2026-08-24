<?php

declare(strict_types=1);

namespace App\Actions\TableSessions;

use App\Enums\OrderStatus;
use App\Enums\TableSessionStatus;
use App\Models\TableSession;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

class CleanupInactiveTableSessionsAction
{
    public function __construct(
        private readonly BuildTableSessionInactivityStateAction $buildInactivityState,
        private readonly TransitionTableSessionStatusAction $transitionTableSessionStatus,
        private readonly FinalizeTableSessionTemporaryStateAction $finalizeTemporaryState,
    ) {}

    /**
     * @return array{
     *     checked: int,
     *     pending_cancelled: int,
     *     active_warnings: int,
     *     skipped_unpaid_orders: int,
     *     skipped_existing_orders: int,
     *     skipped_existing_drafts: int
     * }
     */
    public function handle(?int $branchId = null): array
    {
        $result = [
            'checked' => 0,
            'pending_cancelled' => 0,
            'active_warnings' => 0,
            'skipped_unpaid_orders' => 0,
            'skipped_existing_orders' => 0,
            'skipped_existing_drafts' => 0,
        ];

        foreach ($this->candidateSessions($branchId) as $tableSession) {
            $result['checked']++;

            $state = $this->buildInactivityState->handle($tableSession);
            $status = $this->status($tableSession);

            if ($status === TableSessionStatus::Active && $state['should_warn']) {
                $result['active_warnings']++;

                continue;
            }

            if ($status !== TableSessionStatus::Pending || ! $state['should_expire_pending']) {
                continue;
            }

            if ((bool) $tableSession->getAttribute('has_unpaid_orders')) {
                $result['skipped_unpaid_orders']++;

                continue;
            }

            if ((bool) $tableSession->getAttribute('has_any_orders')) {
                $result['skipped_existing_orders']++;

                continue;
            }

            if ((bool) $tableSession->getAttribute('has_draft_orders')) {
                $result['skipped_existing_drafts']++;

                continue;
            }

            if ($this->cancelPendingSession($tableSession, $state)) {
                $result['pending_cancelled']++;
            }
        }

        return $result;
    }

    /**
     * @return EloquentCollection<int, TableSession>
     */
    private function candidateSessions(?int $branchId): EloquentCollection
    {
        return TableSession::query()
            ->select([
                'id',
                'branch_id',
                'service_point_id',
                'status',
                'source',
                'started_at',
                'ended_at',
                'closed_by_user_id',
                'metadata',
                'created_at',
                'updated_at',
            ])
            ->with([
                'branch' => fn ($query) => $query->select(['id', 'timezone']),
                'branch.settings' => fn ($query) => $query->select([
                    'id',
                    'branch_id',
                    'inactivity_warning_minutes',
                    'pending_session_expire_minutes',
                ]),
            ])
            ->withExists([
                'orders as has_any_orders',
                'orders as has_unpaid_orders' => fn ($query) => $query->whereNotIn('status', $this->settledOrderStatuses()),
                'draftOrders as has_draft_orders',
            ])
            ->whereIn('status', [
                TableSessionStatus::Pending->value,
                TableSessionStatus::Active->value,
            ])
            ->where('updated_at', '<=', now()->subMinute())
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit(1000)
            ->get();
    }

    /**
     * @param  array{
     *     should_warn: bool,
     *     should_expire_pending: bool,
     *     minutes_inactive: int,
     *     warning_minutes: int,
     *     pending_expire_minutes: int,
     *     last_activity_at: string|null
     * }  $state
     */
    private function cancelPendingSession(TableSession $tableSession, array $state): bool
    {
        return DB::transaction(function () use ($tableSession, $state): bool {
            $tableSession = TableSession::query()
                ->select([
                    'id',
                    'branch_id',
                    'service_point_id',
                    'status',
                    'source',
                    'started_at',
                    'ended_at',
                    'closed_by_user_id',
                    'metadata',
                    'created_at',
                    'updated_at',
                ])
                ->withExists([
                    'orders as has_any_orders',
                    'orders as has_unpaid_orders' => fn ($query) => $query->whereNotIn('status', $this->settledOrderStatuses()),
                    'draftOrders as has_draft_orders',
                ])
                ->whereKey($tableSession->id)
                ->firstOrFail();

            if ($this->status($tableSession) !== TableSessionStatus::Pending) {
                return false;
            }

            if ((bool) $tableSession->getAttribute('has_unpaid_orders')
                || (bool) $tableSession->getAttribute('has_any_orders')
                || (bool) $tableSession->getAttribute('has_draft_orders')) {
                return false;
            }

            $metadata = (array) ($tableSession->metadata ?? []);
            $metadata['cleanup'] = [
                'reason' => 'pending_session_expired',
                'ran_at' => now()->toISOString(),
                'minutes_inactive' => $state['minutes_inactive'],
                'pending_session_expire_minutes' => $state['pending_expire_minutes'],
                'last_activity_at' => $state['last_activity_at'],
            ];

            $this->transitionTableSessionStatus->handle($tableSession, TableSessionStatus::Cancelled);
            $tableSession->forceFill([
                'ended_at' => now(),
                'metadata' => $metadata,
            ])->save();
            $this->finalizeTemporaryState->handle($tableSession);

            return true;
        });
    }

    /**
     * @return list<string>
     */
    private function settledOrderStatuses(): array
    {
        return [
            OrderStatus::Paid->value,
            OrderStatus::Closed->value,
            OrderStatus::Cancelled->value,
        ];
    }

    private function status(TableSession $tableSession): TableSessionStatus
    {
        return $tableSession->status;
    }
}
