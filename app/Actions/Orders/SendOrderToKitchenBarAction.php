<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\KitchenDepartments\SeedKitchenDepartmentsForBranchAction;
use App\Actions\ServicePoints\UpdateServicePointStatusAction;
use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderStatusLogEvent;
use App\Enums\ServicePointStatus;
use App\Models\Branch;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicket;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SendOrderToKitchenBarAction
{
    public function __construct(
        private readonly UpdateServicePointStatusAction $updateServicePointStatus,
        private readonly CreateOrderStatusLogAction $createOrderStatusLog,
        private readonly SeedKitchenDepartmentsForBranchAction $seedKitchenDepartments,
    ) {}

    public function handle(Order $order, User $sentBy): Order
    {
        return DB::transaction(function () use ($order, $sentBy): Order {
            $order = $this->reloadOrder($order);
            $this->ensureCanSend($order, $sentBy);

            if ($order->status === OrderStatus::SentToKitchenBar && $order->kitchenTickets->isNotEmpty()) {
                $this->markServicePointCooking($order);

                return $order;
            }

            $previousStatus = $order->status;
            $tickets = $order->kitchenTickets->isNotEmpty()
                ? $order->kitchenTickets
                : $this->createTickets($order, $sentBy);

            if ($previousStatus === OrderStatus::SentToKitchenBar) {
                $this->markServicePointCooking($order);

                return $this->reloadOrder($order);
            }

            $order
                ->forceFill([
                    'status' => OrderStatus::SentToKitchenBar,
                    'metadata' => $this->updatedOrderMetadata($order, $tickets, $sentBy),
                ])
                ->save();

            $this->markServicePointCooking($order);

            $this->createOrderStatusLog->handle(
                event: OrderStatusLogEvent::OrderSentToKitchenBar,
                order: $order,
                actorUser: $sentBy,
                previousStatus: $previousStatus,
                newStatus: OrderStatus::SentToKitchenBar,
                statusType: 'order',
                metadata: [
                    'source' => 'waiter_dispatch',
                    'tickets_count' => $tickets->count(),
                    'items_count' => $order->items->count(),
                    'departments' => $tickets
                        ->map(fn (KitchenTicket $ticket): string => $ticket->department_name)
                        ->values()
                        ->all(),
                ],
            );

            return $this->reloadOrder($order);
        });
    }

    private function reloadOrder(Order $order): Order
    {
        return Order::query()
            ->select([
                'id',
                'branch_id',
                'service_point_id',
                'table_session_id',
                'draft_order_id',
                'status',
                'confirmed_by_user_id',
                'confirmed_at',
                'total_price_cents',
                'currency',
                'metadata',
            ])
            ->with([
                'branch' => fn ($query) => $query->select(['id', 'organization_id']),
                'servicePoint' => fn ($query) => $query->select(['id', 'status']),
                'items' => fn ($query) => $query
                    ->select([
                        'id',
                        'order_id',
                        'table_session_guest_id',
                        'menu_item_id',
                        'kitchen_department_id',
                        'kitchen_department_type',
                        'kitchen_department_name',
                        'guest_name',
                        'guest_name_snapshot',
                        'item_name',
                        'item_name_snapshot',
                        'variant_name',
                        'quantity',
                        'selected_modifiers',
                        'modifiers_snapshot',
                        'comment',
                        'created_at',
                    ])
                    ->active()
                    ->with(['kitchenDepartment' => fn ($departmentQuery) => $departmentQuery->select(['id', 'branch_id', 'type', 'name'])]),
                'kitchenTickets' => fn ($query) => $query
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
                        'sent_by_user_id',
                        'sent_at',
                        'metadata',
                    ])
                    ->with([
                        'items' => fn ($itemQuery) => $itemQuery->select([
                            'id',
                            'kitchen_ticket_id',
                            'order_item_id',
                            'item_name',
                            'quantity',
                        ]),
                    ]),
            ])
            ->whereKey($order->id)
            ->firstOrFail();
    }

    private function ensureCanSend(Order $order, User $user): void
    {
        if (Gate::forUser($user)->denies('sendToKitchen', $order)) {
            throw ValidationException::withMessages([
                'order_dispatch' => __('ui.actions.orders.sendordertokitchenbaraction.u_vas_net_prava_otpravliat_za'),
            ]);
        }

        if (! in_array($order->status, [OrderStatus::ConfirmedByWaiter, OrderStatus::SentToKitchenBar], true)) {
            throw ValidationException::withMessages([
                'order_dispatch' => __('ui.actions.orders.sendordertokitchenbaraction.na_kuxniu_ili_bar_mozno_otpra'),
            ]);
        }

        if ($order->items->isEmpty()) {
            throw ValidationException::withMessages([
                'order_dispatch' => __('ui.actions.orders.sendordertokitchenbaraction.nelzia_otpravit_pustoi_zakaz'),
            ]);
        }
    }

    /**
     * @return EloquentCollection<int, KitchenTicket>
     */
    private function createTickets(Order $order, User $sentBy): EloquentCollection
    {
        $tickets = new EloquentCollection;

        foreach ($this->departmentGroups($order) as $group) {
            $ticket = new KitchenTicket;
            $ticket->forceFill([
                'order_id' => $order->id,
                'branch_id' => $order->branch_id,
                'service_point_id' => $order->service_point_id,
                'table_session_id' => $order->table_session_id,
                'kitchen_department_id' => $group['kitchen_department_id'],
                'department_type' => $group['department_type'],
                'department_name' => $group['department_name'],
                'status' => KitchenTicketStatus::Sent,
                'sent_by_user_id' => $sentBy->id,
                'sent_at' => now(),
                'metadata' => [
                    'order_item_count' => $group['items']->count(),
                ],
            ])->save();

            $group['items']->each(function (OrderItem $item) use ($ticket): void {
                $ticket->items()->create([
                    'order_item_id' => $item->id,
                    'table_session_guest_id' => $item->table_session_guest_id,
                    'menu_item_id' => $item->menu_item_id,
                    'guest_name' => $item->historicalGuestName(),
                    'item_name' => $this->kitchenItemName($item),
                    'quantity' => $item->quantity,
                    'selected_modifiers' => $item->historicalModifiers(),
                    'comment' => $item->comment,
                ]);
            });

            $tickets->push($ticket->load('items'));
        }

        return $tickets;
    }

    private function kitchenItemName(OrderItem $item): string
    {
        $itemName = $item->historicalItemName();

        return filled($item->variant_name) ? $itemName.' · '.$item->variant_name : $itemName;
    }

    /**
     * @return Collection<int, array{kitchen_department_id: int|null, department_type: string, department_name: string, items: Collection<int, OrderItem>}>
     */
    private function departmentGroups(Order $order): Collection
    {
        $groups = collect();
        $defaultKitchenDepartment = null;

        $order->items->each(function (OrderItem $item) use ($order, $groups, &$defaultKitchenDepartment): void {
            $department = $this->departmentPayloadFor($order, $item, $defaultKitchenDepartment);
            $key = $department['key'];
            $group = $groups->get($key, [
                'kitchen_department_id' => $department['id'],
                'department_type' => $department['type'],
                'department_name' => $department['name'],
                'items' => collect(),
            ]);

            $group['items']->push($item);
            $groups->put($key, $group);
        });

        return $groups->values();
    }

    /**
     * @return array{key: string, id: int|null, type: string, name: string}
     */
    private function departmentPayloadFor(Order $order, OrderItem $item, ?KitchenDepartment &$defaultKitchenDepartment): array
    {
        if (
            is_string($item->kitchen_department_type)
            && trim($item->kitchen_department_type) !== ''
            && is_string($item->kitchen_department_name)
            && trim($item->kitchen_department_name) !== ''
        ) {
            $departmentId = $item->kitchen_department_id === null
                ? null
                : (int) $item->kitchen_department_id;

            return [
                'key' => $departmentId === null
                    ? 'snapshot:'.$item->kitchen_department_type.':'.$item->kitchen_department_name
                    : 'department:'.$departmentId,
                'id' => $departmentId,
                'type' => $item->kitchen_department_type,
                'name' => $item->kitchen_department_name,
            ];
        }

        if ($item->kitchenDepartment instanceof KitchenDepartment) {
            return [
                'key' => 'department:'.$item->kitchenDepartment->id,
                'id' => $item->kitchenDepartment->id,
                'type' => $item->kitchenDepartment->type->value,
                'name' => $item->kitchenDepartment->name,
            ];
        }

        $defaultKitchenDepartment ??= $this->defaultKitchenDepartmentFor($order);

        if ($defaultKitchenDepartment instanceof KitchenDepartment) {
            return [
                'key' => 'department:'.$defaultKitchenDepartment->id,
                'id' => $defaultKitchenDepartment->id,
                'type' => $defaultKitchenDepartment->type->value,
                'name' => $defaultKitchenDepartment->name,
            ];
        }

        return [
            'key' => 'snapshot:'.KitchenDepartmentType::Kitchen->value.':Kitchen',
            'id' => null,
            'type' => KitchenDepartmentType::Kitchen->value,
            'name' => 'Kitchen',
        ];
    }

    private function defaultKitchenDepartmentFor(Order $order): ?KitchenDepartment
    {
        $branch = Branch::query()
            ->select(['id'])
            ->whereKey($order->branch_id)
            ->first();

        if (! $branch instanceof Branch) {
            return null;
        }

        $department = $this->queryDefaultKitchenDepartment($branch);

        if ($department instanceof KitchenDepartment) {
            return $department;
        }

        $this->seedKitchenDepartments->handle($branch);

        return $this->queryDefaultKitchenDepartment($branch);
    }

    private function queryDefaultKitchenDepartment(Branch $branch): ?KitchenDepartment
    {
        return $branch
            ->kitchenDepartments()
            ->select(['id', 'branch_id', 'type', 'name'])
            ->where('type', KitchenDepartmentType::Kitchen->value)
            ->where('is_active', true)
            ->oldest('sort_order')
            ->oldest('name')
            ->oldest('id')
            ->first();
    }

    /**
     * @param  EloquentCollection<int, KitchenTicket>  $tickets
     * @return array<string, mixed>
     */
    private function updatedOrderMetadata(Order $order, EloquentCollection $tickets, User $sentBy): array
    {
        $metadata = $order->metadata ?? [];
        $departmentTypes = $tickets
            ->map(fn (KitchenTicket $ticket): string => $ticket->department_type)
            ->values();

        return array_merge($metadata, [
            'sent_to_kitchen_bar' => true,
            'sent_to_kitchen_bar_at' => now()->toISOString(),
            'sent_to_kitchen_bar_by_user_id' => $sentBy->id,
            'sent_to_kitchen' => $departmentTypes->contains(fn (string $type): bool => $type !== KitchenDepartmentType::Bar->value),
            'sent_to_bar' => $departmentTypes->contains(KitchenDepartmentType::Bar->value),
            'kitchen_tickets_count' => $tickets->count(),
        ]);
    }

    private function markServicePointCooking(Order $order): void
    {
        if ($order->servicePoint !== null) {
            $this->updateServicePointStatus->handle($order->servicePoint, ServicePointStatus::Cooking);
        }
    }
}
