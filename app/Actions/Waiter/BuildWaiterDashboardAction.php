<?php

declare(strict_types=1);

namespace App\Actions\Waiter;

use App\Actions\TableSessions\BuildTableSessionInactivityStateAction;
use App\Enums\DraftOrderStatus;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\TableSessionStatus;
use App\Enums\WaiterCallStatus;
use App\Models\AreaNodeWaiter;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\KitchenTicketItem;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\User;
use App\Models\WaiterCall;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class BuildWaiterDashboardAction
{
    public function __construct(
        private readonly ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds,
        private readonly BuildTableSessionInactivityStateAction $buildInactivityState,
    ) {}

    /**
     * @return array{
     *     has_access: bool,
     *     branches: list<array<string, mixed>>,
     *     service_point_count: int,
     *     active_session_count: int,
     *     new_draft_count: int,
     *     waiter_call_count: int,
     *     bill_request_count: int,
     *     ready_item_count: int
     * }
     */
    public function handle(User $user, string $zoneScope = 'mine'): array
    {
        $zoneScope = $zoneScope === 'all' ? 'all' : 'mine';
        $branchIds = $this->accessibleBranchIds($user);
        $openTableBranchIds = $this->resolveAccessibleBranchIds
            ->handle($user, SystemPermission::ViewOrders)
            ->merge($this->resolveAccessibleBranchIds->handle($user, SystemPermission::ConfirmOrders))
            ->unique()
            ->values();
        $closeTableBranchIds = $this->resolveAccessibleBranchIds
            ->handle($user, SystemPermission::CloseTableSessions);

        if ($branchIds->isEmpty()) {
            return [
                'has_access' => false,
                'branches' => [],
                'service_point_count' => 0,
                'active_session_count' => 0,
                'new_draft_count' => 0,
                'waiter_call_count' => 0,
                'bill_request_count' => 0,
                'ready_item_count' => 0,
            ];
        }

        $branches = Branch::query()
            ->select([
                'id',
                'organization_id',
                'brand_id',
                'name',
                'city',
                'timezone',
                'currency',
                'is_active',
                'is_temporarily_closed',
                'temporary_closed_reason',
                'temporary_closed_until',
            ])
            ->with([
                'organization' => fn ($query) => $query->select(['id', 'name']),
                'brand' => fn ($query) => $query->select(['id', 'organization_id', 'name']),
                'settings' => fn ($query) => $query->select([
                    'id',
                    'branch_id',
                    'inactivity_warning_minutes',
                    'pending_session_expire_minutes',
                ]),
            ])
            ->whereIn('id', $branchIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $branchIds = $branches->pluck('id');
        $assignedAreaNodeIdsByBranch = $this->assignedAreaNodeIdsByBranch($user, $branchIds);
        $hasAssignedAreaNodes = $assignedAreaNodeIdsByBranch->isNotEmpty();

        $servicePoints = ServicePoint::query()
            ->select(['id', 'branch_id', 'area_node_id', 'type', 'name', 'display_number', 'capacity', 'icon', 'status', 'is_active'])
            ->with(['areaNode' => fn ($query) => $query->select(['id', 'branch_id', 'name'])])
            ->whereIn('branch_id', $branchIds)
            ->when($zoneScope === 'mine' && $hasAssignedAreaNodes, function ($query) use ($branchIds, $assignedAreaNodeIdsByBranch): void {
                $this->applyAssignedAreaNodeFilter($query, $branchIds, $assignedAreaNodeIdsByBranch);
            })
            ->orderBy('branch_id')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $servicePointIds = $servicePoints->pluck('id')->values();
        $sessions = $this->openTableSessions($branchIds, $servicePointIds);

        $draftOrders = $this->sentDraftOrders($sessions->pluck('id'));
        $waiterCalls = $this->pendingWaiterCalls($branchIds, $servicePointIds);
        $readyItems = $this->readyTicketItems($branchIds, $servicePointIds);
        $draftsBySessionId = $draftOrders->keyBy('table_session_id');
        $sessionsByServicePointId = collect($sessions->all())->groupBy('service_point_id');
        $waiterCallsByServicePointId = collect($waiterCalls->all())->groupBy('service_point_id');
        $readyItemsByServicePointId = collect($readyItems->all())->groupBy(
            fn (KitchenTicketItem $item): int => (int) $item->kitchenTicket->service_point_id,
        );
        $servicePointsByBranchId = collect($servicePoints->all())->groupBy('branch_id');
        $sessionsByBranchId = collect($sessions->all())->groupBy('branch_id');
        $draftsByBranchId = $this->draftsByBranchId($draftOrders, $sessions);
        $waiterCallsByBranchId = collect($waiterCalls->all())->groupBy('branch_id');
        $readyItemsByBranchId = collect($readyItems->all())->groupBy(
            fn (KitchenTicketItem $item): int => (int) $item->kitchenTicket->branch_id,
        );
        $billRequestsByBranchId = $this->billRequestsByBranchId($sessions);
        $billRequestCount = $billRequestsByBranchId->sum(fn (Collection $branchBillRequests): int => count($branchBillRequests));

        return [
            'has_access' => true,
            'branches' => $branches
                ->map(fn (Branch $branch): array => $this->branchPayload(
                    branch: $branch,
                    servicePoints: $servicePointsByBranchId->get($branch->id, new Collection),
                    sessions: $sessionsByBranchId->get($branch->id, new Collection),
                    drafts: $draftsByBranchId->get($branch->id, new Collection),
                    waiterCalls: $waiterCallsByBranchId->get($branch->id, new Collection),
                    billRequests: $billRequestsByBranchId->get($branch->id, new Collection),
                    readyItems: $readyItemsByBranchId->get($branch->id, new Collection),
                    sessionsByServicePointId: $sessionsByServicePointId,
                    draftsBySessionId: $draftsBySessionId,
                    waiterCallsByServicePointId: $waiterCallsByServicePointId,
                    readyItemsByServicePointId: $readyItemsByServicePointId,
                    canOpenTable: $openTableBranchIds->contains((int) $branch->id),
                    canCloseTable: $closeTableBranchIds->contains((int) $branch->id),
                    assignedAreaNodeIds: $assignedAreaNodeIdsByBranch->get($branch->id, collect()),
                    zoneScope: $zoneScope,
                ))
                ->values()
                ->all(),
            'service_point_count' => $servicePoints->count(),
            'active_session_count' => $sessions->count(),
            'new_draft_count' => $draftOrders->count(),
            'waiter_call_count' => $waiterCalls->count(),
            'bill_request_count' => $billRequestCount,
            'ready_item_count' => $readyItems->count(),
        ];
    }

    public function userHasAccess(User $user): bool
    {
        return $this->accessibleBranchIds($user)->isNotEmpty();
    }

    /**
     * @return Collection<int, int>
     */
    private function accessibleBranchIds(User $user): Collection
    {
        return $this->resolveAccessibleBranchIds->handle($user);
    }

    /**
     * @param  Collection<int, int>  $branchIds
     * @return Collection<int, Collection<int, int>>
     */
    private function assignedAreaNodeIdsByBranch(User $user, Collection $branchIds): Collection
    {
        if ($user->isSuperadmin() || $branchIds->isEmpty()) {
            return collect();
        }

        return AreaNodeWaiter::query()
            ->select(['id', 'branch_id', 'area_node_id', 'user_id'])
            ->where('user_id', $user->id)
            ->whereIn('branch_id', $branchIds)
            ->orderBy('branch_id')
            ->orderBy('area_node_id')
            ->get()
            ->groupBy('branch_id')
            ->map(fn (EloquentCollection $assignments): Collection => $assignments
                ->pluck('area_node_id')
                ->map(fn (int $areaNodeId): int => $areaNodeId)
                ->unique()
                ->values());
    }

    /**
     * @param  Collection<int, int>  $branchIds
     * @param  Collection<int, Collection<int, int>>  $assignedAreaNodeIdsByBranch
     */
    private function applyAssignedAreaNodeFilter(mixed $query, Collection $branchIds, Collection $assignedAreaNodeIdsByBranch): void
    {
        $branchesWithoutAssignments = $branchIds
            ->reject(fn (int $branchId): bool => $assignedAreaNodeIdsByBranch->has($branchId))
            ->values();

        $query->where(function ($areaNodeQuery) use ($assignedAreaNodeIdsByBranch, $branchesWithoutAssignments): void {
            $hasPreviousCondition = false;

            if ($branchesWithoutAssignments->isNotEmpty()) {
                $areaNodeQuery->whereIn('branch_id', $branchesWithoutAssignments);
                $hasPreviousCondition = true;
            }

            foreach ($assignedAreaNodeIdsByBranch as $branchId => $areaNodeIds) {
                $method = $hasPreviousCondition ? 'orWhere' : 'where';

                $areaNodeQuery->{$method}(function ($branchQuery) use ($areaNodeIds, $branchId): void {
                    $branchQuery
                        ->where('branch_id', (int) $branchId)
                        ->whereIn('area_node_id', $areaNodeIds);
                });

                $hasPreviousCondition = true;
            }
        });
    }

    /**
     * @param  Collection<int, int>  $branchIds
     * @param  Collection<int, int>  $servicePointIds
     * @return EloquentCollection<int, TableSession>
     */
    private function openTableSessions(Collection $branchIds, Collection $servicePointIds): EloquentCollection
    {
        if ($branchIds->isEmpty() || $servicePointIds->isEmpty()) {
            return new EloquentCollection;
        }

        return TableSession::query()
            ->select(['id', 'branch_id', 'service_point_id', 'opened_by_user_id', 'opened_by_guest_id', 'status', 'source', 'started_at', 'created_at', 'updated_at'])
            ->withCount(['activeGuests'])
            ->with([
                'openedByUser' => fn ($query) => $query->select(['id', 'name']),
                'openedByGuest' => fn ($query) => $query->select(['id', 'guest_name']),
            ])
            ->whereIn('branch_id', $branchIds)
            ->whereIn('service_point_id', $servicePointIds)
            ->whereIn('status', $this->openSessionStatuses())
            ->orderBy('branch_id')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  Collection<int, int>  $tableSessionIds
     * @return EloquentCollection<int, DraftOrder>
     */
    private function sentDraftOrders(Collection $tableSessionIds): EloquentCollection
    {
        if ($tableSessionIds->isEmpty()) {
            return new EloquentCollection;
        }

        return DraftOrder::query()
            ->select(['id', 'table_session_id', 'status', 'sent_to_waiter_at', 'sent_by_guest_id', 'created_at', 'updated_at'])
            ->withCount(['items'])
            ->with([
                'sentByGuest' => fn ($query) => $query->select(['id', 'guest_name']),
                'items' => fn ($query) => $query->select(['id', 'draft_order_id', 'total_price']),
            ])
            ->whereIn('status', [DraftOrderStatus::SentToWaiter->value, DraftOrderStatus::WaiterReview->value])
            ->whereIn('table_session_id', $tableSessionIds)
            ->orderByDesc('sent_to_waiter_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  Collection<int, int>  $branchIds
     * @param  Collection<int, int>  $servicePointIds
     * @return EloquentCollection<int, WaiterCall>
     */
    private function pendingWaiterCalls(Collection $branchIds, Collection $servicePointIds): EloquentCollection
    {
        if ($branchIds->isEmpty() || $servicePointIds->isEmpty()) {
            return new EloquentCollection;
        }

        return WaiterCall::query()
            ->select([
                'id',
                'branch_id',
                'service_point_id',
                'table_session_id',
                'requested_by_guest_id',
                'status',
                'requested_at',
            ])
            ->with([
                'servicePoint' => fn ($query) => $query
                    ->select(['id', 'branch_id', 'area_node_id', 'name', 'display_number'])
                    ->with(['areaNode' => fn ($areaQuery) => $areaQuery->select(['id', 'branch_id', 'name'])]),
                'requestedByGuest' => fn ($query) => $query->select(['id', 'guest_name']),
            ])
            ->whereIn('branch_id', $branchIds)
            ->whereIn('service_point_id', $servicePointIds)
            ->where('status', WaiterCallStatus::Pending->value)
            ->orderBy('requested_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, int>  $branchIds
     * @param  Collection<int, int>  $servicePointIds
     * @return EloquentCollection<int, KitchenTicketItem>
     */
    private function readyTicketItems(Collection $branchIds, Collection $servicePointIds): EloquentCollection
    {
        if ($branchIds->isEmpty() || $servicePointIds->isEmpty()) {
            return new EloquentCollection;
        }

        return KitchenTicketItem::query()
            ->select([
                'id',
                'kitchen_ticket_id',
                'table_session_guest_id',
                'guest_name',
                'item_name',
                'quantity',
                'status',
                'served_at',
                'comment',
                'created_at',
                'updated_at',
            ])
            ->with([
                'kitchenTicket' => fn ($query) => $query->select([
                    'id',
                    'branch_id',
                    'service_point_id',
                    'table_session_id',
                    'department_name',
                    'sent_at',
                ]),
            ])
            ->where('status', KitchenTicketItemStatus::Ready->value)
            ->whereNull('served_at')
            ->whereHas('kitchenTicket', function ($query) use ($branchIds, $servicePointIds): void {
                $query
                    ->whereIn('branch_id', $branchIds)
                    ->whereIn('service_point_id', $servicePointIds);
            })
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit(150)
            ->get();
    }

    /**
     * @return list<string>
     */
    private function openSessionStatuses(): array
    {
        return [
            TableSessionStatus::Pending->value,
            TableSessionStatus::Active->value,
            TableSessionStatus::WaitingWaiterConfirmation->value,
            TableSessionStatus::PaymentRequested->value,
        ];
    }

    /**
     * @param  Collection<int, ServicePoint>  $servicePoints
     * @param  Collection<int, TableSession>  $sessions
     * @param  Collection<int, DraftOrder>  $drafts
     * @param  Collection<int, WaiterCall>  $waiterCalls
     * @param  Collection<int, TableSession>  $billRequests
     * @param  Collection<int, KitchenTicketItem>  $readyItems
     * @param  Collection<int, Collection<int, TableSession>>  $sessionsByServicePointId
     * @param  Collection<int, DraftOrder>  $draftsBySessionId
     * @param  Collection<int, Collection<int, WaiterCall>>  $waiterCallsByServicePointId
     * @param  Collection<int, Collection<int, KitchenTicketItem>>  $readyItemsByServicePointId
     * @param  Collection<int, int>  $assignedAreaNodeIds
     * @return array<string, mixed>
     */
    private function branchPayload(
        Branch $branch,
        Collection $servicePoints,
        Collection $sessions,
        Collection $drafts,
        Collection $waiterCalls,
        Collection $billRequests,
        Collection $readyItems,
        Collection $sessionsByServicePointId,
        Collection $draftsBySessionId,
        Collection $waiterCallsByServicePointId,
        Collection $readyItemsByServicePointId,
        bool $canOpenTable,
        bool $canCloseTable,
        Collection $assignedAreaNodeIds,
        string $zoneScope,
    ): array {
        $servicePointsById = $servicePoints->keyBy('id');
        $servicePointPayloads = $servicePoints
            ->map(fn (ServicePoint $servicePoint): array => $this->servicePointPayload(
                servicePoint: $servicePoint,
                sessions: $sessionsByServicePointId->get($servicePoint->id, new Collection),
                draftsBySessionId: $draftsBySessionId,
                waiterCalls: $waiterCallsByServicePointId->get($servicePoint->id, new Collection),
                readyItems: $readyItemsByServicePointId->get($servicePoint->id, new Collection),
                currency: $branch->currency,
                canOpenTable: $canOpenTable,
                canCloseTable: $canCloseTable,
                inactivitySettings: $branch->settings,
            ))
            ->values();
        $assignedAreaNodeCount = $assignedAreaNodeIds->count();
        $showingAssignedZonesOnly = $zoneScope === 'mine' && $assignedAreaNodeCount > 0;

        return [
            'id' => $branch->id,
            'name' => $branch->name,
            'brand_name' => $branch->brand?->name,
            'organization_name' => $branch->organization?->name,
            'city' => $branch->city,
            'currency' => $branch->currency,
            'is_active' => $branch->is_active,
            'temporary_closure_active' => $this->temporaryClosureIsActive($branch),
            'temporary_closed_reason' => $branch->temporary_closed_reason,
            'temporary_closed_until_label' => $this->temporaryClosedUntilLabel($branch),
            'zone_scope' => $zoneScope,
            'assigned_area_node_count' => $assignedAreaNodeCount,
            'showing_assigned_zones_only' => $showingAssignedZonesOnly,
            'service_point_count' => count($servicePoints),
            'active_session_count' => count($sessions),
            'new_draft_count' => count($drafts),
            'waiter_call_count' => count($waiterCalls),
            'bill_request_count' => count($billRequests),
            'ready_item_count' => count($readyItems),
            'service_points' => $servicePointPayloads->all(),
            'service_point_zones' => $this->servicePointZonePayloads($servicePointPayloads, $assignedAreaNodeIds),
            'drafts' => $drafts
                ->map(fn (DraftOrder $draftOrder): array => $this->draftPayload($draftOrder, $branch->currency))
                ->values()
                ->all(),
            'waiter_calls' => $waiterCalls
                ->map(fn (WaiterCall $waiterCall): array => $this->waiterCallPayload($waiterCall))
                ->values()
                ->all(),
            'bill_requests' => $billRequests
                ->map(fn (TableSession $tableSession): array => $this->billRequestPayload(
                    tableSession: $tableSession,
                    servicePoint: $servicePointsById->get($tableSession->service_point_id),
                ))
                ->values()
                ->all(),
            'ready_items' => $readyItems
                ->map(fn (KitchenTicketItem $item): array => $this->readyItemPayload(
                    item: $item,
                    servicePoint: $servicePointsById->get((int) $item->kitchenTicket->service_point_id),
                ))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, TableSession>  $sessions
     * @param  Collection<int, DraftOrder>  $draftsBySessionId
     * @param  Collection<int, WaiterCall>  $waiterCalls
     * @param  Collection<int, KitchenTicketItem>  $readyItems
     * @return array<string, mixed>
     */
    private function servicePointPayload(
        ServicePoint $servicePoint,
        Collection $sessions,
        Collection $draftsBySessionId,
        Collection $waiterCalls,
        Collection $readyItems,
        string $currency,
        bool $canOpenTable,
        bool $canCloseTable,
        ?BranchSetting $inactivitySettings,
    ): array {
        $status = $servicePoint->status;
        $sessionPayloads = $sessions
            ->map(fn (TableSession $tableSession): array => $this->sessionPayload(
                tableSession: $tableSession,
                draftOrder: $draftsBySessionId->get($tableSession->id),
                currency: $currency,
                canCloseTable: $canCloseTable,
                inactivitySettings: $inactivitySettings,
            ))
            ->values();
        $newDraftCount = $sessionPayloads
            ->filter(fn (array $session): bool => is_array($session['draft']))
            ->count();
        $billRequestCount = $sessions
            ->filter(fn (TableSession $tableSession): bool => $tableSession->status === TableSessionStatus::PaymentRequested)
            ->count();
        $readyItemCount = count($readyItems);
        $inactiveSessionWarningCount = $sessionPayloads
            ->filter(fn (array $session): bool => (bool) data_get($session, 'inactivity.should_warn'))
            ->count();

        return [
            'id' => $servicePoint->id,
            'name' => $servicePoint->name,
            'display_number' => $servicePoint->display_number,
            'area_id' => $servicePoint->area_node_id,
            'area_name' => $servicePoint->areaNode?->name,
            'status' => $status->value,
            'status_label' => $status->label(),
            'status_color' => $status->badgeColor(),
            'capacity' => $servicePoint->capacity,
            'is_active' => $servicePoint->is_active,
            'waiter_call_count' => count($waiterCalls),
            'bill_request_count' => $billRequestCount,
            'ready_item_count' => $readyItemCount,
            'inactive_session_warning_count' => $inactiveSessionWarningCount,
            'new_draft_count' => $newDraftCount,
            'has_open_session' => $sessionPayloads->isNotEmpty(),
            'has_priority' => $newDraftCount > 0 || count($waiterCalls) > 0 || $billRequestCount > 0 || $readyItemCount > 0 || $inactiveSessionWarningCount > 0,
            'priority_rank' => $this->servicePointPriorityRank($newDraftCount, count($waiterCalls), $billRequestCount, $readyItemCount, $inactiveSessionWarningCount, $status),
            'can_open_table' => $canOpenTable
                && (bool) $servicePoint->is_active
                && $sessionPayloads->isEmpty()
                && ! in_array($status, [ServicePointStatus::Blocked, ServicePointStatus::Closed], true),
            'sessions' => $sessionPayloads->all(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $servicePointPayloads
     * @param  Collection<int, int>  $assignedAreaNodeIds
     * @return list<array<string, mixed>>
     */
    private function servicePointZonePayloads(Collection $servicePointPayloads, Collection $assignedAreaNodeIds): array
    {
        return $servicePointPayloads
            ->groupBy(fn (array $servicePoint): string => (string) ($servicePoint['area_id'] ?? 'no-zone'))
            ->map(function (Collection $zoneServicePoints): array {
                $sortedServicePoints = $zoneServicePoints
                    ->sortBy(fn (array $servicePoint): string => str_pad((string) $servicePoint['priority_rank'], 2, '0', STR_PAD_LEFT)
                        .mb_strtolower((string) $servicePoint['name'])
                        .str_pad((string) $servicePoint['id'], 8, '0', STR_PAD_LEFT))
                    ->values();

                $areaId = $sortedServicePoints->first()['area_id'] ?? null;

                return [
                    'area_id' => $areaId,
                    'name' => $sortedServicePoints->first()['area_name'] ?? null,
                    'service_point_count' => $sortedServicePoints->count(),
                    'priority_count' => $sortedServicePoints
                        ->filter(fn (array $servicePoint): bool => (bool) $servicePoint['has_priority'])
                        ->count(),
                    'service_points' => $sortedServicePoints->all(),
                ];
            })
            ->map(function (array $zone) use ($assignedAreaNodeIds): array {
                $zone['is_assigned'] = $zone['area_id'] !== null && $assignedAreaNodeIds->contains((int) $zone['area_id']);

                return $zone;
            })
            ->sortBy(fn (array $zone): string => ($zone['is_assigned'] ? '0' : '1')
                .($zone['name'] === null ? 'zzzz' : mb_strtolower((string) $zone['name'])))
            ->values()
            ->all();
    }

    private function servicePointPriorityRank(
        int $newDraftCount,
        int $waiterCallCount,
        int $billRequestCount,
        int $readyItemCount,
        int $inactiveSessionWarningCount,
        ServicePointStatus $status,
    ): int {
        if ($newDraftCount > 0) {
            return 10;
        }

        if ($waiterCallCount > 0) {
            return 20;
        }

        if ($billRequestCount > 0) {
            return 30;
        }

        if ($readyItemCount > 0 || $status === ServicePointStatus::ReadyToServe) {
            return 40;
        }

        if ($inactiveSessionWarningCount > 0) {
            return 45;
        }

        if ($status === ServicePointStatus::Cooking) {
            return 50;
        }

        if ($status === ServicePointStatus::Occupied) {
            return 60;
        }

        return 90;
    }

    /**
     * @return array<string, mixed>
     */
    private function billRequestPayload(TableSession $tableSession, ?ServicePoint $servicePoint): array
    {
        return [
            'id' => $tableSession->id,
            'detail_url' => route('restaurant.waiter.tables.show', $tableSession),
            'service_point_name' => $servicePoint?->name,
            'service_point_display_number' => $servicePoint?->display_number,
            'area_name' => $servicePoint?->areaNode?->name,
            'started_at' => $tableSession->started_at?->format('Y-m-d H:i') ?? $tableSession->created_at?->format('Y-m-d H:i'),
            'opened_by' => $this->openedByName($tableSession),
            'active_guest_count' => (int) ($tableSession->active_guests_count ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionPayload(
        TableSession $tableSession,
        ?DraftOrder $draftOrder,
        string $currency,
        bool $canCloseTable,
        ?BranchSetting $inactivitySettings,
    ): array {
        $status = $tableSession->status;

        return [
            'id' => $tableSession->id,
            'detail_url' => route('restaurant.waiter.tables.show', $tableSession),
            'status' => $status->value,
            'status_label' => $status->label(),
            'source_label' => $tableSession->source->label(),
            'started_at' => $tableSession->started_at?->format('Y-m-d H:i') ?? $tableSession->created_at?->format('Y-m-d H:i'),
            'opened_by' => $this->openedByName($tableSession),
            'active_guest_count' => (int) ($tableSession->active_guests_count ?? 0),
            'can_close' => $canCloseTable,
            'inactivity' => $this->buildInactivityState->handle($tableSession, $inactivitySettings),
            'draft' => $draftOrder instanceof DraftOrder ? $this->draftPayload($draftOrder, $currency) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function draftPayload(DraftOrder $draftOrder, string $currency): array
    {
        $totalCents = $draftOrder->items->sum(
            fn (DraftOrderItem $item): int => $this->decimalToCents($item->total_price),
        );

        return [
            'id' => $draftOrder->id,
            'table_session_id' => $draftOrder->table_session_id,
            'status_label' => $draftOrder->status->label(),
            'sent_at' => $draftOrder->sent_to_waiter_at?->format('Y-m-d H:i'),
            'sent_by_guest_name' => $draftOrder->sentByGuest?->guest_name,
            'items_count' => (int) ($draftOrder->items_count ?? 0),
            'total' => $this->formatCents($totalCents).' '.$currency,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function waiterCallPayload(WaiterCall $waiterCall): array
    {
        $status = $waiterCall->status;

        return [
            'id' => $waiterCall->id,
            'table_session_id' => $waiterCall->table_session_id,
            'detail_url' => route('restaurant.waiter.tables.show', $waiterCall->table_session_id),
            'service_point_name' => $waiterCall->servicePoint->name,
            'service_point_display_number' => $waiterCall->servicePoint->display_number,
            'area_name' => $waiterCall->servicePoint->areaNode?->name,
            'guest_name' => $waiterCall->requested_by_guest_id === null ? null : $waiterCall->requestedByGuest->guest_name,
            'status_label' => $status->label(),
            'status_color' => $status->badgeColor(),
            'requested_at' => $waiterCall->requested_at->format('Y-m-d H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readyItemPayload(KitchenTicketItem $item, ?ServicePoint $servicePoint): array
    {
        $status = $item->status;

        return [
            'id' => $item->id,
            'table_session_id' => $item->kitchenTicket->table_session_id,
            'detail_url' => route('restaurant.waiter.tables.show', $item->kitchenTicket->table_session_id),
            'service_point_name' => $servicePoint?->name,
            'service_point_display_number' => $servicePoint?->display_number,
            'area_name' => $servicePoint?->areaNode?->name,
            'guest_name' => $item->guest_name,
            'item_name' => $item->item_name,
            'quantity' => (int) $item->quantity,
            'department_name' => $item->kitchenTicket->department_name,
            'status_label' => $status->label(),
            'status_color' => $status->badgeColor(),
            'ready_at' => $item->updated_at?->format('Y-m-d H:i') ?? $item->created_at?->format('Y-m-d H:i'),
        ];
    }

    /**
     * @param  EloquentCollection<int, DraftOrder>  $draftOrders
     * @param  EloquentCollection<int, TableSession>  $sessions
     * @return Collection<int, Collection<int, DraftOrder>>
     */
    private function draftsByBranchId(EloquentCollection $draftOrders, EloquentCollection $sessions): Collection
    {
        $sessionBranchIds = $sessions->pluck('branch_id', 'id');

        return collect($draftOrders->all())->groupBy(
            fn (DraftOrder $draftOrder): int => (int) $sessionBranchIds->get($draftOrder->table_session_id),
        );
    }

    /**
     * @param  EloquentCollection<int, TableSession>  $sessions
     * @return Collection<int, Collection<int, TableSession>>
     */
    private function billRequestsByBranchId(EloquentCollection $sessions): Collection
    {
        return collect($sessions->all())
            ->filter(fn (TableSession $tableSession): bool => $tableSession->status === TableSessionStatus::PaymentRequested)
            ->groupBy('branch_id');
    }

    private function openedByName(TableSession $tableSession): ?string
    {
        if ($tableSession->opened_by_user_id !== null) {
            return $tableSession->openedByUser->name;
        }

        if ($tableSession->opened_by_guest_id !== null) {
            return $tableSession->openedByGuest->guest_name;
        }

        return null;
    }

    private function temporaryClosureIsActive(Branch $branch): bool
    {
        if (! (bool) $branch->is_temporarily_closed) {
            return false;
        }

        $closedUntil = $branch->temporaryClosedUntilForBranch();

        if ($closedUntil === null) {
            return true;
        }

        $timezone = $branch->timezone ?: config('app.timezone');

        return $closedUntil->greaterThan(now($timezone));
    }

    private function temporaryClosedUntilLabel(Branch $branch): ?string
    {
        $closedUntil = $branch->temporaryClosedUntilForBranch();

        if ($closedUntil === null) {
            return null;
        }

        return $closedUntil->format('d.m H:i');
    }

    private function decimalToCents(string|int|float|null $amount): int
    {
        $normalized = number_format((float) ($amount ?? 0), 2, '.', '');
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '-');
        [$whole, $fraction] = explode('.', $normalized);
        $cents = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');

        return $negative ? -$cents : $cents;
    }

    private function formatCents(int $cents): string
    {
        $negative = $cents < 0;
        $absoluteCents = abs($cents);
        $formatted = intdiv($absoluteCents, 100).'.'.str_pad((string) ($absoluteCents % 100), 2, '0', STR_PAD_LEFT);

        return $negative ? '-'.$formatted : $formatted;
    }
}
