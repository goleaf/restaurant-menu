<?php

declare(strict_types=1);

namespace App\Actions\Departments;

use App\Enums\DepartmentTicketFilter;
use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\KitchenTicketStatus;
use App\Enums\MenuAllergen;
use App\Enums\OrderStatus;
use App\Enums\OrderStatusLogEvent;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionStatus;
use App\Models\Branch;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\OrderStatusLog;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\User;
use App\Support\LocalizedDateFormatter;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;

class BuildDepartmentDashboardAction
{
    public function __construct(
        private readonly ResolveAccessibleDepartmentIdsAction $resolveAccessibleDepartmentIds,
        private readonly BuildDepartmentTicketDelayTimerAction $buildDepartmentTicketDelayTimer,
    ) {}

    /**
     * @param  list<KitchenDepartmentType>  $departmentTypes
     * @param  list<SystemRole>  $roleCodes
     * @param  list<SystemPermission>  $permissionCodes
     * @param  list<int>|null  $accessibleDepartmentIds  Internal, freshly authorized union of separate production families.
     * @return array{
     *     has_access: bool,
     *     departments: list<array<string, mixed>>,
     *     selected_department_id: int|null,
     *     selected_department_name: string|null,
     *     branch_timezone: string,
     *     is_overview: bool,
     *     selected_ticket_id: int|null,
     *     routing_issue_count: int,
     *     routing_issues: list<array<string, mixed>>,
     *     tickets: list<array<string, mixed>>,
     *     ticket_count: int,
     *     new_item_count: int,
     *     accepted_item_count: int,
     *     in_progress_item_count: int,
     *     ready_item_count: int,
     *     completed_item_count: int,
     *     cancelled_item_count: int,
     *     recent_cancelled_item_count: int,
     *     portion_count: int,
     *     ticket_page: int,
     *     has_previous_ticket_page: bool,
     *     has_next_ticket_page: bool,
     *     sort_label: string
     * }
     */
    public function handle(
        User $user,
        ?int $selectedDepartmentId,
        array $departmentTypes,
        array $roleCodes,
        array $permissionCodes,
        DepartmentTicketFilter $filter = DepartmentTicketFilter::Active,
        int $page = 1,
        int $perPage = 24,
        ?int $branchId = null,
        ?array $accessibleDepartmentIds = null,
        bool $overview = false,
        ?int $selectedTicketId = null,
        int $itemPage = 1,
    ): array {
        return DB::transaction(function () use ($user, $selectedDepartmentId, $departmentTypes, $roleCodes, $permissionCodes, $filter, $page, $perPage, $branchId, $accessibleDepartmentIds, $overview, $selectedTicketId, $itemPage): array {
            $departmentIds = $accessibleDepartmentIds === null
                ? $this->resolveAccessibleDepartmentIds->handle($user, $departmentTypes, $roleCodes, $permissionCodes)
                : collect($accessibleDepartmentIds);

            if ($branchId !== null) {
                $departmentIds = KitchenDepartment::query()->whereIn('id', $departmentIds)->where('branch_id', $branchId)->pluck('id');
                abort_if($selectedDepartmentId !== null && ! $departmentIds->contains($selectedDepartmentId), 403);
            }

            abort_if($selectedDepartmentId !== null && ! $departmentIds->contains($selectedDepartmentId), 403);

            if ($departmentIds->isEmpty()) {
                return $this->emptyPayload(false);
            }

            $departments = $this->departments($departmentIds, $departmentTypes);
            abort_if($selectedDepartmentId !== null && ! $departments->contains('id', $selectedDepartmentId), 403);
            $selectedDepartment = $overview ? null : $this->selectedDepartment($departments, $selectedDepartmentId);

            if (! $overview && ! $selectedDepartment instanceof KitchenDepartment) {
                return $this->emptyPayload(false);
            }

            $page = $selectedTicketId === null ? max(1, $page) : 1;
            $perPage = (int) Number::clamp($perPage, 1, 50);
            $scopeIds = $overview ? $departments->modelKeys() : [$selectedDepartment->id];
            $itemPage = max(1, $itemPage);
            $ticketPage = $this->ticketsFor($scopeIds, $filter, $page, $perPage, $selectedTicketId, $itemPage);
            $tickets = $ticketPage['tickets'];
            $itemCounts = $this->itemCountsFor($scopeIds, $filter, $selectedTicketId);

            return [
                'has_access' => true,
                'departments' => $departments
                    ->map(fn (KitchenDepartment $department): array => $this->departmentPayload($department))
                    ->values()
                    ->all(),
                'selected_department_id' => $selectedDepartment?->id,
                'selected_department_name' => $selectedDepartment?->name,
                'branch_timezone' => ($selectedDepartment ?? $departments->first())?->branch->timezone ?: config('app.timezone'),
                'is_overview' => $overview,
                'selected_ticket_id' => $selectedTicketId,
                'tickets' => $tickets
                    ->map(fn (KitchenTicket $ticket): array => $this->ticketPayload($ticket, $itemPage, $departments->firstWhere('id', $ticket->kitchen_department_id)))
                    ->values()
                    ->all(),
                'ticket_count' => $ticketPage['total'],
                ...$itemCounts,
                ...$this->routingIssues($user, $branchId, $departments),
                'ticket_page' => $page,
                'has_previous_ticket_page' => $page > 1,
                'has_next_ticket_page' => $page * $perPage < $ticketPage['total'],
                'sort_label' => $filter->isHistory()
                    ? __('ui.departments.dashboard.newest_first')
                    : __('ui.departments.dashboard.oldest_first'),
            ];
        }, attempts: 3);
    }

    /**
     * @param  list<KitchenDepartmentType>  $departmentTypes
     * @param  list<SystemRole>  $roleCodes
     * @param  list<SystemPermission>  $permissionCodes
     */
    public function userHasAccess(User $user, array $departmentTypes, array $roleCodes, array $permissionCodes): bool
    {
        return $this->resolveAccessibleDepartmentIds->userHasAccess($user, $departmentTypes, $roleCodes, $permissionCodes);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(bool $hasAccess): array
    {
        return [
            'has_access' => $hasAccess,
            'departments' => [],
            'branch_timezone' => config('app.timezone'),
            'selected_department_id' => null,
            'selected_department_name' => null,
            'is_overview' => false,
            'selected_ticket_id' => null,
            'routing_issue_count' => 0,
            'routing_issues' => [],
            'tickets' => [],
            'ticket_count' => 0,
            'new_item_count' => 0,
            'accepted_item_count' => 0,
            'in_progress_item_count' => 0,
            'ready_item_count' => 0,
            'completed_item_count' => 0,
            'cancelled_item_count' => 0,
            'recent_cancelled_item_count' => 0,
            'portion_count' => 0,
            'ticket_page' => 1,
            'has_previous_ticket_page' => false,
            'has_next_ticket_page' => false,
            'sort_label' => __('ui.departments.dashboard.oldest_first'),
        ];
    }

    /**
     * @param  Collection<int, int>  $departmentIds
     * @param  list<KitchenDepartmentType>  $departmentTypes
     * @return EloquentCollection<int, KitchenDepartment>
     */
    private function departments(Collection $departmentIds, array $departmentTypes): EloquentCollection
    {
        return KitchenDepartment::query()
            ->select(['id', 'branch_id', 'type', 'name', 'sort_order', 'is_active'])
            ->with([
                'branch' => fn ($query) => $query
                    ->select(['id', 'organization_id', 'brand_id', 'name', 'city', 'timezone'])
                    ->with([
                        'organization' => fn ($organizationQuery) => $organizationQuery->select(['id', 'name']),
                        'brand' => fn ($brandQuery) => $brandQuery->select(['id', 'organization_id', 'name']),
                    ]),
            ])
            ->whereIn('id', $departmentIds)
            ->when($departmentTypes !== [], function ($query) use ($departmentTypes): void {
                $query->whereIn(
                    'type',
                    array_map(fn (KitchenDepartmentType $type): string => $type->value, $departmentTypes),
                );
            })
            ->orderBy('branch_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    private function selectedDepartment(EloquentCollection $departments, ?int $selectedDepartmentId): ?KitchenDepartment
    {
        if ($selectedDepartmentId !== null) {
            $selectedDepartment = $departments->firstWhere('id', $selectedDepartmentId);

            if ($selectedDepartment instanceof KitchenDepartment) {
                return $selectedDepartment;
            }
        }

        $firstDepartment = $departments->first();

        return $firstDepartment instanceof KitchenDepartment ? $firstDepartment : null;
    }

    /**
     * @param  list<int>  $departmentIds
     * @return array{tickets: EloquentCollection<int, KitchenTicket>, total: int}
     */
    private function ticketsFor(
        array $departmentIds,
        DepartmentTicketFilter $filter,
        int $page,
        int $perPage,
        ?int $selectedTicketId,
        int $itemPage,
    ): array {
        $query = KitchenTicket::query()
            ->select([
                'id',
                'order_id',
                'branch_id',
                'service_point_id',
                'table_session_id',
                'kitchen_department_id',
                'department_type',
                'department_name',
                'status',
                'sent_at',
                'created_at',
            ])
            ->with([
                'order' => fn ($query) => $query->select(['id', 'status', 'metadata']),
            ])
            ->whereIn('kitchen_department_id', $departmentIds)
            ->where('status', KitchenTicketStatus::Sent->value)
            ->when($selectedTicketId !== null, fn ($query) => $query->whereKey($selectedTicketId))
            ->whereHas('items', function ($query) use ($filter, $selectedTicketId): void {
                if ($selectedTicketId === null) {
                    $this->applyItemFilter($query, $filter);
                }
            })
            ->with(['items' => function ($query) use ($filter, $selectedTicketId, $itemPage): void {
                $query->select([
                    'id',
                    'kitchen_ticket_id',
                    'order_item_id',
                    'table_session_guest_id',
                    'menu_item_id',
                    'item_name',
                    'quantity',
                    'status',
                    'served_at',
                    'served_by_user_id',
                    'selected_modifiers',
                    'allergens_snapshot',
                    'comment',
                    'created_at',
                    'updated_at',
                ])
                    ->addSelect(['notification_delivery_state' => OrderStatusLog::query()
                        ->select('metadata->notifications->state')
                        ->whereIn('order_id', KitchenTicket::query()->select('order_id')->whereColumn('id', 'kitchen_ticket_items.kitchen_ticket_id'))
                        ->whereColumn('metadata->kitchen_ticket_item_id', 'kitchen_ticket_items.id')
                        ->where('event', OrderStatusLogEvent::TicketItemStatusChanged->value)
                        ->whereColumn('new_status', 'kitchen_ticket_items.status')
                        ->orderByDesc('occurred_at')->orderByDesc('id')->limit(1)])
                    ->with(['orderItem' => fn ($orderItemQuery) => $orderItemQuery->select([
                        'id',
                        'cancellation_reason',
                        'cancelled_at',
                        'variant_name',
                    ])]);
                if ($selectedTicketId === null) {
                    $this->applyItemFilter($query, $filter);
                }
                $query->orderBy('created_at')->orderBy('id')->offset(($itemPage - 1) * 100)->limit(100);
            }])
            ->withCount([
                'items as full_item_count',
                'items as matching_item_count' => function ($query) use ($filter, $selectedTicketId): void {
                    if ($selectedTicketId === null) {
                        $this->applyItemFilter($query, $filter);
                    }
                },
                'items as full_new_count' => fn ($query) => $query->where('status', KitchenTicketItemStatus::New->value),
                'items as full_accepted_count' => fn ($query) => $query->where('status', KitchenTicketItemStatus::Accepted->value),
                'items as full_in_progress_count' => fn ($query) => $query->where('status', KitchenTicketItemStatus::InProgress->value),
                'items as full_ready_count' => fn ($query) => $query->where('status', KitchenTicketItemStatus::Ready->value),
                'items as full_served_count' => fn ($query) => $query->where('status', KitchenTicketItemStatus::Ready->value)->whereNotNull('served_at'),
                'items as full_cancelled_count' => fn ($query) => $query->where('status', KitchenTicketItemStatus::Cancelled->value),
                'items as missing_ready_event_count' => fn ($query) => $query
                    ->where('status', KitchenTicketItemStatus::Ready->value)->whereNull('served_at')
                    ->whereNotIn('id', OrderStatusLog::query()
                        ->select('metadata->kitchen_ticket_item_id')
                        ->whereColumn('order_id', 'kitchen_tickets.order_id')
                        ->where('event', OrderStatusLogEvent::TicketItemStatusChanged->value)
                        ->where('new_status', KitchenTicketItemStatus::Ready->value)
                        ->whereNotNull('metadata->kitchen_ticket_item_id')),
            ])
            ->withSum('items as full_portion_count', 'quantity')
            ->withMax('items as last_served_at', 'served_at')
            ->addSelect([
                'table_session_status' => TableSession::query()->select('status')
                    ->whereColumn('id', 'kitchen_tickets.table_session_id')->whereColumn('branch_id', 'kitchen_tickets.branch_id')->limit(1),
                'current_service_point_id' => TableSession::query()->select('service_point_id')
                    ->whereColumn('id', 'kitchen_tickets.table_session_id')->whereColumn('branch_id', 'kitchen_tickets.branch_id')->limit(1),
                'preparation_started_at' => $this->ticketEventTime(KitchenTicketItemStatus::InProgress),
                'ready_at' => $this->ticketEventTime(KitchenTicketItemStatus::Ready),
            ]);

        if ($selectedTicketId !== null) {
            abort_unless((clone $query)->exists(), 404);
        }

        if (! $filter->isHistory() && $selectedTicketId === null) {
            $query->whereHas('order', function ($orderQuery): void {
                $orderQuery->where('status', '!=', OrderStatus::Cancelled->value);
            });
        }

        $total = (clone $query)->count();
        $direction = $filter->isHistory() ? 'desc' : 'asc';
        $tickets = $query
            ->orderBy('sent_at', $direction)
            ->orderBy('id', $direction)
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $servicePointIds = $tickets->pluck('service_point_id')->merge($tickets->pluck('current_service_point_id'))->filter()->unique();
        $servicePoints = ServicePoint::query()->withTrashed()
            ->select(['id', 'branch_id', 'area_node_id', 'name', 'display_number', 'status'])
            ->with(['areaNode:id,branch_id,name'])->whereIn('id', $servicePointIds)->get()->keyBy('id');
        $tickets->each(function (KitchenTicket $ticket) use ($servicePoints): void {
            $ticket->setRelation('servicePoint', $servicePoints->get($ticket->service_point_id));
            $ticket->setRelation('currentServicePoint', $servicePoints->get($ticket->getAttribute('current_service_point_id')) ?? $ticket->servicePoint);
        });

        return [
            'tickets' => $tickets,
            'total' => $total,
        ];
    }

    private function applyItemFilter(Builder|Relation $query, DepartmentTicketFilter $filter): void
    {
        match ($filter) {
            DepartmentTicketFilter::Active => $query
                ->whereIn('kitchen_ticket_items.status', [
                    KitchenTicketItemStatus::New->value,
                    KitchenTicketItemStatus::Accepted->value,
                    KitchenTicketItemStatus::InProgress->value,
                ])
                ->whereNull('kitchen_ticket_items.served_at'),
            DepartmentTicketFilter::New => $query
                ->where('kitchen_ticket_items.status', KitchenTicketItemStatus::New->value)
                ->whereNull('kitchen_ticket_items.served_at'),
            DepartmentTicketFilter::Accepted => $query
                ->where('kitchen_ticket_items.status', KitchenTicketItemStatus::Accepted->value)
                ->whereNull('kitchen_ticket_items.served_at'),
            DepartmentTicketFilter::InProgress => $query
                ->where('kitchen_ticket_items.status', KitchenTicketItemStatus::InProgress->value)
                ->whereNull('kitchen_ticket_items.served_at'),
            DepartmentTicketFilter::Ready => $query
                ->where('kitchen_ticket_items.status', KitchenTicketItemStatus::Ready->value)
                ->whereNull('kitchen_ticket_items.served_at'),
            DepartmentTicketFilter::Completed => $query
                ->where('kitchen_ticket_items.status', KitchenTicketItemStatus::Ready->value)
                ->whereNotNull('kitchen_ticket_items.served_at'),
            DepartmentTicketFilter::History => $query->where(fn ($historyQuery) => $historyQuery
                ->where('kitchen_ticket_items.status', KitchenTicketItemStatus::Cancelled->value)
                ->orWhereHas('kitchenTicket.order', fn ($orderQuery) => $orderQuery->where('status', OrderStatus::Cancelled->value))
                ->orWhere(fn ($servedQuery) => $servedQuery->where('kitchen_ticket_items.status', KitchenTicketItemStatus::Ready->value)->whereNotNull('kitchen_ticket_items.served_at'))),
            DepartmentTicketFilter::Cancelled => $query->where(fn ($cancelledQuery) => $cancelledQuery
                ->where('kitchen_ticket_items.status', KitchenTicketItemStatus::Cancelled->value)
                ->orWhereHas('kitchenTicket.order', fn ($orderQuery) => $orderQuery->where('status', OrderStatus::Cancelled->value))),
        };
    }

    /**
     * @param  list<int>  $departmentIds
     * @return array{
     *     new_item_count: int,
     *     accepted_item_count: int,
     *     in_progress_item_count: int,
     *     ready_item_count: int,
     *     completed_item_count: int,
     *     cancelled_item_count: int,
     *     recent_cancelled_item_count: int,
     *     portion_count: int
     * }
     */
    private function itemCountsFor(array $departmentIds, DepartmentTicketFilter $currentFilter, ?int $selectedTicketId): array
    {
        $filters = [
            'new_item_count' => DepartmentTicketFilter::New,
            'accepted_item_count' => DepartmentTicketFilter::Accepted,
            'in_progress_item_count' => DepartmentTicketFilter::InProgress,
            'ready_item_count' => DepartmentTicketFilter::Ready,
            'completed_item_count' => DepartmentTicketFilter::Completed,
            'cancelled_item_count' => DepartmentTicketFilter::Cancelled,
        ];
        $aggregates = [];
        foreach ($filters as $alias => $filter) {
            $aggregates['ticketItems as '.$alias] = function ($query) use ($filter, $currentFilter): void {
                if (! $currentFilter->isHistory() && $filter === DepartmentTicketFilter::Completed) {
                    $query->whereKey(-1);
                }
                if (! $currentFilter->isHistory() && $filter === DepartmentTicketFilter::Cancelled) {
                    $query->where(fn ($recentQuery) => $recentQuery->where('kitchen_ticket_items.updated_at', '>=', now()->subDay())
                        ->orWhereHas('kitchenTicket.order', fn ($orderQuery) => $orderQuery
                            ->where('status', OrderStatus::Cancelled->value)->where('updated_at', '>=', now()->subDay())));
                }
                $this->applyItemFilter($query, $filter);
                $query->where('kitchen_tickets.status', KitchenTicketStatus::Sent->value);
                if (! $filter->isHistory()) {
                    $query->whereHas('kitchenTicket.order', fn ($orderQuery) => $orderQuery->where('status', '!=', OrderStatus::Cancelled->value));
                }
            };
        }
        $aggregates['ticketItems as recent_cancelled_item_count'] = function ($query): void {
            $this->applyItemFilter($query, DepartmentTicketFilter::Cancelled);
            $query->where('kitchen_tickets.status', KitchenTicketStatus::Sent->value)
                ->where(fn ($recentQuery) => $recentQuery->where('kitchen_ticket_items.updated_at', '>=', now()->subDay())
                    ->orWhereHas('kitchenTicket.order', fn ($orderQuery) => $orderQuery
                        ->where('status', OrderStatus::Cancelled->value)->where('updated_at', '>=', now()->subDay())));
        };
        $departments = KitchenDepartment::query()->select(['id'])->whereIn('id', $departmentIds)->withCount($aggregates)
            ->withSum(['ticketItems as portion_count' => function ($query) use ($currentFilter, $selectedTicketId): void {
                $query->where('kitchen_tickets.status', KitchenTicketStatus::Sent->value);
                if ($selectedTicketId !== null) {
                    $query->where('kitchen_tickets.id', $selectedTicketId);
                } else {
                    $this->applyItemFilter($query, $currentFilter);
                    if (! $currentFilter->isHistory()) {
                        $query->whereHas('kitchenTicket.order', fn ($orderQuery) => $orderQuery->where('status', '!=', OrderStatus::Cancelled->value));
                    }
                }
            }], 'quantity')->get();

        return [
            ...array_map(fn ($alias): int => (int) $departments->sum($alias), array_combine(array_keys($filters), array_keys($filters))),
            'recent_cancelled_item_count' => (int) $departments->sum('recent_cancelled_item_count'),
            'portion_count' => (int) $departments->sum('portion_count'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function departmentPayload(KitchenDepartment $department): array
    {
        return [
            'id' => $department->id,
            'name' => $department->name,
            'type_label' => $department->type->label(),
            'is_active' => $department->is_active,
            'branch_name' => $department->branch->name,
            'brand_name' => $department->branch->brand->name,
            'organization_name' => $department->branch->organization->name,
            'label' => $department->branch->name.' / '.$department->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketPayload(KitchenTicket $ticket, int $itemPage, ?KitchenDepartment $department): array
    {
        $status = $ticket->status;
        $orderStatus = $ticket->order->status;
        $sessionStatus = TableSessionStatus::tryFrom((string) $ticket->getAttribute('table_session_status'));
        $timezone = $department?->branch->timezone ?: config('app.timezone');
        $canMutate = in_array($orderStatus, [OrderStatus::SentToKitchenBar, OrderStatus::InProgress, OrderStatus::Ready], true)
            && $sessionStatus !== null && ! $sessionStatus->locksOrderChanges();
        $items = $ticket->items->map(fn (KitchenTicketItem $item): array => $this->itemPayload($item, $canMutate, $ticket, $timezone))->values()->all();
        $servicePoint = $ticket->getRelation('currentServicePoint');
        $displayNumber = trim((string) ($servicePoint->display_number ?? ''));
        $servicePointName = trim((string) ($servicePoint->name ?? ''));
        $receivedAt = $ticket->sent_at ?? $ticket->created_at;
        $workStatus = $this->workStatusPayload($ticket);
        $fullCount = (int) $ticket->getAttribute('full_item_count');
        $terminal = $orderStatus === OrderStatus::Cancelled || $fullCount > 0 && (int) $ticket->getAttribute('full_served_count') + (int) $ticket->getAttribute('full_cancelled_count') === $fullCount;
        $readyAt = (int) $ticket->getAttribute('missing_ready_event_count') === 0 ? $this->dateAttribute($ticket, 'ready_at') : null;
        $preparationStartedAt = $this->dateAttribute($ticket, 'preparation_started_at');
        $phase = match (true) {
            $terminal => 'completed',
            $workStatus['value'] === KitchenTicketItemStatus::Ready->value => 'awaiting_service',
            $workStatus['value'] === KitchenTicketItemStatus::InProgress->value => 'preparing',
            default => 'received',
        };
        $timerStart = match ($phase) {
            'awaiting_service' => $readyAt,
            'preparing' => $preparationStartedAt,
            default => $receivedAt,
        };
        $terminalAt = $orderStatus === OrderStatus::Cancelled
            ? (isset($ticket->order->metadata['cancelled_at']) ? CarbonImmutable::parse($ticket->order->metadata['cancelled_at']) : null)
            : ((int) $ticket->getAttribute('full_cancelled_count') === 0 ? $this->dateAttribute($ticket, 'last_served_at') : null);
        $timer = $this->buildDepartmentTicketDelayTimer->handle($timerStart, $terminal ? $terminalAt ?? $receivedAt : null);
        if ($phase === 'awaiting_service' || $terminal || $timerStart === null) {
            $timer['delay_state'] = 'on-track';
            $timer['delay_status_label'] = '';
            $timer['delay_label'] = null;
            $timer['delay_description'] = null;
        }

        return [
            'id' => $ticket->id,
            'order_id' => $ticket->order_id,
            'table_session_id' => $ticket->table_session_id,
            'department_id' => $ticket->kitchen_department_id,
            'department_name' => $ticket->department_name,
            'department_type_label' => KitchenDepartmentType::tryFrom($ticket->department_type)?->label() ?? $ticket->department_type,
            'is_department_active' => $department->is_active ?? false,
            'service_point_id' => $servicePoint?->id,
            'service_point_name' => $servicePointName === '' ? __('guest.table.service_point') : $servicePointName,
            'service_point_display_number' => $displayNumber,
            'service_point_label' => $this->servicePointLabel($displayNumber, $servicePointName),
            'original_service_point_id' => $ticket->service_point_id,
            'original_service_point_label' => $this->servicePointLabel((string) ($ticket->servicePoint->display_number ?? ''), (string) ($ticket->servicePoint->name ?? '')),
            'zone_name' => $servicePoint?->areaNode?->name,
            'status_value' => $status->value,
            'status_label' => $status->label(),
            'order_status_value' => $orderStatus->value,
            'order_status_label' => $orderStatus->label(),
            'work_status' => $workStatus,
            'sent_at' => LocalizedDateFormatter::dateTime($receivedAt?->copy()->setTimezone($timezone)),
            'created_time' => LocalizedDateFormatter::time($ticket->created_at?->copy()->setTimezone($timezone)),
            'preparation_started_at' => $preparationStartedAt?->toISOString(),
            'ready_at' => $readyAt?->toISOString(),
            'timer_phase' => $phase,
            'timer_known' => $timerStart !== null && (! $terminal || $terminalAt !== null),
            'timer_running' => ! $terminal && $timerStart !== null,
            ...$timer,
            'items' => $items,
            'item_count' => count($items),
            'full_item_count' => $fullCount,
            'full_portion_count' => (int) $ticket->getAttribute('full_portion_count'),
            'matching_item_count' => (int) $ticket->getAttribute('matching_item_count'),
            'is_partial' => count($items) < $fullCount,
            'item_page' => $itemPage,
            'has_previous_item_page' => $itemPage > 1,
            'has_next_item_page' => $itemPage * 100 < (int) $ticket->getAttribute('matching_item_count'),
            'is_terminal' => $terminal,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function itemPayload(KitchenTicketItem $item, bool $canMutate, KitchenTicket $ticket, string $timezone): array
    {
        $orderCancelled = $ticket->order->status === OrderStatus::Cancelled;
        $status = $orderCancelled ? KitchenTicketItemStatus::Cancelled : $this->itemStatus($item->status);
        $isCompleted = ! $orderCancelled && $item->served_at !== null;
        $notificationPending = $item->getAttribute('notification_delivery_state') === 'pending';
        $transitions = $canMutate && ! $isCompleted ? $status->productionTransitions() : [];
        $transitionPayloads = array_map(fn (KitchenTicketItemStatus $next): array => [
            'value' => $next->value,
            'label' => __(match ($next) {
                KitchenTicketItemStatus::Accepted => 'ui.departments.dashboard.accept',
                KitchenTicketItemStatus::InProgress => 'ui.departments.dashboard.start_preparing',
                default => 'ui.departments.dashboard.mark_ready',
            }),
        ], $transitions);

        return [
            'id' => $item->id,
            'kitchen_ticket_id' => $item->kitchen_ticket_id,
            'order_item_id' => $item->order_item_id,
            'version' => (string) $item->getRawOriginal('updated_at'),
            'variant_name' => $item->orderItem?->variant_name,
            'notification_delivery_pending' => $notificationPending,
            'can_retry_notification' => $notificationPending && $canMutate && ! $isCompleted
                && in_array($status, [KitchenTicketItemStatus::InProgress, KitchenTicketItemStatus::Ready], true),
            'primary_transition' => $transitionPayloads[0] ?? null,
            'secondary_transitions' => array_slice($transitionPayloads, 1),
            'item_name' => $item->item_name,
            'quantity' => $item->quantity,
            'status_value' => $isCompleted ? DepartmentTicketFilter::Completed->value : $status->value,
            'status_key' => $isCompleted ? 'ui.departments.dashboard.completed' : $status->translationKey(),
            'status_label' => $isCompleted ? __('ui.departments.dashboard.completed') : $status->label(),
            'status_color' => $isCompleted ? 'zinc' : $status->badgeColor(),
            'can_accept' => $canMutate && ! $isCompleted && $status === KitchenTicketItemStatus::New,
            'can_start' => $canMutate && ! $isCompleted && $status === KitchenTicketItemStatus::Accepted,
            'can_mark_ready' => $canMutate && ! $isCompleted && $status === KitchenTicketItemStatus::InProgress,
            'comment' => $item->comment,
            'modifiers' => $this->modifierSummary($item->selected_modifiers ?? []),
            'allergens' => $this->allergenSummary($item->allergens_snapshot ?? []),
            'completed_at' => LocalizedDateFormatter::dateTime($item->served_at?->copy()->setTimezone($timezone)),
            'cancellation_reason' => $item->orderItem->cancellation_reason ?? ($orderCancelled ? $ticket->order->metadata['cancellation_reason'] ?? null : null),
        ];
    }

    /**
     * @return array{value: string, label: string, color: string}
     */
    private function workStatusPayload(KitchenTicket $ticket): array
    {
        if ($ticket->order->status === OrderStatus::Cancelled) {
            return ['value' => KitchenTicketItemStatus::Cancelled->value, 'label' => KitchenTicketItemStatus::Cancelled->label(), 'color' => KitchenTicketItemStatus::Cancelled->badgeColor()];
        }
        $total = (int) $ticket->getAttribute('full_item_count');
        $cancelled = (int) $ticket->getAttribute('full_cancelled_count');
        $active = $total - $cancelled;
        if ($active > 0 && (int) $ticket->getAttribute('full_served_count') === $active) {
            return ['value' => DepartmentTicketFilter::Completed->value, 'label' => __('ui.departments.dashboard.completed'), 'color' => 'zinc'];
        }
        $status = match (true) {
            $total > 0 && $cancelled === $total => KitchenTicketItemStatus::Cancelled,
            $active > 0 && (int) $ticket->getAttribute('full_ready_count') === $active => KitchenTicketItemStatus::Ready,
            (int) $ticket->getAttribute('full_in_progress_count') > 0 => KitchenTicketItemStatus::InProgress,
            (int) $ticket->getAttribute('full_accepted_count') > 0 => KitchenTicketItemStatus::Accepted,
            default => KitchenTicketItemStatus::New,
        };

        return ['value' => $status->value, 'label' => $status->label(), 'color' => $status->badgeColor()];
    }

    private function itemStatus(mixed $status): KitchenTicketItemStatus
    {
        if ($status instanceof KitchenTicketItemStatus) {
            return $status;
        }

        return KitchenTicketItemStatus::tryFrom((string) $status) ?? KitchenTicketItemStatus::New;
    }

    private function servicePointLabel(string $displayNumber, string $name): string
    {
        if ($displayNumber !== '' && $name !== '') {
            return $displayNumber.' · '.$name;
        }

        if ($displayNumber !== '') {
            return $displayNumber;
        }

        return $name === '' ? __('guest.table.service_point') : $name;
    }

    /**
     * @param  list<array<string, mixed>>  $selectedModifiers
     * @return list<array{label: string}>
     */
    private function modifierSummary(array $selectedModifiers): array
    {
        return collect($selectedModifiers)
            ->map(function (array $modifier): array {
                $groupName = (string) ($modifier['group_name'] ?? $modifier['group'] ?? '');
                $optionName = (string) ($modifier['option_name'] ?? $modifier['option'] ?? '');

                return [
                    'label' => trim($groupName) === '' ? $optionName : $groupName.': '.$optionName,
                ];
            })
            ->filter(fn (array $modifier): bool => trim($modifier['label']) !== '')
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $allergens
     * @return list<array{value: string, label: string}>
     */
    private function allergenSummary(array $allergens): array
    {
        return collect($allergens)
            ->map(fn (string $allergen): ?MenuAllergen => MenuAllergen::tryFrom($allergen))
            ->filter(fn (?MenuAllergen $allergen): bool => $allergen instanceof MenuAllergen)
            ->map(fn (MenuAllergen $allergen): array => [
                'value' => $allergen->value,
                'label' => $allergen->label(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  EloquentCollection<int, KitchenDepartment>  $departments
     * @return array{routing_issue_count: int, routing_issues: list<array<string, mixed>>}
     */
    private function routingIssues(User $user, ?int $branchId, EloquentCollection $departments): array
    {
        $empty = ['routing_issue_count' => 0, 'routing_issues' => []];
        if ($branchId === null) {
            return $empty;
        }
        $branch = Branch::query()->select(['id', 'organization_id', 'deleted_at'])->whereKey($branchId)->first();
        if ($branch === null || Gate::forUser($user)->denies('manageMenu', $branch)) {
            return $empty;
        }
        $allowedTypes = $departments->where('branch_id', $branchId)->map(fn (KitchenDepartment $department): string => $department->type->value)->unique()->all();
        if ($allowedTypes === []) {
            return $empty;
        }
        $query = KitchenTicket::query()->select(['id', 'order_id', 'department_name', 'department_type', 'sent_at'])
            ->where('branch_id', $branchId)
            ->whereIn('department_type', $allowedTypes)
            ->where('status', KitchenTicketStatus::Sent->value)
            ->whereDoesntHave('kitchenDepartment')
            ->whereHas('order', fn ($orderQuery) => $orderQuery->where('status', '!=', OrderStatus::Cancelled->value))
            ->whereHas('items', fn ($itemQuery) => $itemQuery->whereNull('served_at')->where('status', '!=', KitchenTicketItemStatus::Cancelled->value));

        return [
            'routing_issue_count' => (clone $query)->count(),
            'routing_issues' => $query->orderBy('sent_at')->orderBy('id')->limit(10)->get()
                ->map(fn (KitchenTicket $ticket): array => [
                    'ticket_id' => $ticket->id,
                    'order_id' => $ticket->order_id,
                    'department_name' => $ticket->department_name,
                    'department_type_label' => KitchenDepartmentType::tryFrom($ticket->department_type)?->label() ?? $ticket->department_type,
                ])->all(),
        ];
    }

    private function dateAttribute(KitchenTicket $ticket, string $attribute): ?CarbonInterface
    {
        $value = $ticket->getAttribute($attribute);

        return $value === null ? null : CarbonImmutable::parse($value);
    }

    /** @return Builder<OrderStatusLog> */
    private function ticketEventTime(KitchenTicketItemStatus $status): Builder
    {
        return OrderStatusLog::query()->select('occurred_at')
            ->whereColumn('order_id', 'kitchen_tickets.order_id')
            ->whereColumn('metadata->kitchen_ticket_id', 'kitchen_tickets.id')
            ->where('event', OrderStatusLogEvent::TicketItemStatusChanged->value)
            ->where('new_status', $status->value)
            ->when($status === KitchenTicketItemStatus::Ready, fn ($query) => $query
                ->whereIn('metadata->kitchen_ticket_item_id', KitchenTicketItem::query()->select('id')
                    ->whereColumn('kitchen_ticket_id', 'kitchen_tickets.id')
                    ->where('status', KitchenTicketItemStatus::Ready->value)->whereNull('served_at')))
            ->orderBy('occurred_at')->orderBy('id')->limit(1);
    }
}
