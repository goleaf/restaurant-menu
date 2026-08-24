<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AuditLogAction;
use App\Enums\DraftOrderStatus;
use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\KitchenTicketStatus;
use App\Enums\ManualPaymentScope;
use App\Enums\OrderStatus;
use App\Enums\OrderStatusLogEvent;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionSource;
use App\Enums\TableSessionStatus;
use App\Enums\WaiterCallStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\ManualPayment;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusLog;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use App\Models\WaiterCall;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DemoOperationalStateSeeder extends Seeder
{
    private const ORGANIZATION_NAME = 'Demo Food Group';

    private const BRANCH_NAME = 'Bella Pizza Old Town';

    public function run(): void
    {
        if (strtolower((string) config('app.env')) === 'production') {
            throw new RuntimeException('DemoOperationalStateSeeder is development-only and cannot run while APP_ENV=production.');
        }

        DB::transaction(function (): void {
            $organization = Organization::query()
                ->where('name', self::ORGANIZATION_NAME)
                ->firstOrFail();
            $branches = Branch::query()
                ->select(['id', 'organization_id', 'name', 'currency'])
                ->where('organization_id', $organization->id)
                ->with([
                    'servicePoints' => fn ($query) => $query
                        ->select(['id', 'branch_id', 'area_node_id', 'name', 'is_active'])
                        ->where('is_active', true)
                        ->orderBy('id'),
                    'menus' => fn ($query) => $query
                        ->select(['id', 'branch_id', 'name', 'sort_order'])
                        ->with([
                            'items' => fn ($itemQuery) => $itemQuery
                                ->select([
                                    'id',
                                    'menu_id',
                                    'kitchen_department_id',
                                    'name',
                                    'price_cents',
                                    'sort_order',
                                ])
                                ->with([
                                    'kitchenDepartment:id,branch_id,type,name',
                                    'variants' => fn ($variantQuery) => $variantQuery
                                        ->select([
                                            'id',
                                            'menu_item_id',
                                            'type',
                                            'name',
                                            'price_cents',
                                            'is_default',
                                            'sort_order',
                                        ]),
                                ])
                                ->orderBy('id'),
                        ]),
                ])
                ->orderBy('id')
                ->get();
            $branch = $branches->firstWhere('name', self::BRANCH_NAME);

            if (! $branch instanceof Branch) {
                throw new RuntimeException('The primary demo branch must be seeded before operational states.');
            }

            $waiter = User::query()->where('email', 'waiter@demo.test')->firstOrFail();
            $manager = User::query()->where('email', 'manager@demo.test')->firstOrFail();
            $servicePoints = $branch->servicePoints->take(4)->values();
            $menuItems = $branch->menus
                ->flatMap(fn ($menu) => $menu->items)
                ->take(3)
                ->values();

            if ($servicePoints->count() < 4 || $menuItems->count() < 3) {
                throw new RuntimeException('The base demo restaurant must be seeded before operational states.');
            }

            $this->seedSubscription($organization);
            $this->seedSentDraft($branch, $servicePoints[0], $menuItems);
            $this->seedKitchenWorkflow($branch, $servicePoints[1], $menuItems, $waiter);
            $this->seedPaymentWorkflow($branch, $servicePoints[2], $menuItems[0], $waiter);
            $this->seedHistoricalVolume($branch, $servicePoints[3], $menuItems[1], $waiter);

            foreach ($branches as $operationalBranch) {
                $barServicePoint = $operationalBranch->servicePoints->last();
                $barMenuItems = $operationalBranch->menus
                    ->flatMap(fn ($menu) => $menu->items)
                    ->filter(fn (MenuItem $menuItem): bool => $menuItem->kitchenDepartment?->type === KitchenDepartmentType::Bar)
                    ->values();

                if (! $barServicePoint instanceof ServicePoint || $barMenuItems->isEmpty()) {
                    throw new RuntimeException("Demo branch [{$operationalBranch->name}] must have a service point and bar menu item.");
                }

                $this->seedBarWorkflow($operationalBranch, $barServicePoint, $barMenuItems, $manager);
            }

            foreach ($branches->where('id', '!=', $branch->id) as $secondaryBranch) {
                $servicePoint = $secondaryBranch->servicePoints->first();
                $menuItem = $secondaryBranch->menus
                    ->flatMap(fn ($menu) => $menu->items)
                    ->first();

                if (! $servicePoint instanceof ServicePoint || ! $menuItem instanceof MenuItem) {
                    throw new RuntimeException("Demo branch [{$secondaryBranch->name}] must have a service point and menu item.");
                }

                $this->seedPaidBranchWorkflow($secondaryBranch, $servicePoint, $menuItem, $manager);
            }

            $this->seedLifecycleShowcases($organization, $waiter);
        });
    }

    private function seedLifecycleShowcases(Organization $organization, User $waiter): void
    {
        $demoKeys = array_map(
            fn (OrderStatus $status): string => 'order-status-'.$status->value,
            OrderStatus::cases(),
        );
        $existingServicePointIds = TableSession::query()
            ->select(['id', 'branch_id', 'service_point_id', 'metadata'])
            ->whereHas('branch', fn ($query) => $query->where('organization_id', $organization->id))
            ->whereIn('metadata->demo_key', $demoKeys)
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (TableSession $session): array => [
                (string) data_get($session->metadata, 'demo_key') => $session->service_point_id,
            ]);
        $missingCount = count($demoKeys) - $existingServicePointIds->count();
        $availableServicePointIds = ServicePoint::query()
            ->select(['id', 'branch_id'])
            ->whereHas('branch', fn ($query) => $query->where('organization_id', $organization->id))
            ->whereDoesntHave('activeTableSession')
            ->orderBy('id')
            ->limit($missingCount)
            ->pluck('id')
            ->values();

        if ($availableServicePointIds->count() !== $missingCount) {
            throw new RuntimeException('The demo graph requires one free service point per order lifecycle status.');
        }

        foreach (OrderStatus::cases() as $index => $status) {
            $demoKey = 'order-status-'.$status->value;
            $servicePointId = $existingServicePointIds->get($demoKey)
                ?? $availableServicePointIds->shift();
            $servicePoint = $this->lifecycleServicePoint((int) $servicePointId);
            $branch = $servicePoint->branch;
            $menuItem = $branch->menus
                ->flatMap(fn ($menu) => $menu->items)
                ->first();

            if (! $menuItem instanceof MenuItem) {
                throw new RuntimeException("Demo branch [{$branch->id}] requires a menu item for lifecycle states.");
            }

            $session = $this->session(
                $branch,
                $servicePoint,
                $demoKey,
                $this->tableSessionStatusFor($status),
                now()->subMinutes(90 - $index),
                $this->tableSessionEndedAtFor($status),
            );
            $guest = $this->guest($session, 'Order '.$status->value.' Guest', true);
            $amountCents = max(1, $menuItem->price_cents);
            $order = $this->order(
                $session,
                $guest,
                $waiter,
                $demoKey,
                $status,
                $amountCents,
            );
            $orderItem = $this->orderItem(
                $order,
                $guest,
                $menuItem,
                'Order status '.$status->value,
                $amountCents,
            );
            $ticketItemStatus = $this->ticketItemStatusFor($status);

            if ($ticketItemStatus instanceof KitchenTicketItemStatus) {
                $this->ticketItem(
                    $branch,
                    $session,
                    $order,
                    $orderItem,
                    $menuItem,
                    $ticketItemStatus,
                    $waiter,
                );
            }

            $previousStatus = $this->previousOrderStatusFor($status);

            if ($previousStatus instanceof OrderStatus) {
                $this->orderStatusLog(
                    $order,
                    $waiter,
                    'order-status-log-'.$status->value,
                    $previousStatus,
                    $status,
                );
            }

            if ($index === 0) {
                $this->seedDraftLifecycleShowcase($session, $guest, $menuItem, $waiter);
            }
        }
    }

    private function lifecycleServicePoint(int $servicePointId): ServicePoint
    {
        return ServicePoint::query()
            ->select(['id', 'branch_id', 'area_node_id', 'name', 'is_active'])
            ->with([
                'branch:id,organization_id,currency',
                'branch.menus:id,branch_id,name',
                'branch.menus.items' => fn ($query) => $query
                    ->select([
                        'id',
                        'menu_id',
                        'kitchen_department_id',
                        'name',
                        'price_cents',
                        'sort_order',
                    ])
                    ->with([
                        'kitchenDepartment:id,branch_id,type,name',
                        'variants:id,menu_item_id,type,name,price_cents,is_default,sort_order',
                    ])
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->whereKey($servicePointId)
            ->firstOrFail();
    }

    private function seedDraftLifecycleShowcase(
        TableSession $session,
        TableSessionGuest $guest,
        MenuItem $menuItem,
        User $waiter,
    ): void {
        foreach (DraftOrderStatus::cases() as $index => $status) {
            $draft = $this->draft($session, $status, $guest, $waiter);
            $amountCents = max(1, $menuItem->price_cents);

            $this->draftItem(
                $draft,
                $guest,
                $menuItem,
                1,
                $amountCents,
                $amountCents,
                $index === 0 ? 'Open demo cart' : null,
            );
        }
    }

    private function tableSessionStatusFor(OrderStatus $status): TableSessionStatus
    {
        return match ($status) {
            OrderStatus::ConfirmedByWaiter,
            OrderStatus::SentToKitchenBar,
            OrderStatus::InProgress,
            OrderStatus::Ready,
            OrderStatus::Served => TableSessionStatus::Active,
            OrderStatus::PaymentRequested => TableSessionStatus::PaymentRequested,
            OrderStatus::Paid => TableSessionStatus::Paid,
            OrderStatus::Closed => TableSessionStatus::Closed,
            OrderStatus::Cancelled => TableSessionStatus::Cancelled,
        };
    }

    private function tableSessionEndedAtFor(OrderStatus $status): ?\DateTimeInterface
    {
        return in_array($status, [OrderStatus::Closed, OrderStatus::Cancelled], true)
            ? now()->subMinutes(30)
            : null;
    }

    private function ticketItemStatusFor(OrderStatus $status): ?KitchenTicketItemStatus
    {
        return match ($status) {
            OrderStatus::ConfirmedByWaiter => null,
            OrderStatus::SentToKitchenBar => KitchenTicketItemStatus::New,
            OrderStatus::InProgress => KitchenTicketItemStatus::InProgress,
            OrderStatus::Cancelled => KitchenTicketItemStatus::Cancelled,
            default => KitchenTicketItemStatus::Ready,
        };
    }

    private function previousOrderStatusFor(OrderStatus $status): ?OrderStatus
    {
        return match ($status) {
            OrderStatus::ConfirmedByWaiter => null,
            OrderStatus::SentToKitchenBar => OrderStatus::ConfirmedByWaiter,
            OrderStatus::InProgress => OrderStatus::SentToKitchenBar,
            OrderStatus::Ready => OrderStatus::InProgress,
            OrderStatus::Served => OrderStatus::Ready,
            OrderStatus::PaymentRequested => OrderStatus::Served,
            OrderStatus::Paid => OrderStatus::PaymentRequested,
            OrderStatus::Closed => OrderStatus::Paid,
            OrderStatus::Cancelled => OrderStatus::ConfirmedByWaiter,
        };
    }

    private function seedSubscription(Organization $organization): void
    {
        $subscription = OrganizationSubscription::query()
            ->where('organization_id', $organization->id)
            ->first();
        $factory = OrganizationSubscription::factory()
            ->for($organization)
            ->active()
            ->paymentPaid()
            ->state([
                'started_at' => now()->subMonths(2),
                'next_payment_at' => now()->addMonthNoOverflow(),
            ]);

        if (! $subscription instanceof OrganizationSubscription) {
            $factory->create();

            return;
        }

        $subscription->forceFill($factory->make()->attributesToArray())->save();
    }

    /**
     * @param  Collection<int, MenuItem>  $menuItems
     */
    private function seedSentDraft(Branch $branch, ServicePoint $servicePoint, $menuItems): void
    {
        $session = $this->session($branch, $servicePoint, 'active-draft', TableSessionStatus::Active, now()->subMinutes(28));
        $anna = $this->guest($session, 'Anna Demo', true);
        $tomas = $this->guest($session, 'Tomas Demo', false);
        $draft = $this->draft($session, DraftOrderStatus::SentToWaiter, $anna);

        $this->draftItem($draft, $anna, $menuItems[0], 2, 1250, 2500, 'No onions');
        $this->draftItem($draft, $tomas, $menuItems[1], 1, 950, 950, null);
        $this->waiterCall($branch, $session, $anna, WaiterCallStatus::Pending, 'active-draft-call');
    }

    /**
     * @param  Collection<int, MenuItem>  $menuItems
     */
    private function seedKitchenWorkflow(
        Branch $branch,
        ServicePoint $servicePoint,
        $menuItems,
        User $waiter,
    ): void {
        $session = $this->session($branch, $servicePoint, 'kitchen-progress', TableSessionStatus::Active, now()->subMinutes(45));
        $guest = $this->guest($session, 'Milda Demo', true);
        $order = $this->order($session, $guest, $waiter, 'kitchen-progress-order', OrderStatus::InProgress, 3000);
        $statuses = [
            KitchenTicketItemStatus::New,
            KitchenTicketItemStatus::InProgress,
            KitchenTicketItemStatus::Ready,
        ];

        foreach ($menuItems as $index => $menuItem) {
            $orderItem = $this->orderItem(
                order: $order,
                guest: $guest,
                menuItem: $menuItem,
                itemName: 'Kitchen demo '.($index + 1),
                unitPriceCents: 1000,
            );
            $this->ticketItem($branch, $session, $order, $orderItem, $menuItem, $statuses[$index], $waiter);
        }
    }

    private function seedPaymentWorkflow(
        Branch $branch,
        ServicePoint $servicePoint,
        MenuItem $menuItem,
        User $waiter,
    ): void {
        $session = $this->session($branch, $servicePoint, 'payment-requested', TableSessionStatus::PaymentRequested, now()->subHour());
        $guest = $this->guest($session, 'Jonas Demo', true);
        $order = $this->order($session, $guest, $waiter, 'payment-requested-order', OrderStatus::PaymentRequested, 1500);
        $this->orderItem($order, $guest, $menuItem, 'Payment demo item', 1500);
        $this->payment($branch, $session, $guest, $waiter, 'partial-payment', 500);
        $this->waiterCall($branch, $session, $guest, WaiterCallStatus::Handled, 'handled-payment-call', $waiter);
    }

    /**
     * @param  Collection<int, MenuItem>  $menuItems
     */
    private function seedBarWorkflow(
        Branch $branch,
        ServicePoint $servicePoint,
        Collection $menuItems,
        User $manager,
    ): void {
        $branchKey = Str::slug($branch->name);
        $statuses = [
            KitchenTicketItemStatus::New,
            KitchenTicketItemStatus::InProgress,
            KitchenTicketItemStatus::Ready,
        ];
        $menuItemCount = $menuItems->count();
        $workflowItems = collect($statuses)
            ->map(fn (KitchenTicketItemStatus $status, int $index): array => [
                'menu_item' => $menuItems[$index % $menuItemCount],
                'status' => $status,
            ]);
        $totalPriceCents = $workflowItems->sum(
            fn (array $workflowItem): int => max(1, $workflowItem['menu_item']->price_cents),
        );
        $session = $this->session(
            $branch,
            $servicePoint,
            "$branchKey-bar-live",
            TableSessionStatus::Active,
            now()->subMinutes(35),
        );
        $guest = $this->guest($session, $branch->name.' Bar Guest', true);
        $order = $this->order(
            $session,
            $guest,
            $manager,
            "$branchKey-bar-order",
            OrderStatus::InProgress,
            $totalPriceCents,
        );

        foreach ($workflowItems as $index => $workflowItem) {
            $menuItem = $workflowItem['menu_item'];
            $status = $workflowItem['status'];
            $orderItem = $this->orderItem(
                order: $order,
                guest: $guest,
                menuItem: $menuItem,
                itemName: 'Bar demo '.($index + 1).' '.$status->value,
                unitPriceCents: max(1, $menuItem->price_cents),
            );
            $this->ticketItem($branch, $session, $order, $orderItem, $menuItem, $status, $manager);
        }

        $this->orderStatusLog(
            order: $order,
            actor: $manager,
            demoKey: "$branchKey-bar-status-history",
            previousStatus: OrderStatus::SentToKitchenBar,
            newStatus: OrderStatus::InProgress,
        );
    }

    private function seedHistoricalVolume(
        Branch $branch,
        ServicePoint $servicePoint,
        MenuItem $menuItem,
        User $waiter,
    ): void {
        $session = $this->session(
            $branch,
            $servicePoint,
            'closed-high-volume',
            TableSessionStatus::Closed,
            now()->subDays(2)->subHours(2),
            now()->subDays(2),
        );
        $guest = $this->guest($session, 'Živilė Demo', false, TableSessionGuestStatus::Left);
        $order = $this->order($session, $guest, $waiter, 'closed-high-volume-order', OrderStatus::Closed, 10500);

        foreach (range(1, 21) as $index) {
            $this->orderItem($order, $guest, $menuItem, 'Historical demo item '.str_pad((string) $index, 2, '0', STR_PAD_LEFT), 500);
        }

        $this->payment($branch, $session, null, $waiter, 'closed-table-payment', 10500);
        $this->audit($branch, $waiter, $session);
    }

    private function seedPaidBranchWorkflow(
        Branch $branch,
        ServicePoint $servicePoint,
        MenuItem $menuItem,
        User $manager,
    ): void {
        $branchKey = Str::slug($branch->name);
        $session = $this->session(
            $branch,
            $servicePoint,
            "$branchKey-paid",
            TableSessionStatus::Closed,
            now()->subDay()->subHour(),
            now()->subDay(),
        );
        $guest = $this->guest($session, $branch->name.' Demo Guest', false, TableSessionGuestStatus::Left);
        $amountCents = max(1, $menuItem->price_cents);
        $order = $this->order(
            $session,
            $guest,
            $manager,
            "$branchKey-order",
            OrderStatus::Closed,
            $amountCents,
        );

        $this->orderItem($order, $guest, $menuItem, $branch->name.' demo item', $amountCents);
        $this->payment($branch, $session, null, $manager, "$branchKey-payment", $amountCents);
        $this->audit($branch, $manager, $session);
    }

    private function session(
        Branch $branch,
        ServicePoint $servicePoint,
        string $demoKey,
        TableSessionStatus $status,
        \DateTimeInterface $startedAt,
        ?\DateTimeInterface $endedAt = null,
    ): TableSession {
        $session = TableSession::query()
            ->where('branch_id', $branch->id)
            ->where('metadata->demo_key', $demoKey)
            ->first();
        $factory = TableSession::factory()
            ->forServicePoint($servicePoint)
            ->state([
                'status' => $status,
                'source' => TableSessionSource::WaiterOpened,
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'metadata' => ['demo_key' => $demoKey],
            ]);

        if (! $session instanceof TableSession) {
            $session = $factory->create();
        } else {
            $session->forceFill($factory->make()->attributesToArray())->save();
        }

        $servicePoint->forceFill([
            'status' => $this->servicePointStatusFor($status),
        ])->save();

        $session->setRelation('branch', $branch);

        return $session;
    }

    private function servicePointStatusFor(TableSessionStatus $status): ServicePointStatus
    {
        return match ($status) {
            TableSessionStatus::Pending => ServicePointStatus::WaitingWaiter,
            TableSessionStatus::Active => ServicePointStatus::Occupied,
            TableSessionStatus::WaitingWaiterConfirmation => ServicePointStatus::HasNewOrder,
            TableSessionStatus::PaymentRequested => ServicePointStatus::PaymentRequested,
            TableSessionStatus::Paid => ServicePointStatus::Paid,
            TableSessionStatus::Closed,
            TableSessionStatus::Cancelled => ServicePointStatus::Free,
        };
    }

    private function guest(
        TableSession $session,
        string $name,
        bool $ready,
        TableSessionGuestStatus $status = TableSessionGuestStatus::Active,
    ): TableSessionGuest {
        $guest = TableSessionGuest::query()
            ->where('table_session_id', $session->id)
            ->where('guest_name', $name)
            ->first();
        $factory = TableSessionGuest::factory()
            ->for($session)
            ->state([
                'guest_name' => $name,
                'guest_token' => $guest?->guest_token ?: Str::random(64),
                'status' => $status,
                'ready_at' => $ready ? now()->subMinutes(10) : null,
                'joined_at' => now()->subHour(),
                'left_at' => $status === TableSessionGuestStatus::Left ? now()->subDays(2) : null,
                'metadata' => ['demo' => true],
            ]);

        if (! $guest instanceof TableSessionGuest) {
            return $factory->create();
        }

        $guest->forceFill($factory->make()->attributesToArray())->save();

        return $guest;
    }

    private function draft(
        TableSession $session,
        DraftOrderStatus $status,
        TableSessionGuest $guest,
        ?User $waiter = null,
    ): DraftOrder {
        $draft = DraftOrder::query()
            ->where('table_session_id', $session->id)
            ->where('status', $status->value)
            ->first();
        $factory = DraftOrder::factory()
            ->forTableSession($session)
            ->forStatus($status, $waiter)
            ->state([
                'sent_by_guest_id' => $status === DraftOrderStatus::Draft ? null : $guest->id,
            ]);

        if (! $draft instanceof DraftOrder) {
            return $factory->create();
        }

        $draft->forceFill($factory->make()->attributesToArray())->save();

        return $draft;
    }

    private function draftItem(
        DraftOrder $draft,
        TableSessionGuest $guest,
        MenuItem $menuItem,
        int $quantity,
        int $unitPriceCents,
        int $totalPriceCents,
        ?string $comment,
    ): void {
        $variant = $menuItem->variants->firstWhere('is_default', true) ?? $menuItem->variants->first();
        $item = DraftOrderItem::query()
            ->where('draft_order_id', $draft->id)
            ->where('table_session_guest_id', $guest->id)
            ->where('menu_item_id', $menuItem->id)
            ->first();
        $factory = DraftOrderItem::factory()
            ->for($draft, 'draftOrder')
            ->for($guest, 'guest')
            ->for($menuItem, 'menuItem')
            ->state([
                'menu_item_variant_id' => $variant?->id,
                'item_name' => $menuItem->name,
                'variant_name' => $variant?->name,
                'variant_type' => $variant?->type,
                'quantity' => $quantity,
                'unit_price_cents' => $unitPriceCents,
                'modifier_total_cents' => 0,
                'total_price_cents' => $totalPriceCents,
                'selected_modifiers' => [],
                'comment' => $comment,
            ]);

        if (! $item instanceof DraftOrderItem) {
            $factory->create();

            return;
        }

        $item->forceFill($factory->make()->attributesToArray())->save();
    }

    private function order(
        TableSession $session,
        TableSessionGuest $guest,
        User $waiter,
        string $demoKey,
        OrderStatus $status,
        int $totalPriceCents,
    ): Order {
        $order = Order::query()
            ->where('branch_id', $session->branch_id)
            ->where('metadata->demo_key', $demoKey)
            ->first();
        $draft = $order instanceof Order
            ? DraftOrder::query()->findOrFail($order->draft_order_id)
            : DraftOrder::factory()
                ->forTableSession($session)
                ->forStatus(DraftOrderStatus::ConvertedToOrder, $waiter)
                ->state(['sent_by_guest_id' => $guest->id])
                ->create();
        $factory = Order::factory()
            ->forTableSession($session)
            ->forStatus($status)
            ->state([
                'draft_order_id' => $draft->id,
                'confirmed_by_user_id' => $waiter->id,
                'confirmed_at' => now()->subMinutes(12),
                'total_price_cents' => $totalPriceCents,
                'currency' => $session->branch->currency,
                'metadata' => ['demo_key' => $demoKey],
            ]);

        if (! $order instanceof Order) {
            return $factory->create();
        }

        $order->forceFill($factory->make()->attributesToArray())->save();

        return $order;
    }

    private function orderItem(
        Order $order,
        TableSessionGuest $guest,
        MenuItem $menuItem,
        string $itemName,
        int $unitPriceCents,
    ): OrderItem {
        $variant = $menuItem->variants->firstWhere('is_default', true) ?? $menuItem->variants->first();
        $item = OrderItem::query()
            ->where('order_id', $order->id)
            ->where('item_name_snapshot', $itemName)
            ->first();

        $department = $menuItem->kitchenDepartment;
        $factory = OrderItem::factory()
            ->for($order)
            ->for($guest, 'guest')
            ->for($menuItem, 'menuItem')
            ->state([
                'menu_item_variant_id' => $variant?->id,
                'original_menu_item_id' => $menuItem->id,
                'kitchen_department_id' => $department?->id,
                'kitchen_department_type' => $department?->type,
                'kitchen_department_name' => $department?->name,
                'guest_name' => $guest->guest_name,
                'guest_name_snapshot' => $guest->guest_name,
                'item_name' => $itemName,
                'item_name_snapshot' => $itemName,
                'item_description_snapshot' => null,
                'variant_name' => $variant?->name,
                'variant_type' => $variant?->type,
                'quantity' => 1,
                'unit_price_cents' => $unitPriceCents,
                'unit_price_snapshot_cents' => $unitPriceCents,
                'modifier_total_cents' => 0,
                'total_price_cents' => $unitPriceCents,
                'selected_modifiers' => [],
                'modifiers_snapshot' => [],
                'tax_snapshot' => [],
                'service_snapshot' => [],
                'comment' => null,
                'cancelled_at' => null,
                'cancelled_by_user_id' => null,
                'cancellation_reason' => null,
            ]);

        if (! $item instanceof OrderItem) {
            return $factory->create();
        }

        $item->forceFill($factory->make()->attributesToArray())->save();

        return $item;
    }

    private function ticketItem(
        Branch $branch,
        TableSession $session,
        Order $order,
        OrderItem $orderItem,
        MenuItem $menuItem,
        KitchenTicketItemStatus $status,
        User $waiter,
    ): void {
        $department = $menuItem->kitchenDepartment ?? KitchenDepartment::query()
            ->where('branch_id', $branch->id)
            ->where('type', KitchenDepartmentType::Kitchen->value)
            ->firstOrFail();
        $ticket = KitchenTicket::query()
            ->where('order_id', $order->id)
            ->where('department_type', $department->type->value)
            ->where('department_name', $department->name)
            ->first();
        $ticketFactory = KitchenTicket::factory()
            ->forOrder($order)
            ->state([
                'kitchen_department_id' => $department->id,
                'department_type' => $department->type,
                'department_name' => $department->name,
                'status' => KitchenTicketStatus::Sent,
                'sent_by_user_id' => $waiter->id,
                'sent_at' => now()->subMinutes(10),
                'metadata' => ['demo' => true],
            ]);

        if (! $ticket instanceof KitchenTicket) {
            $ticket = $ticketFactory->create();
        } else {
            $ticket->forceFill($ticketFactory->make()->attributesToArray())->save();
        }

        $ticketItem = KitchenTicketItem::query()
            ->where('order_item_id', $orderItem->id)
            ->first();
        $ticketItemFactory = KitchenTicketItem::factory()
            ->forDispatchedOrderItem($ticket, $orderItem)
            ->state([
                'menu_item_id' => $menuItem->id,
                'guest_name' => $orderItem->guest_name,
                'item_name' => filled($orderItem->variant_name)
                    ? $orderItem->item_name.' · '.$orderItem->variant_name
                    : $orderItem->item_name,
                'quantity' => $orderItem->quantity,
                'status' => $status,
                'selected_modifiers' => [],
                'comment' => null,
            ]);

        if (! $ticketItem instanceof KitchenTicketItem) {
            $ticketItemFactory->create();

            return;
        }

        $ticketItem->forceFill($ticketItemFactory->make()->attributesToArray())->save();
    }

    private function waiterCall(
        Branch $branch,
        TableSession $session,
        TableSessionGuest $guest,
        WaiterCallStatus $status,
        string $demoKey,
        ?User $waiter = null,
    ): void {
        $call = WaiterCall::query()
            ->where('branch_id', $branch->id)
            ->where('metadata->demo_key', $demoKey)
            ->first();
        $factory = WaiterCall::factory()
            ->forTableSession($session)
            ->state([
                'requested_by_guest_id' => $guest->id,
                'status' => $status,
                'requested_at' => now()->subMinutes(8),
                'handled_at' => $status === WaiterCallStatus::Handled ? now()->subMinutes(6) : null,
                'handled_by_user_id' => $status === WaiterCallStatus::Handled ? $waiter?->id : null,
                'metadata' => ['demo_key' => $demoKey],
            ]);

        if (! $call instanceof WaiterCall) {
            $factory->create();

            return;
        }

        $call->forceFill($factory->make()->attributesToArray())->save();
    }

    private function payment(
        Branch $branch,
        TableSession $session,
        ?TableSessionGuest $guest,
        User $waiter,
        string $demoKey,
        int $amountCents,
    ): void {
        if (ManualPayment::query()
            ->where('branch_id', $branch->id)
            ->where('metadata->demo_key', $demoKey)
            ->exists()) {
            return;
        }

        $factory = $guest instanceof TableSessionGuest
            ? ManualPayment::factory()->forGuest($guest)
            : ManualPayment::factory()->forTableSession($session);

        $factory
            ->cardTerminal()
            ->state([
                'recorded_by_user_id' => $waiter->id,
                'scope' => $guest instanceof TableSessionGuest ? ManualPaymentScope::Guest : ManualPaymentScope::Table,
                'covered_subtotal_cents' => $amountCents,
                'service_charge_basis_points' => 0,
                'service_charge_cents' => 0,
                'tips_cents' => 0,
                'amount_cents' => $amountCents,
                'currency' => $branch->currency,
                'guest_name' => $guest?->guest_name,
                'note' => 'Deterministic demo payment',
                'paid_at' => now()->subMinutes(5),
                'metadata' => ['demo_key' => $demoKey],
            ])->create();
    }

    private function audit(Branch $branch, User $waiter, TableSession $session): void
    {
        if (AuditLog::query()
            ->where('entity_type', 'demo_table_session')
            ->where('entity_id', $session->id)
            ->exists()) {
            return;
        }

        AuditLog::factory()->create([
            'organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
            'user_id' => $waiter->id,
            'action' => AuditLogAction::TableSessionClosed,
            'entity_type' => 'demo_table_session',
            'entity_id' => $session->id,
            'old_values' => ['status' => TableSessionStatus::Active->value],
            'new_values' => ['status' => TableSessionStatus::Closed->value],
            'created_at' => now()->subDays(2),
        ]);
    }

    private function orderStatusLog(
        Order $order,
        User $actor,
        string $demoKey,
        OrderStatus $previousStatus,
        OrderStatus $newStatus,
    ): void {
        $factory = OrderStatusLog::factory()
            ->forOrderTransition($order, $actor, $previousStatus, $newStatus)
            ->state([
                'event' => OrderStatusLogEvent::OrderStatusChanged,
                'reason' => 'Deterministic demo status history',
                'metadata' => ['demo_key' => $demoKey],
                'occurred_at' => now()->subMinutes(8),
            ]);
        $statusLog = OrderStatusLog::query()
            ->where('branch_id', $order->branch_id)
            ->where('metadata->demo_key', $demoKey)
            ->first();

        if (! $statusLog instanceof OrderStatusLog) {
            $factory->create();

            return;
        }

        $statusLog->forceFill($factory->make()->attributesToArray())->save();
    }
}
