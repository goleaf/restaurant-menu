<?php

namespace App\Actions\Waiter;

use App\Enums\DraftOrderStatus;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\TableSessionStatus;
use App\Enums\WaiterCallStatus;
use App\Models\Branch;
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
    public function handle(User $user): array
    {
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
            ])
            ->whereIn('id', $branchIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $branchIds = $branches->pluck('id');

        $servicePoints = ServicePoint::query()
            ->select(['id', 'branch_id', 'area_node_id', 'type', 'name', 'display_number', 'capacity', 'icon', 'status', 'is_active'])
            ->with(['areaNode' => fn ($query) => $query->select(['id', 'branch_id', 'name'])])
            ->whereIn('branch_id', $branchIds)
            ->orderBy('branch_id')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $sessions = TableSession::query()
            ->select(['id', 'branch_id', 'service_point_id', 'opened_by_user_id', 'opened_by_guest_id', 'status', 'source', 'started_at', 'created_at'])
            ->withCount(['activeGuests'])
            ->with([
                'openedByUser' => fn ($query) => $query->select(['id', 'name']),
                'openedByGuest' => fn ($query) => $query->select(['id', 'guest_name']),
            ])
            ->whereIn('branch_id', $branchIds)
            ->whereIn('status', $this->openSessionStatuses())
            ->orderBy('branch_id')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->get();

        $draftOrders = $this->sentDraftOrders($sessions->pluck('id'));
        $waiterCalls = $this->pendingWaiterCalls($branchIds);
        $readyItems = $this->readyTicketItems($branchIds);
        $draftsBySessionId = $draftOrders->keyBy('table_session_id');
        $sessionsByServicePointId = $sessions->groupBy('service_point_id');
        $waiterCallsByServicePointId = $waiterCalls->groupBy('service_point_id');
        $readyItemsByServicePointId = $readyItems->groupBy(
            fn (KitchenTicketItem $item): int => (int) $item->kitchenTicket?->service_point_id,
        );
        $servicePointsByBranchId = $servicePoints->groupBy('branch_id');
        $sessionsByBranchId = $sessions->groupBy('branch_id');
        $draftsByBranchId = $this->draftsByBranchId($draftOrders, $sessions);
        $waiterCallsByBranchId = $waiterCalls->groupBy('branch_id');
        $readyItemsByBranchId = $readyItems->groupBy(
            fn (KitchenTicketItem $item): int => (int) $item->kitchenTicket?->branch_id,
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
     * @return EloquentCollection<int, WaiterCall>
     */
    private function pendingWaiterCalls(Collection $branchIds): EloquentCollection
    {
        if ($branchIds->isEmpty()) {
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
            ->where('status', WaiterCallStatus::Pending->value)
            ->orderBy('requested_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, int>  $branchIds
     * @return EloquentCollection<int, KitchenTicketItem>
     */
    private function readyTicketItems(Collection $branchIds): EloquentCollection
    {
        if ($branchIds->isEmpty()) {
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
            ->whereHas('kitchenTicket', function ($query) use ($branchIds): void {
                $query->whereIn('branch_id', $branchIds);
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
            ))
            ->values();

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
            'service_point_count' => count($servicePoints),
            'active_session_count' => count($sessions),
            'new_draft_count' => count($drafts),
            'waiter_call_count' => count($waiterCalls),
            'bill_request_count' => count($billRequests),
            'ready_item_count' => count($readyItems),
            'service_points' => $servicePointPayloads->all(),
            'service_point_zones' => $this->servicePointZonePayloads($servicePointPayloads),
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
                    servicePoint: $servicePointsById->get((int) $item->kitchenTicket?->service_point_id),
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
    ): array {
        $status = $servicePoint->status instanceof ServicePointStatus
            ? $servicePoint->status
            : ServicePointStatus::from((string) $servicePoint->status);
        $sessionPayloads = $sessions
            ->map(fn (TableSession $tableSession): array => $this->sessionPayload(
                tableSession: $tableSession,
                draftOrder: $draftsBySessionId->get($tableSession->id),
                currency: $currency,
                canCloseTable: $canCloseTable,
            ))
            ->values();
        $newDraftCount = $sessionPayloads
            ->filter(fn (array $session): bool => is_array($session['draft']))
            ->count();
        $billRequestCount = $sessions
            ->filter(fn (TableSession $tableSession): bool => $tableSession->status === TableSessionStatus::PaymentRequested)
            ->count();
        $readyItemCount = count($readyItems);

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
            'new_draft_count' => $newDraftCount,
            'has_open_session' => $sessionPayloads->isNotEmpty(),
            'has_priority' => $newDraftCount > 0 || count($waiterCalls) > 0 || $billRequestCount > 0 || $readyItemCount > 0,
            'priority_rank' => $this->servicePointPriorityRank($newDraftCount, count($waiterCalls), $billRequestCount, $readyItemCount, $status),
            'can_open_table' => $canOpenTable
                && (bool) $servicePoint->is_active
                && $sessionPayloads->isEmpty()
                && ! in_array($status, [ServicePointStatus::Blocked, ServicePointStatus::Closed], true),
            'sessions' => $sessionPayloads->all(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $servicePointPayloads
     * @return list<array<string, mixed>>
     */
    private function servicePointZonePayloads(Collection $servicePointPayloads): array
    {
        return $servicePointPayloads
            ->groupBy(fn (array $servicePoint): string => (string) ($servicePoint['area_id'] ?? 'no-zone'))
            ->map(function (Collection $zoneServicePoints): array {
                $sortedServicePoints = $zoneServicePoints
                    ->sortBy(fn (array $servicePoint): string => str_pad((string) $servicePoint['priority_rank'], 2, '0', STR_PAD_LEFT)
                        .mb_strtolower((string) $servicePoint['name'])
                        .str_pad((string) $servicePoint['id'], 8, '0', STR_PAD_LEFT))
                    ->values();

                return [
                    'area_id' => $sortedServicePoints->first()['area_id'] ?? null,
                    'name' => $sortedServicePoints->first()['area_name'] ?? null,
                    'service_point_count' => $sortedServicePoints->count(),
                    'priority_count' => $sortedServicePoints
                        ->filter(fn (array $servicePoint): bool => (bool) $servicePoint['has_priority'])
                        ->count(),
                    'service_points' => $sortedServicePoints->all(),
                ];
            })
            ->sortBy(fn (array $zone): string => ($zone['name'] === null ? 'zzzz' : mb_strtolower((string) $zone['name'])))
            ->values()
            ->all();
    }

    private function servicePointPriorityRank(
        int $newDraftCount,
        int $waiterCallCount,
        int $billRequestCount,
        int $readyItemCount,
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
            'opened_by' => $tableSession->openedByUser?->name ?? $tableSession->openedByGuest?->guest_name,
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
    ): array {
        $status = $tableSession->status instanceof TableSessionStatus
            ? $tableSession->status
            : TableSessionStatus::from((string) $tableSession->status);

        return [
            'id' => $tableSession->id,
            'detail_url' => route('restaurant.waiter.tables.show', $tableSession),
            'status' => $status->value,
            'status_label' => $status->label(),
            'source_label' => $tableSession->source->label(),
            'started_at' => $tableSession->started_at?->format('Y-m-d H:i') ?? $tableSession->created_at?->format('Y-m-d H:i'),
            'opened_by' => $tableSession->openedByUser?->name ?? $tableSession->openedByGuest?->guest_name,
            'active_guest_count' => (int) ($tableSession->active_guests_count ?? 0),
            'can_close' => $canCloseTable,
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
            'status_label' => $draftOrder->status?->label(),
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
        $status = $waiterCall->status instanceof WaiterCallStatus
            ? $waiterCall->status
            : WaiterCallStatus::from((string) $waiterCall->status);

        return [
            'id' => $waiterCall->id,
            'table_session_id' => $waiterCall->table_session_id,
            'detail_url' => route('restaurant.waiter.tables.show', $waiterCall->table_session_id),
            'service_point_name' => $waiterCall->servicePoint?->name,
            'service_point_display_number' => $waiterCall->servicePoint?->display_number,
            'area_name' => $waiterCall->servicePoint?->areaNode?->name,
            'guest_name' => $waiterCall->requestedByGuest?->guest_name,
            'status_label' => $status->label(),
            'status_color' => $status->badgeColor(),
            'requested_at' => $waiterCall->requested_at?->format('Y-m-d H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readyItemPayload(KitchenTicketItem $item, ?ServicePoint $servicePoint): array
    {
        $status = $item->status instanceof KitchenTicketItemStatus
            ? $item->status
            : KitchenTicketItemStatus::from((string) $item->status);

        return [
            'id' => $item->id,
            'table_session_id' => $item->kitchenTicket?->table_session_id,
            'detail_url' => route('restaurant.waiter.tables.show', $item->kitchenTicket?->table_session_id),
            'service_point_name' => $servicePoint?->name,
            'service_point_display_number' => $servicePoint?->display_number,
            'area_name' => $servicePoint?->areaNode?->name,
            'guest_name' => $item->guest_name,
            'item_name' => $item->item_name,
            'quantity' => (int) $item->quantity,
            'department_name' => $item->kitchenTicket?->department_name,
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

        return $draftOrders->groupBy(
            fn (DraftOrder $draftOrder): int => (int) $sessionBranchIds->get($draftOrder->table_session_id),
        );
    }

    /**
     * @param  EloquentCollection<int, TableSession>  $sessions
     * @return Collection<int, Collection<int, TableSession>>
     */
    private function billRequestsByBranchId(EloquentCollection $sessions): Collection
    {
        return $sessions
            ->filter(fn (TableSession $tableSession): bool => $tableSession->status === TableSessionStatus::PaymentRequested)
            ->groupBy('branch_id');
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
