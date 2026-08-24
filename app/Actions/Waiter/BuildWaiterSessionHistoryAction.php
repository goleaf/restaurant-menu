<?php

declare(strict_types=1);

namespace App\Actions\Waiter;

use App\Enums\SystemPermission;
use App\Enums\TableSessionStatus;
use App\Models\TableSession;
use App\Models\User;
use App\Support\LocalizedDateFormatter;

final class BuildWaiterSessionHistoryAction
{
    public function __construct(
        private readonly ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds,
    ) {}

    /**
     * @return array{has_access: bool, sessions: list<array<string, mixed>>}
     */
    public function handle(User $user): array
    {
        $branchIds = $this->resolveAccessibleBranchIds->handle($user, SystemPermission::ViewOrders);

        if ($branchIds->isEmpty()) {
            return [
                'has_access' => false,
                'sessions' => [],
            ];
        }

        $sessions = TableSession::query()
            ->select([
                'id',
                'branch_id',
                'service_point_id',
                'status',
                'started_at',
                'ended_at',
            ])
            ->withCount(['guests', 'orders'])
            ->with([
                'branch' => fn ($query) => $query->select(['id', 'name']),
                'servicePoint' => fn ($query) => $query->select([
                    'id',
                    'branch_id',
                    'name',
                    'display_number',
                ]),
            ])
            ->whereIn('branch_id', $branchIds)
            ->whereIn('status', [
                TableSessionStatus::Closed->value,
                TableSessionStatus::Cancelled->value,
            ])
            ->orderByDesc('ended_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (TableSession $tableSession): array => [
                'id' => $tableSession->id,
                'branch_name' => $tableSession->branch->name,
                'service_point_name' => $tableSession->servicePoint->name,
                'display_number' => $tableSession->servicePoint->display_number,
                'status_key' => 'ui.waiter.session_history.status.'.$tableSession->status->value,
                'started_at' => LocalizedDateFormatter::dateTime($tableSession->started_at),
                'ended_at' => LocalizedDateFormatter::dateTime($tableSession->ended_at),
                'guest_count' => (int) $tableSession->getAttribute('guests_count'),
                'order_count' => (int) $tableSession->getAttribute('orders_count'),
                'detail_url' => route('restaurant.waiter.tables.show', $tableSession),
            ])
            ->values()
            ->all();

        return [
            'has_access' => true,
            'sessions' => $sessions,
        ];
    }
}
