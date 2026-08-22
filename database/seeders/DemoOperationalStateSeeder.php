<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AuditLogAction;
use App\Enums\DraftOrderStatus;
use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\KitchenTicketStatus;
use App\Enums\ManualPaymentMethod;
use App\Enums\ManualPaymentScope;
use App\Enums\OrderStatus;
use App\Enums\OrganizationSubscriptionPaymentStatus;
use App\Enums\OrganizationSubscriptionStatus;
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
            $branch = Branch::query()
                ->where('organization_id', $organization->id)
                ->where('name', self::BRANCH_NAME)
                ->firstOrFail();
            $waiter = User::query()->where('email', 'waiter@demo.test')->firstOrFail();
            $servicePoints = ServicePoint::query()
                ->where('branch_id', $branch->id)
                ->where('is_active', true)
                ->orderBy('id')
                ->limit(4)
                ->get();
            $menuItems = MenuItem::query()
                ->whereHas('menu', fn ($query) => $query->where('branch_id', $branch->id))
                ->with('kitchenDepartment')
                ->orderBy('id')
                ->limit(3)
                ->get();

            if ($servicePoints->count() < 4 || $menuItems->count() < 3) {
                throw new RuntimeException('The base demo restaurant must be seeded before operational states.');
            }

            $this->seedSubscription($organization);
            $this->seedSentDraft($branch, $servicePoints[0], $menuItems);
            $this->seedKitchenWorkflow($branch, $servicePoints[1], $menuItems, $waiter);
            $this->seedPaymentWorkflow($branch, $servicePoints[2], $menuItems[0], $waiter);
            $this->seedHistoricalVolume($branch, $servicePoints[3], $menuItems[1], $waiter);
        });
    }

    private function seedSubscription(Organization $organization): void
    {
        $subscription = OrganizationSubscription::query()
            ->where('organization_id', $organization->id)
            ->first() ?? new OrganizationSubscription;

        $subscription->forceFill([
            'organization_id' => $organization->id,
            'status' => OrganizationSubscriptionStatus::Active,
            'started_at' => now()->subMonths(2),
            'next_payment_at' => now()->addMonthNoOverflow(),
            'payment_status' => OrganizationSubscriptionPaymentStatus::Paid,
        ])->save();
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

        $this->draftItem($draft, $anna, $menuItems[0], 2, '12.50', '25.00', 'No onions');
        $this->draftItem($draft, $tomas, $menuItems[1], 1, '9.50', '9.50', null);
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
        $order = $this->order($session, $guest, $waiter, 'kitchen-progress-order', OrderStatus::InProgress, '30.00');
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
                unitPrice: '10.00',
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
        $order = $this->order($session, $guest, $waiter, 'payment-requested-order', OrderStatus::PaymentRequested, '15.00');
        $this->orderItem($order, $guest, $menuItem, 'Payment demo item', '15.00');
        $this->payment($branch, $session, $guest, $waiter, 'partial-payment', '5.00');
        $this->waiterCall($branch, $session, $guest, WaiterCallStatus::Handled, 'handled-payment-call', $waiter);
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
        $order = $this->order($session, $guest, $waiter, 'closed-high-volume-order', OrderStatus::Closed, '105.00');

        foreach (range(1, 21) as $index) {
            $this->orderItem($order, $guest, $menuItem, 'Historical demo item '.str_pad((string) $index, 2, '0', STR_PAD_LEFT), '5.00');
        }

        $this->payment($branch, $session, null, $waiter, 'closed-table-payment', '105.00');
        $this->audit($branch, $waiter, $session);
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
            ->where('metadata->demo_key', $demoKey)
            ->first() ?? new TableSession;

        $session->forceFill([
            'branch_id' => $branch->id,
            'service_point_id' => $servicePoint->id,
            'status' => $status,
            'source' => TableSessionSource::WaiterOpened,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'metadata' => ['demo_key' => $demoKey],
        ])->save();

        return $session;
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
            ->first() ?? new TableSessionGuest;

        $guest->forceFill([
            'table_session_id' => $session->id,
            'guest_name' => $name,
            'guest_token' => $guest->guest_token ?: Str::random(64),
            'status' => $status,
            'ready_at' => $ready ? now()->subMinutes(10) : null,
            'joined_at' => now()->subHour(),
            'left_at' => $status === TableSessionGuestStatus::Left ? now()->subDays(2) : null,
            'metadata' => ['demo' => true],
        ])->save();

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
            ->first() ?? new DraftOrder;

        $draft->forceFill([
            'table_session_id' => $session->id,
            'status' => $status,
            'sent_to_waiter_at' => now()->subMinutes(15),
            'sent_by_guest_id' => $guest->id,
            'converted_to_order_at' => $status === DraftOrderStatus::ConvertedToOrder ? now()->subMinutes(12) : null,
            'converted_by_user_id' => $status === DraftOrderStatus::ConvertedToOrder ? $waiter?->id : null,
        ])->save();

        return $draft;
    }

    private function draftItem(
        DraftOrder $draft,
        TableSessionGuest $guest,
        MenuItem $menuItem,
        int $quantity,
        string $unitPrice,
        string $totalPrice,
        ?string $comment,
    ): void {
        $item = DraftOrderItem::query()
            ->where('draft_order_id', $draft->id)
            ->where('table_session_guest_id', $guest->id)
            ->where('menu_item_id', $menuItem->id)
            ->first() ?? new DraftOrderItem;

        $item->forceFill([
            'draft_order_id' => $draft->id,
            'table_session_guest_id' => $guest->id,
            'menu_item_id' => $menuItem->id,
            'item_name' => $menuItem->name,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'modifier_total' => '0.00',
            'total_price' => $totalPrice,
            'selected_modifiers' => [],
            'comment' => $comment,
        ])->save();
    }

    private function order(
        TableSession $session,
        TableSessionGuest $guest,
        User $waiter,
        string $demoKey,
        OrderStatus $status,
        string $totalPrice,
    ): Order {
        $order = Order::query()
            ->where('metadata->demo_key', $demoKey)
            ->first();

        if ($order instanceof Order) {
            return $order;
        }

        $draft = $this->draft($session, DraftOrderStatus::ConvertedToOrder, $guest, $waiter);
        $order = new Order;
        $order->forceFill([
            'branch_id' => $session->branch_id,
            'service_point_id' => $session->service_point_id,
            'table_session_id' => $session->id,
            'draft_order_id' => $draft->id,
            'status' => $status,
            'confirmed_by_user_id' => $waiter->id,
            'confirmed_at' => now()->subMinutes(12),
            'total_price' => $totalPrice,
            'currency' => 'EUR',
            'metadata' => ['demo_key' => $demoKey],
        ])->save();

        return $order;
    }

    private function orderItem(
        Order $order,
        TableSessionGuest $guest,
        MenuItem $menuItem,
        string $itemName,
        string $unitPrice,
    ): OrderItem {
        $item = OrderItem::query()
            ->where('order_id', $order->id)
            ->where('item_name_snapshot', $itemName)
            ->first() ?? new OrderItem;

        $department = $menuItem->kitchenDepartment;
        $item->forceFill([
            'order_id' => $order->id,
            'table_session_guest_id' => $guest->id,
            'menu_item_id' => $menuItem->id,
            'original_menu_item_id' => $menuItem->id,
            'kitchen_department_id' => $department?->id,
            'kitchen_department_type' => $department?->type,
            'kitchen_department_name' => $department?->name,
            'guest_name' => $guest->guest_name,
            'guest_name_snapshot' => $guest->guest_name,
            'item_name' => $itemName,
            'item_name_snapshot' => $itemName,
            'item_description_snapshot' => null,
            'quantity' => 1,
            'unit_price' => $unitPrice,
            'unit_price_snapshot' => $unitPrice,
            'modifier_total' => '0.00',
            'total_price' => $unitPrice,
            'selected_modifiers' => [],
            'modifiers_snapshot' => [],
            'tax_snapshot' => [],
            'service_snapshot' => [],
            'comment' => null,
        ])->save();

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
            ->first() ?? new KitchenTicket;

        $ticket->forceFill([
            'order_id' => $order->id,
            'branch_id' => $branch->id,
            'service_point_id' => $session->service_point_id,
            'table_session_id' => $session->id,
            'kitchen_department_id' => $department->id,
            'department_type' => $department->type,
            'department_name' => $department->name,
            'status' => KitchenTicketStatus::Sent,
            'sent_by_user_id' => $waiter->id,
            'sent_at' => now()->subMinutes(10),
            'metadata' => ['demo' => true],
        ])->save();

        $ticketItem = KitchenTicketItem::query()
            ->where('order_item_id', $orderItem->id)
            ->first() ?? new KitchenTicketItem;
        $ticketItem->forceFill([
            'kitchen_ticket_id' => $ticket->id,
            'order_item_id' => $orderItem->id,
            'table_session_guest_id' => $orderItem->table_session_guest_id,
            'menu_item_id' => $menuItem->id,
            'guest_name' => $orderItem->guest_name,
            'item_name' => $orderItem->item_name,
            'quantity' => $orderItem->quantity,
            'status' => $status,
            'selected_modifiers' => [],
            'comment' => null,
        ])->save();
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
            ->where('metadata->demo_key', $demoKey)
            ->first() ?? new WaiterCall;
        $call->forceFill([
            'branch_id' => $branch->id,
            'service_point_id' => $session->service_point_id,
            'table_session_id' => $session->id,
            'requested_by_guest_id' => $guest->id,
            'status' => $status,
            'requested_at' => now()->subMinutes(8),
            'handled_at' => $status === WaiterCallStatus::Handled ? now()->subMinutes(6) : null,
            'handled_by_user_id' => $status === WaiterCallStatus::Handled ? $waiter?->id : null,
            'metadata' => ['demo_key' => $demoKey],
        ])->save();
    }

    private function payment(
        Branch $branch,
        TableSession $session,
        ?TableSessionGuest $guest,
        User $waiter,
        string $demoKey,
        string $amount,
    ): void {
        if (ManualPayment::query()->where('metadata->demo_key', $demoKey)->exists()) {
            return;
        }

        $payment = new ManualPayment;
        $payment->forceFill([
            'branch_id' => $branch->id,
            'service_point_id' => $session->service_point_id,
            'table_session_id' => $session->id,
            'table_session_guest_id' => $guest?->id,
            'recorded_by_user_id' => $waiter->id,
            'scope' => $guest instanceof TableSessionGuest ? ManualPaymentScope::Guest : ManualPaymentScope::Table,
            'payment_method' => ManualPaymentMethod::CardTerminal,
            'covered_subtotal_amount' => $amount,
            'service_charge_percent' => '0.00',
            'service_charge_amount' => '0.00',
            'tips_amount' => '0.00',
            'amount' => $amount,
            'currency' => 'EUR',
            'guest_name' => $guest?->guest_name,
            'note' => 'Deterministic demo payment',
            'paid_at' => now()->subMinutes(5),
            'metadata' => ['demo_key' => $demoKey],
        ])->save();
    }

    private function audit(Branch $branch, User $waiter, TableSession $session): void
    {
        if (AuditLog::query()
            ->where('entity_type', 'demo_table_session')
            ->where('entity_id', $session->id)
            ->exists()) {
            return;
        }

        $auditLog = new AuditLog;
        $auditLog->forceFill([
            'organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
            'user_id' => $waiter->id,
            'action' => AuditLogAction::TableSessionClosed,
            'entity_type' => 'demo_table_session',
            'entity_id' => $session->id,
            'old_values' => ['status' => TableSessionStatus::Active->value],
            'new_values' => ['status' => TableSessionStatus::Closed->value],
            'created_at' => now()->subDays(2),
        ])->save();
    }
}
