<?php

declare(strict_types=1);

namespace App\Actions\Departments;

use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\MenuAllergen;
use App\Enums\OrderStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\User;
use App\Support\LocalizedDateFormatter;
use App\Support\MoneyFormatter;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class BuildDepartmentTicketPrintAction
{
    public function __construct(
        private readonly ResolveAccessibleDepartmentIdsAction $resolveAccessibleDepartmentIds,
    ) {}

    /**
     * @return array{
     *     ticket: array<string, mixed>,
     *     branch: array<string, mixed>,
     *     service_point: array<string, mixed>,
     *     department: array<string, mixed>,
     *     items: list<array<string, mixed>>
     * }
     */
    public function handle(User $user, KitchenTicket $ticket): array
    {
        $ticket = $this->ticket($ticket);

        if (! $this->canPrint($user, $ticket)) {
            abort(403);
        }

        $timezone = $ticket->branch->timezone ?: config('app.timezone');
        $servicePoint = $ticket->tableSession->servicePoint ?? $ticket->servicePoint;
        $items = $ticket->items
            ->map(fn (KitchenTicketItem $item): array => $this->itemPayload($item, $ticket, $timezone))
            ->values()
            ->all();

        return [
            'ticket' => [
                'id' => $ticket->id,
                'order_id' => $ticket->order_id,
                'order_number' => '#'.$ticket->order_id,
                'status_key' => 'statuses.kitchen_ticket.sent',
                'sent_at' => $this->formatTime($ticket->sent_at ?? $ticket->created_at, $timezone),
                'printed_at' => $this->formatTime(now(), $timezone),
                'timezone' => $timezone,
            ],
            'branch' => [
                'id' => $ticket->branch_id,
                'name' => $ticket->branch->name,
                'city' => $ticket->branch->city,
                'country' => $ticket->branch->country,
            ],
            'service_point' => [
                'id' => $servicePoint?->id,
                'original_id' => $ticket->service_point_id,
                'original_label' => $this->servicePointLabel((string) ($ticket->servicePoint->display_number ?? ''), (string) ($ticket->servicePoint->name ?? '')),
                'name' => $servicePoint?->name,
                'display_number' => $servicePoint?->display_number,
                'label' => $this->servicePointLabel(
                    (string) ($servicePoint->display_number ?? ''),
                    (string) $servicePoint?->name,
                ),
                'zone_name' => $servicePoint?->area_node_id === null
                    ? null
                    : $servicePoint->areaNode?->name,
            ],
            'department' => [
                'id' => $ticket->kitchen_department_id,
                'name' => $ticket->department_name,
                'type' => $ticket->department_type,
                'type_label' => KitchenDepartmentType::tryFrom($ticket->department_type)?->label() ?? $ticket->department_type,
            ],
            'items' => $items,
        ];
    }

    public function canPrint(User $user, KitchenTicket $ticket): bool
    {
        $departmentId = $ticket->kitchen_department_id;

        if ($departmentId === null) {
            return false;
        }

        return $this->accessibleDepartmentIds($user)->contains((int) $departmentId);
    }

    private function ticket(KitchenTicket $ticket): KitchenTicket
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
                'order:id,status,metadata',
                'branch' => fn ($query) => $query->select([
                    'id',
                    'organization_id',
                    'brand_id',
                    'name',
                    'city',
                    'country',
                    'timezone',
                ]),
                'tableSession' => fn ($query) => $query->select(['id', 'service_point_id'])->with([
                    'servicePoint' => fn ($pointQuery) => $pointQuery->withTrashed()
                        ->select(['id', 'branch_id', 'area_node_id', 'name', 'display_number', 'status'])
                        ->with(['areaNode:id,branch_id,name']),
                ]),
                'servicePoint' => fn ($query) => $query->withTrashed()
                    ->select(['id', 'branch_id', 'area_node_id', 'name', 'display_number', 'status'])
                    ->with(['areaNode' => fn ($areaQuery) => $areaQuery->select(['id', 'branch_id', 'name'])]),
                'kitchenDepartment' => fn ($query) => $query->select([
                    'id',
                    'branch_id',
                    'type',
                    'name',
                    'sort_order',
                    'is_active',
                ]),
                'items' => fn ($query) => $query
                    ->select([
                        'id',
                        'kitchen_ticket_id',
                        'order_item_id',
                        'table_session_guest_id',
                        'menu_item_id',
                        'item_name',
                        'quantity',
                        'status',
                        'selected_modifiers',
                        'allergens_snapshot',
                        'comment',
                        'served_at',
                        'created_at',
                    ])
                    ->with(['orderItem:id,variant_name,cancellation_reason,cancelled_at'])
                    ->orderBy('created_at')
                    ->orderBy('id'),
            ])
            ->whereKey($ticket->id)
            ->firstOrFail();
    }

    /**
     * @return Collection<int, int>
     */
    private function accessibleDepartmentIds(User $user): Collection
    {
        $kitchenIds = $this->resolveAccessibleDepartmentIds->handle(
            user: $user,
            departmentTypes: KitchenDepartmentType::kitchenProductionTypes(),
            roleCodes: [SystemRole::HeadChef, SystemRole::Cook],
            permissionCodes: [SystemPermission::ViewKitchen],
        );
        $barIds = $this->resolveAccessibleDepartmentIds->handle(
            user: $user,
            departmentTypes: KitchenDepartmentType::barProductionTypes(),
            roleCodes: [SystemRole::Bartender, SystemRole::HeadChef],
            permissionCodes: [SystemPermission::ViewOrders, SystemPermission::SendToKitchen],
        );

        return $kitchenIds
            ->merge($barIds)
            ->unique()
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function itemPayload(KitchenTicketItem $item, KitchenTicket $ticket, string $timezone): array
    {
        $orderCancelled = $ticket->order->status === OrderStatus::Cancelled;
        $status = $orderCancelled ? KitchenTicketItemStatus::Cancelled : $this->itemStatus($item->status);
        $allergens = $this->allergenSummary($item->allergens_snapshot ?? []);

        return [
            'id' => $item->id,
            'item_name' => $item->item_name,
            'variant_name' => $item->orderItem?->variant_name,
            'cancellation_reason' => $item->orderItem->cancellation_reason ?? ($orderCancelled ? $ticket->order->metadata['cancellation_reason'] ?? null : null),
            'served_at' => $this->formatTime($item->served_at, $timezone),
            'quantity' => $item->quantity,
            'status_key' => ! $orderCancelled && $item->served_at !== null ? 'ui.departments.dashboard.completed' : match ($status) {
                KitchenTicketItemStatus::New => 'statuses.kitchen_ticket_item.new',
                KitchenTicketItemStatus::Accepted => 'statuses.kitchen_ticket_item.accepted',
                KitchenTicketItemStatus::InProgress => 'statuses.kitchen_ticket_item.in_progress',
                KitchenTicketItemStatus::Ready => 'statuses.kitchen_ticket_item.ready',
                KitchenTicketItemStatus::Cancelled => 'statuses.kitchen_ticket_item.cancelled',
            },
            'selected_modifiers' => $this->modifierSummary($item->selected_modifiers ?? []),
            'allergens' => $allergens,
            'allergens_label' => implode(', ', $allergens),
            'comment' => $item->comment,
        ];
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
     * @return list<string>
     */
    private function allergenSummary(array $allergens): array
    {
        return collect($allergens)
            ->map(fn (string $allergen): ?MenuAllergen => MenuAllergen::tryFrom($allergen))
            ->filter(fn (?MenuAllergen $allergen): bool => $allergen instanceof MenuAllergen)
            ->map(fn (MenuAllergen $allergen): string => $allergen->label())
            ->values()
            ->all();
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

    private function formatTime(?CarbonInterface $time, string $timezone): ?string
    {
        return LocalizedDateFormatter::dateTime($time?->copy()->timezone($timezone));
    }

    private function itemStatus(mixed $status): KitchenTicketItemStatus
    {
        if ($status instanceof KitchenTicketItemStatus) {
            return $status;
        }

        return KitchenTicketItemStatus::tryFrom((string) $status) ?? KitchenTicketItemStatus::New;
    }
}
