<?php

declare(strict_types=1);

namespace App\Actions\TableSessions;

use App\Enums\OrderStatus;
use App\Enums\TableSessionStatus;
use App\Models\Branch;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CleanupInactiveTableSessionsAction
{
    public function __construct(
        private readonly BuildTableSessionInactivityStateAction $buildInactivityState,
        private readonly TransitionTableSessionStatusAction $transitionTableSessionStatus,
        private readonly FinalizeTableSessionTemporaryStateAction $finalizeTemporaryState,
    ) {}

    /**
     * @param  array<int, string>|null  $expectedCandidates
     * @return array{
     *     checked: int,
     *     pending_cancelled: int,
     *     active_warnings: int,
     *     skipped_unpaid_orders: int,
     *     skipped_existing_orders: int,
     *     skipped_existing_drafts: int,
     *     skipped_changed: int,
     *     skipped_recent: int,
     *     has_more: bool,
     *     cancelled_session_ids: list<int>
     * }
     */
    public function handle(?int $branchId = null, ?array $expectedCandidates = null, ?User $actor = null): array
    {
        $result = [
            'checked' => 0,
            'pending_cancelled' => 0,
            'active_warnings' => 0,
            'skipped_unpaid_orders' => 0,
            'skipped_existing_orders' => 0,
            'skipped_existing_drafts' => 0,
            'skipped_changed' => 0,
            'skipped_recent' => 0,
            'has_more' => false,
            'cancelled_session_ids' => [],
        ];

        $candidates = $this->candidateSessions($branchId, $expectedCandidates === null ? null : array_keys($expectedCandidates));
        $result['has_more'] = $candidates->count() > 1000;
        if ($expectedCandidates !== null) {
            $result['skipped_changed'] = count($expectedCandidates) - $candidates->count();
        }
        foreach ($candidates->take(1000) as $tableSession) {
            $result['checked']++;

            $state = $this->buildInactivityState->handle($tableSession);
            $status = $this->status($tableSession);

            if ($expectedCandidates !== null && $status !== TableSessionStatus::Pending) {
                $result['skipped_changed']++;

                continue;
            }

            if ($status === TableSessionStatus::Active && $state['should_warn']) {
                $result['active_warnings']++;

                continue;
            }

            if ($status !== TableSessionStatus::Pending || ! $state['should_expire_pending']) {
                $result[$status === TableSessionStatus::Pending ? 'skipped_recent' : 'skipped_changed']++;

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

            $outcome = $this->cancelPendingSession($tableSession, $expectedCandidates[$tableSession->id] ?? null, $actor);
            if ($outcome === 'cancelled') {
                $result['pending_cancelled']++;
                $result['cancelled_session_ids'][] = $tableSession->id;
            } else {
                $result[$outcome]++;
            }
        }

        return $result;
    }

    /**
     * @param  list<int>|null  $candidateIds
     * @return EloquentCollection<int, TableSession>
     */
    private function candidateSessions(?int $branchId, ?array $candidateIds = null): EloquentCollection
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
            ->withInactivityActivity()
            ->whereHas('branch')
            ->when($candidateIds === null, fn ($query) => $query
                ->whereIn('status', [TableSessionStatus::Pending->value, TableSessionStatus::Active->value])
                ->where('updated_at', '<=', now()->subMinute()))
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->when($candidateIds !== null, fn ($query) => $query->whereKey($candidateIds))
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit(1001)
            ->get();
    }

    private function cancelPendingSession(TableSession $tableSession, ?string $expectedFingerprint, ?User $actor): string
    {
        return DB::transaction(function () use ($tableSession, $expectedFingerprint, $actor): string {
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
                ->withInactivityActivity()
                ->with([
                    'branch' => fn ($query) => $query->select(['id', 'timezone']),
                    'branch.settings' => fn ($query) => $query->select(['id', 'branch_id', 'inactivity_warning_minutes', 'pending_session_expire_minutes']),
                ])
                ->whereKey($tableSession->id)
                ->where('branch_id', $tableSession->branch_id)
                ->first();

            if (! $tableSession instanceof TableSession || ! $tableSession->getRelation('branch') instanceof Branch
                || $this->status($tableSession) !== TableSessionStatus::Pending) {
                return 'skipped_changed';
            }

            $state = $this->buildInactivityState->handle($tableSession);
            if (! $state['should_expire_pending']) {
                return 'skipped_recent';
            }
            if ($expectedFingerprint !== null && ! hash_equals($expectedFingerprint, self::fingerprint($tableSession))) {
                return 'skipped_changed';
            }

            foreach (['has_unpaid_orders' => 'skipped_unpaid_orders', 'has_any_orders' => 'skipped_existing_orders', 'has_draft_orders' => 'skipped_existing_drafts'] as $attribute => $outcome) {
                if ((bool) $tableSession->getAttribute($attribute)) {
                    return $outcome;
                }
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
            if ($tableSession->status !== TableSessionStatus::Cancelled) {
                throw new RuntimeException('Pending session cancellation was rejected.');
            }
            $tableSession->forceFill([
                'ended_at' => now(),
                'metadata' => $metadata,
            ]);
            if (! $tableSession->save()) {
                throw new RuntimeException('Pending session cleanup could not be saved.');
            }
            $this->finalizeTemporaryState->handle($tableSession, $actor);
            if ($tableSession->isDirty(['guest_invite_token_hash', 'guest_invite_created_at', 'guest_invite_expires_at', 'guest_invite_created_by_guest_id'])) {
                throw new RuntimeException('Pending session temporary access could not be cleared.');
            }

            return 'cancelled';
        }, 3);
    }

    public static function fingerprint(TableSession $tableSession): string
    {
        return hash('sha256', json_encode($tableSession->only([
            'id', 'branch_id', 'service_point_id', 'status', 'started_at', 'created_at', 'updated_at',
            'guests_max_joined_at', 'guests_max_left_at', 'guests_max_ready_at',
            'join_requests_max_created_at', 'waiter_calls_max_requested_at', 'waiter_calls_max_handled_at',
            'has_unpaid_orders', 'has_any_orders', 'has_draft_orders',
        ]), JSON_THROW_ON_ERROR));
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
