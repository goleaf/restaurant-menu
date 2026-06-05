<?php

namespace App\Actions\Departments;

use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\KitchenTicketStatus;
use App\Enums\OrderStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class BuildDepartmentDashboardAction
{
    public function __construct(
        private readonly ResolveAccessibleDepartmentIdsAction $resolveAccessibleDepartmentIds,
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
     *     in_progress_item_count: int,
     *     ready_item_count: int
     * }
     */
    public function handle(
        User $user,
        ?int $selectedDepartmentId,
        array $departmentTypes,
        array $roleCodes,
        array $permissionCodes,
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

        $tickets = $this->ticketsFor($selectedDepartment);
        $itemStatuses = $tickets->flatMap(fn (KitchenTicket $ticket): Collection => $ticket->items->pluck('status'));

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
            'ticket_count' => $tickets->count(),
            'new_item_count' => $itemStatuses->filter(fn (mixed $status): bool => $this->itemStatus($status) === KitchenTicketItemStatus::New)->count(),
            'in_progress_item_count' => $itemStatuses->filter(fn (mixed $status): bool => $this->itemStatus($status) === KitchenTicketItemStatus::InProgress)->count(),
            'ready_item_count' => $itemStatuses->filter(fn (mixed $status): bool => $this->itemStatus($status) === KitchenTicketItemStatus::Ready)->count(),
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
            'in_progress_item_count' => 0,
            'ready_item_count' => 0,
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

        return $departments->first();
    }

    /**
     * @return EloquentCollection<int, KitchenTicket>
     */
    private function ticketsFor(KitchenDepartment $department): EloquentCollection
    {
        return KitchenTicket::query()
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
                'servicePoint' => fn ($query) => $query
                    ->select(['id', 'branch_id', 'area_node_id', 'name', 'display_number', 'status'])
                    ->with(['areaNode' => fn ($areaQuery) => $areaQuery->select(['id', 'branch_id', 'name'])]),
                'items' => fn ($query) => $query
                    ->select([
                        'id',
                        'kitchen_ticket_id',
                        'order_item_id',
                        'table_session_guest_id',
                        'menu_item_id',
                        'guest_name',
                        'item_name',
                        'quantity',
                        'status',
                        'selected_modifiers',
                        'comment',
                        'created_at',
                        'updated_at',
                    ])
                    ->orderBy('created_at')
                    ->orderBy('id'),
            ])
            ->where('kitchen_department_id', $department->id)
            ->where('status', KitchenTicketStatus::Sent->value)
            ->whereHas('order', function ($query): void {
                $query->where('status', '!=', OrderStatus::Cancelled->value);
            })
            ->orderBy('sent_at')
            ->orderBy('id')
            ->limit(100)
            ->get();
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
            'branch_name' => $department->branch?->name,
            'brand_name' => $department->branch?->brand?->name,
            'organization_name' => $department->branch?->organization?->name,
            'label' => trim(($department->branch?->name ?? '').' / '.$department->name, ' /'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketPayload(KitchenTicket $ticket): array
    {
        $status = $ticket->status instanceof KitchenTicketStatus
            ? $ticket->status
            : KitchenTicketStatus::from((string) $ticket->status);
        $items = $ticket->items
            ->map(fn (KitchenTicketItem $item): array => $this->itemPayload($item))
            ->values()
            ->all();
        $displayNumber = trim((string) ($ticket->servicePoint?->display_number ?? ''));
        $servicePointName = trim((string) ($ticket->servicePoint?->name ?? ''));
        $startedAt = $ticket->sent_at ?? $ticket->created_at;
        $elapsedSeconds = $this->elapsedSeconds($startedAt);

        return [
            'id' => $ticket->id,
            'order_id' => $ticket->order_id,
            'service_point_name' => $servicePointName === '' ? __('Service point') : $servicePointName,
            'service_point_display_number' => $displayNumber,
            'service_point_label' => $this->servicePointLabel($displayNumber, $servicePointName),
            'zone_name' => $ticket->servicePoint?->areaNode?->name,
            'status_value' => $status->value,
            'status_label' => $status->label(),
            'work_status' => $this->workStatusPayload($ticket->items),
            'sent_at' => $startedAt?->format('Y-m-d H:i'),
            'created_time' => $ticket->created_at?->format('H:i'),
            'elapsed_seconds' => $elapsedSeconds,
            'elapsed_label' => $this->formatDuration($elapsedSeconds),
            'timer_tone' => $this->timerTone($elapsedSeconds),
            'items' => $items,
            'item_count' => count($items),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function itemPayload(KitchenTicketItem $item): array
    {
        $status = $this->itemStatus($item->status);

        return [
            'id' => $item->id,
            'guest_name' => $item->guest_name,
            'item_name' => $item->item_name,
            'quantity' => $item->quantity,
            'status_value' => $status->value,
            'status_label' => $status->label(),
            'status_color' => $status->badgeColor(),
            'can_start' => $status === KitchenTicketItemStatus::New,
            'can_mark_ready' => $status !== KitchenTicketItemStatus::Ready,
            'comment' => $item->comment,
            'modifiers' => $this->modifierSummary($item->selected_modifiers ?? []),
        ];
    }

    /**
     * @param  Collection<int, KitchenTicketItem>  $items
     * @return array{value: string, label: string, color: string}
     */
    private function workStatusPayload(Collection $items): array
    {
        if ($items->isNotEmpty() && $items->every(fn (KitchenTicketItem $item): bool => $this->itemStatus($item->status) === KitchenTicketItemStatus::Ready)) {
            return [
                'value' => KitchenTicketItemStatus::Ready->value,
                'label' => KitchenTicketItemStatus::Ready->label(),
                'color' => KitchenTicketItemStatus::Ready->badgeColor(),
            ];
        }

        if ($items->contains(fn (KitchenTicketItem $item): bool => $this->itemStatus($item->status) === KitchenTicketItemStatus::InProgress)) {
            return [
                'value' => KitchenTicketItemStatus::InProgress->value,
                'label' => KitchenTicketItemStatus::InProgress->label(),
                'color' => KitchenTicketItemStatus::InProgress->badgeColor(),
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

        return $name === '' ? __('Service point') : $name;
    }

    private function elapsedSeconds(?CarbonInterface $startedAt): int
    {
        if (! $startedAt instanceof CarbonInterface) {
            return 0;
        }

        return max(0, (int) $startedAt->diffInSeconds(now()));
    }

    private function formatDuration(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        if ($hours > 0) {
            return $hours.':'.str_pad((string) $minutes, 2, '0', STR_PAD_LEFT).':'.str_pad((string) $remainingSeconds, 2, '0', STR_PAD_LEFT);
        }

        return str_pad((string) $minutes, 2, '0', STR_PAD_LEFT).':'.str_pad((string) $remainingSeconds, 2, '0', STR_PAD_LEFT);
    }

    private function timerTone(int $seconds): string
    {
        if ($seconds >= 900) {
            return 'rose';
        }

        if ($seconds >= 600) {
            return 'amber';
        }

        return 'emerald';
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
                $priceDelta = $modifier['price_delta'] ?? null;

                return [
                    'label' => trim($groupName) === '' ? $optionName : $groupName.': '.$optionName,
                    'price_delta' => $priceDelta === null ? null : (string) $priceDelta,
                ];
            })
            ->filter(fn (array $modifier): bool => trim($modifier['label']) !== '')
            ->values()
            ->all();
    }
}
