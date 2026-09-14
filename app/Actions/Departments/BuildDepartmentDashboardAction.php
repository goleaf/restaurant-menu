<?php

declare(strict_types=1);

namespace App\Actions\Departments;

use App\Enums\DepartmentTicketFilter;
use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\KitchenTicketStatus;
use App\Enums\MenuAllergen;
use App\Enums\OrderStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\User;
use App\Support\LocalizedDateFormatter;
use App\Support\MoneyFormatter;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
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
     * @return array{
     *     has_access: bool,
     *     departments: list<array<string, mixed>>,
     *     selected_department_id: int|null,
     *     selected_department_name: string|null,
     *     tickets: list<array<string, mixed>>,
     *     ticket_count: int,
     *     new_item_count: int,
     *     accepted_item_count: int,
     *     in_progress_item_count: int,
     *     ready_item_count: int,
     *     completed_item_count: int,
     *     cancelled_item_count: int,
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
    ): array {
        $departmentIds = $this->resolveAccessibleDepartmentIds->handle($user, $departmentTypes, $roleCodes, $permissionCodes);

        if ($departmentIds->isEmpty()) {
            return $this->emptyPayload(false);
        }

        $departments = $this->departments($departmentIds, $departmentTypes);
        $selectedDepartment = $this->selectedDepartment($departments, $selectedDepartmentId);

        if (! $selectedDepartment instanceof KitchenDepartment) {
            return $this->emptyPayload(false);
        }

        $page = max(1, $page);
        $perPage = (int) Number::clamp($perPage, 1, 50);
        $ticketPage = $this->ticketsFor($selectedDepartment, $filter, $page, $perPage);
        $tickets = $ticketPage['tickets'];
        $itemCounts = $this->itemCountsFor($selectedDepartment);

        return [
            'has_access' => true,
            'departments' => $departments
                ->map(fn (KitchenDepartment $department): array => $this->departmentPayload($department))
                ->values()
                ->all(),
            'selected_department_id' => $selectedDepartment->id,
            'selected_department_name' => $selectedDepartment->name,
            'tickets' => $tickets
                ->map(fn (KitchenTicket $ticket): array => $this->ticketPayload($ticket))
                ->values()
                ->all(),
            'ticket_count' => $ticketPage['total'],
            ...$itemCounts,
            'ticket_page' => $page,
            'has_previous_ticket_page' => $page > 1,
            'has_next_ticket_page' => $page * $perPage < $ticketPage['total'],
            'sort_label' => $filter->isHistory()
                ? __('ui.departments.dashboard.newest_first')
                : __('ui.departments.dashboard.oldest_first'),
        ];
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
            'selected_department_id' => null,
            'selected_department_name' => null,
            'tickets' => [],
            'ticket_count' => 0,
            'new_item_count' => 0,
            'accepted_item_count' => 0,
            'in_progress_item_count' => 0,
            'ready_item_count' => 0,
            'completed_item_count' => 0,
            'cancelled_item_count' => 0,
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
            ->where('is_active', true)
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
     * @return array{tickets: EloquentCollection<int, KitchenTicket>, total: int}
     */
    private function ticketsFor(
        KitchenDepartment $department,
        DepartmentTicketFilter $filter,
        int $page,
        int $perPage,
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
                'order' => fn ($query) => $query->select(['id', 'status']),
                'servicePoint' => fn ($query) => $query
                    ->select(['id', 'branch_id', 'area_node_id', 'name', 'display_number', 'status'])
                    ->with(['areaNode' => fn ($areaQuery) => $areaQuery->select(['id', 'branch_id', 'name'])]),
            ])
            ->where('kitchen_department_id', $department->id)
            ->where('status', KitchenTicketStatus::Sent->value)
            ->withWhereHas('items', function ($query) use ($filter): void {
                $query->select([
                    'id',
                    'kitchen_ticket_id',
                    'order_item_id',
                    'table_session_guest_id',
                    'menu_item_id',
                    'guest_name',
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
                    ->with(['orderItem' => fn ($orderItemQuery) => $orderItemQuery->select([
                        'id',
                        'cancellation_reason',
                    ])]);
                $this->applyItemFilter($query, $filter);
                $query
                    ->orderBy('created_at')
                    ->orderBy('id');
            });

        if (! $filter->isHistory()) {
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

        return [
            'tickets' => $tickets,
            'total' => $total,
        ];
    }

    private function applyItemFilter(Builder|Relation $query, DepartmentTicketFilter $filter): void
    {
        match ($filter) {
            DepartmentTicketFilter::Active => $query
                ->whereIn('status', [
                    KitchenTicketItemStatus::New->value,
                    KitchenTicketItemStatus::Accepted->value,
                    KitchenTicketItemStatus::InProgress->value,
                    KitchenTicketItemStatus::Ready->value,
                ])
                ->whereNull('served_at'),
            DepartmentTicketFilter::New => $query
                ->where('status', KitchenTicketItemStatus::New->value)
                ->whereNull('served_at'),
            DepartmentTicketFilter::Accepted => $query
                ->where('status', KitchenTicketItemStatus::Accepted->value)
                ->whereNull('served_at'),
            DepartmentTicketFilter::InProgress => $query
                ->where('status', KitchenTicketItemStatus::InProgress->value)
                ->whereNull('served_at'),
            DepartmentTicketFilter::Ready => $query
                ->where('status', KitchenTicketItemStatus::Ready->value)
                ->whereNull('served_at'),
            DepartmentTicketFilter::Completed => $query
                ->where('status', KitchenTicketItemStatus::Ready->value)
                ->whereNotNull('served_at'),
            DepartmentTicketFilter::Cancelled => $query
                ->where('status', KitchenTicketItemStatus::Cancelled->value),
        };
    }

    /**
     * @return array{
     *     new_item_count: int,
     *     accepted_item_count: int,
     *     in_progress_item_count: int,
     *     ready_item_count: int,
     *     completed_item_count: int,
     *     cancelled_item_count: int
     * }
     */
    private function itemCountsFor(KitchenDepartment $department): array
    {
        $department = KitchenDepartment::query()
            ->select(['id'])
            ->withCount([
                'ticketItems as new_item_count' => fn ($query) => $query
                    ->where('kitchen_ticket_items.status', KitchenTicketItemStatus::New->value)
                    ->whereNull('kitchen_ticket_items.served_at'),
                'ticketItems as accepted_item_count' => fn ($query) => $query
                    ->where('kitchen_ticket_items.status', KitchenTicketItemStatus::Accepted->value)
                    ->whereNull('kitchen_ticket_items.served_at'),
                'ticketItems as in_progress_item_count' => fn ($query) => $query
                    ->where('kitchen_ticket_items.status', KitchenTicketItemStatus::InProgress->value)
                    ->whereNull('kitchen_ticket_items.served_at'),
                'ticketItems as ready_item_count' => fn ($query) => $query
                    ->where('kitchen_ticket_items.status', KitchenTicketItemStatus::Ready->value)
                    ->whereNull('kitchen_ticket_items.served_at'),
                'ticketItems as completed_item_count' => fn ($query) => $query
                    ->where('kitchen_ticket_items.status', KitchenTicketItemStatus::Ready->value)
                    ->whereNotNull('kitchen_ticket_items.served_at'),
                'ticketItems as cancelled_item_count' => fn ($query) => $query
                    ->where('kitchen_ticket_items.status', KitchenTicketItemStatus::Cancelled->value),
            ])
            ->whereKey($department->id)
            ->firstOrFail();

        return [
            'new_item_count' => (int) $department->getAttribute('new_item_count'),
            'accepted_item_count' => (int) $department->getAttribute('accepted_item_count'),
            'in_progress_item_count' => (int) $department->getAttribute('in_progress_item_count'),
            'ready_item_count' => (int) $department->getAttribute('ready_item_count'),
            'completed_item_count' => (int) $department->getAttribute('completed_item_count'),
            'cancelled_item_count' => (int) $department->getAttribute('cancelled_item_count'),
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
            'branch_name' => $department->branch->name,
            'brand_name' => $department->branch->brand->name,
            'organization_name' => $department->branch->organization->name,
            'label' => $department->branch->name.' / '.$department->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketPayload(KitchenTicket $ticket): array
    {
        $status = $ticket->status;
        $orderStatus = $ticket->order->status;
        $items = $ticket->items
            ->map(fn (KitchenTicketItem $item): array => $this->itemPayload($item))
            ->values()
            ->all();
        $displayNumber = trim((string) ($ticket->servicePoint->display_number ?? ''));
        $servicePointName = trim((string) $ticket->servicePoint->name);
        $startedAt = $ticket->sent_at ?? $ticket->created_at;
        $terminalAt = $this->terminalAt($ticket->items);

        return [
            'id' => $ticket->id,
            'order_id' => $ticket->order_id,
            'service_point_name' => $servicePointName === '' ? __('guest.table.service_point') : $servicePointName,
            'service_point_display_number' => $displayNumber,
            'service_point_label' => $this->servicePointLabel($displayNumber, $servicePointName),
            'zone_name' => $ticket->servicePoint?->areaNode?->name,
            'status_value' => $status->value,
            'status_label' => $status->label(),
            'order_status_value' => $orderStatus->value,
            'order_status_label' => $orderStatus->label(),
            'work_status' => $this->workStatusPayload($ticket->items),
            'sent_at' => LocalizedDateFormatter::dateTime($startedAt),
            'created_time' => LocalizedDateFormatter::time($ticket->created_at),
            ...$this->buildDepartmentTicketDelayTimer->handle($startedAt, $terminalAt),
            'items' => $items,
            'item_count' => count($items),
            'is_terminal' => $terminalAt !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function itemPayload(KitchenTicketItem $item): array
    {
        $status = $this->itemStatus($item->status);
        $isCompleted = $item->served_at !== null;

        return [
            'id' => $item->id,
            'guest_name' => $item->guest_name,
            'item_name' => $item->item_name,
            'quantity' => $item->quantity,
            'status_value' => $isCompleted ? DepartmentTicketFilter::Completed->value : $status->value,
            'status_key' => $isCompleted ? 'ui.departments.dashboard.completed' : $status->translationKey(),
            'status_label' => $isCompleted ? __('ui.departments.dashboard.completed') : $status->label(),
            'status_color' => $isCompleted ? 'zinc' : $status->badgeColor(),
            'can_accept' => ! $isCompleted && $status === KitchenTicketItemStatus::New,
            'can_start' => ! $isCompleted && $status === KitchenTicketItemStatus::Accepted,
            'can_mark_ready' => ! $isCompleted && $status === KitchenTicketItemStatus::InProgress,
            'comment' => $item->comment,
            'modifiers' => $this->modifierSummary($item->selected_modifiers ?? []),
            'allergens' => $this->allergenSummary($item->allergens_snapshot ?? []),
            'completed_at' => LocalizedDateFormatter::dateTime($item->served_at),
            'cancellation_reason' => $item->orderItem?->cancellation_reason,
        ];
    }

    /**
     * @param  Collection<int, KitchenTicketItem>  $items
     * @return array{value: string, label: string, color: string}
     */
    private function workStatusPayload(Collection $items): array
    {
        if ($items->isNotEmpty() && $items->every(fn (KitchenTicketItem $item): bool => $this->itemStatus($item->status) === KitchenTicketItemStatus::Ready)) {
            if ($items->every(fn (KitchenTicketItem $item): bool => $item->served_at !== null)) {
                return [
                    'value' => DepartmentTicketFilter::Completed->value,
                    'label' => __('ui.departments.dashboard.completed'),
                    'color' => 'zinc',
                ];
            }

            return [
                'value' => KitchenTicketItemStatus::Ready->value,
                'label' => KitchenTicketItemStatus::Ready->label(),
                'color' => KitchenTicketItemStatus::Ready->badgeColor(),
            ];
        }

        if ($items->isNotEmpty() && $items->every(fn (KitchenTicketItem $item): bool => $this->itemStatus($item->status) === KitchenTicketItemStatus::Cancelled)) {
            return [
                'value' => KitchenTicketItemStatus::Cancelled->value,
                'label' => KitchenTicketItemStatus::Cancelled->label(),
                'color' => KitchenTicketItemStatus::Cancelled->badgeColor(),
            ];
        }

        if ($items->contains(fn (KitchenTicketItem $item): bool => $this->itemStatus($item->status) === KitchenTicketItemStatus::InProgress)) {
            return [
                'value' => KitchenTicketItemStatus::InProgress->value,
                'label' => KitchenTicketItemStatus::InProgress->label(),
                'color' => KitchenTicketItemStatus::InProgress->badgeColor(),
            ];
        }

        if ($items->contains(fn (KitchenTicketItem $item): bool => $this->itemStatus($item->status) === KitchenTicketItemStatus::Accepted)) {
            return [
                'value' => KitchenTicketItemStatus::Accepted->value,
                'label' => KitchenTicketItemStatus::Accepted->label(),
                'color' => KitchenTicketItemStatus::Accepted->badgeColor(),
            ];
        }

        return [
            'value' => KitchenTicketItemStatus::New->value,
            'label' => KitchenTicketItemStatus::New->label(),
            'color' => KitchenTicketItemStatus::New->badgeColor(),
        ];
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
     * @return list<array{label: string, price_delta: string|null}>
     */
    private function modifierSummary(array $selectedModifiers): array
    {
        return collect($selectedModifiers)
            ->map(function (array $modifier): array {
                $groupName = (string) ($modifier['group_name'] ?? $modifier['group'] ?? '');
                $optionName = (string) ($modifier['option_name'] ?? $modifier['option'] ?? '');
                $priceDeltaCents = $modifier['price_delta_cents'] ?? null;

                return [
                    'label' => trim($groupName) === '' ? $optionName : $groupName.': '.$optionName,
                    'price_delta' => $priceDeltaCents === null
                        ? null
                        : MoneyFormatter::centsToDecimal((int) $priceDeltaCents),
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
     * @param  Collection<int, KitchenTicketItem>  $items
     */
    private function terminalAt(Collection $items): ?CarbonInterface
    {
        if ($items->isEmpty() || $items->contains(function (KitchenTicketItem $item): bool {
            return $item->served_at === null
                && $this->itemStatus($item->status) !== KitchenTicketItemStatus::Cancelled;
        })) {
            return null;
        }

        $terminalItem = $items
            ->sortByDesc(fn (KitchenTicketItem $item): int => ($item->served_at ?? $item->updated_at)?->getTimestamp() ?? 0)
            ->first();

        return $terminalItem instanceof KitchenTicketItem
            ? $terminalItem->served_at ?? $terminalItem->updated_at
            : null;
    }
}
