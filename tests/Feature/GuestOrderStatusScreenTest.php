<?php

declare(strict_types=1);

use App\Enums\DraftOrderStatus;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\OrderStatus;
use App\Enums\QrCodeStatus;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Livewire\PublicQr\OrderStatuses;
use App\Models\Branch;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use Livewire\Livewire;

test('guest sees friendly draft sent and waiter review statuses', function () {
    [$tableSession, $guest, $qrCode] = createPrompt124TableSession();
    $draftOrder = DraftOrder::factory()
        ->for($tableSession)
        ->create(['status' => DraftOrderStatus::Draft]);

    DraftOrderItem::factory()
        ->for($draftOrder, 'draftOrder')
        ->for($guest, 'guest')
        ->create([
            'item_name' => 'Маргарита',
            'quantity' => 2,
            'total_price_cents' => 1800,
        ]);

    $component = Livewire::withCookie(prompt124GuestCookieName($qrCode), $guest->guest_token)
        ->test(OrderStatuses::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $guest->id,
            'publicToken' => $qrCode->public_token,
            'pollingIntervalSeconds' => 1,
        ])
        ->assertSet('overallStatusLabel', 'Choosing items')
        ->assertSet('itemStatuses.0.status_key', 'guest.statuses.items.draft')
        ->assertSeeText('Choosing items')
        ->assertSeeText('Маргарита')
        ->assertSeeText('In draft');

    expect(collect($component->get('guestSteps'))->firstWhere('key', 'draft')['state'])->toBe('current');

    $draftOrder->forceFill([
        'status' => DraftOrderStatus::SentToWaiter,
        'sent_to_waiter_at' => now(),
        'sent_by_guest_id' => $guest->id,
    ])->save();

    $component
        ->call('refreshOrderStatuses')
        ->assertSet('overallStatusLabel', 'Sent to waiter')
        ->assertSet('itemStatuses.0.status_key', 'guest.statuses.items.sent_to_waiter')
        ->assertSeeText('Sent to waiter')
        ->assertSeeText('Waiting for waiter');

    expect(collect($component->get('guestSteps'))->firstWhere('key', 'sent_to_waiter')['state'])->toBe('current');

    $draftOrder->forceFill(['status' => DraftOrderStatus::WaiterReview])->save();

    $component
        ->call('refreshOrderStatuses')
        ->assertSet('overallStatusLabel', 'Waiter review')
        ->assertSet('itemStatuses.0.status_key', 'guest.statuses.items.waiter_review')
        ->assertSeeText('Waiter review');

    expect(collect($component->get('guestSteps'))->firstWhere('key', 'waiter_review')['state'])->toBe('current');
});

test('guest sees accepted cooking ready and served item statuses', function () {
    [$tableSession, $guest, $qrCode] = createPrompt124TableSession();
    $draftOrder = DraftOrder::factory()
        ->for($tableSession)
        ->create(['status' => DraftOrderStatus::ConvertedToOrder]);
    $order = createPrompt124Order($tableSession, $draftOrder, OrderStatus::ConfirmedByWaiter);
    $acceptedItem = createPrompt124OrderItem($order, $guest, 'Суп дня');

    Livewire::withCookie(prompt124GuestCookieName($qrCode), $guest->guest_token)
        ->test(OrderStatuses::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $guest->id,
            'publicToken' => $qrCode->public_token,
            'pollingIntervalSeconds' => 1,
        ])
        ->assertSet('overallStatusLabel', 'Order accepted')
        ->assertSet('itemStatuses.0.status_key', 'guest.statuses.items.accepted')
        ->assertSeeText('Order accepted')
        ->assertSeeText('Суп дня');

    $order->update(['status' => OrderStatus::InProgress]);
    $ticket = KitchenTicket::factory()
        ->for($order)
        ->create([
            'branch_id' => $order->branch_id,
            'service_point_id' => $order->service_point_id,
            'table_session_id' => $order->table_session_id,
        ]);
    $cookingItem = createPrompt124OrderItem($order, $guest, 'Паста');
    $readyItem = createPrompt124OrderItem($order, $guest, 'Лимонад');
    $servedItem = createPrompt124OrderItem($order, $guest, 'Десерт');

    createPrompt124TicketItem($ticket, $acceptedItem, KitchenTicketItemStatus::New);
    createPrompt124TicketItem($ticket, $cookingItem, KitchenTicketItemStatus::InProgress);
    createPrompt124TicketItem($ticket, $readyItem, KitchenTicketItemStatus::Ready);
    createPrompt124TicketItem($ticket, $servedItem, KitchenTicketItemStatus::Ready, now());

    Livewire::withCookie(prompt124GuestCookieName($qrCode), $guest->guest_token)
        ->test(OrderStatuses::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $guest->id,
            'publicToken' => $qrCode->public_token,
            'pollingIntervalSeconds' => 1,
        ])
        ->assertSet('overallStatusLabel', 'Cooking')
        ->assertSet('itemStatuses.0.status_key', 'guest.statuses.items.accepted')
        ->assertSet('itemStatuses.1.status_key', 'guest.statuses.items.cooking')
        ->assertSet('itemStatuses.2.status_key', 'guest.statuses.items.ready')
        ->assertSet('itemStatuses.3.status_key', 'guest.statuses.items.served')
        ->assertSeeText('Accepted')
        ->assertSeeText('Cooking')
        ->assertSeeText('Ready')
        ->assertSeeText('Served');
});

test('guest sees whole table bill and paid statuses', function () {
    [$tableSession, $guest, $qrCode] = createPrompt124TableSession();

    $tableSession->forceFill(['status' => TableSessionStatus::PaymentRequested])->save();

    $component = Livewire::withCookie(prompt124GuestCookieName($qrCode), $guest->guest_token)
        ->test(OrderStatuses::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $guest->id,
            'publicToken' => $qrCode->public_token,
            'pollingIntervalSeconds' => 1,
        ])
        ->assertSet('overallStatusLabel', 'Bill requested')
        ->assertSeeText('Bill requested');

    expect(collect($component->get('guestSteps'))->firstWhere('key', 'bill')['state'])->toBe('current');

    $tableSession->forceFill(['status' => TableSessionStatus::Paid])->save();

    $component
        ->call('refreshOrderStatuses')
        ->assertSet('overallStatusLabel', 'Paid')
        ->assertSeeText('Paid');

    expect(collect($component->get('guestSteps'))->firstWhere('key', 'paid')['state'])->toBe('current');
});

test('guest order polling query count stays constant as draft items grow', function (): void {
    [$tableSession, $guest, $qrCode] = createPrompt124TableSession();
    $draftOrder = DraftOrder::factory()
        ->for($tableSession)
        ->create(['status' => DraftOrderStatus::Draft]);
    DraftOrderItem::factory()
        ->for($draftOrder, 'draftOrder')
        ->for($guest, 'guest')
        ->create();
    $component = Livewire::withCookie(prompt124GuestCookieName($qrCode), $guest->guest_token)
        ->test(OrderStatuses::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $guest->id,
            'publicToken' => $qrCode->public_token,
            'pollingIntervalSeconds' => 1,
        ]);
    $initialQueryCount = countDatabaseQueries(
        fn () => $component->call('refreshOrderStatuses'),
    );

    DraftOrderItem::factory()
        ->count(20)
        ->for($draftOrder, 'draftOrder')
        ->for($guest, 'guest')
        ->create();

    $grownQueryCount = countDatabaseQueries(
        fn () => $component->call('refreshOrderStatuses'),
    );

    expect($initialQueryCount)->toBeLessThanOrEqual(12)
        ->and($grownQueryCount)->toBe($initialQueryCount);
});

function createPrompt124TableSession(): array
{
    $branch = Branch::factory()->create(['currency' => 'EUR']);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create(['name' => 'Стол 124']);
    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->create(['status' => TableSessionStatus::Active]);
    $guest = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Анна',
            'status' => TableSessionGuestStatus::Active,
        ]);
    $qrCode = QrCode::factory()
        ->for($servicePoint)
        ->create(['status' => QrCodeStatus::Active]);

    return [$tableSession, $guest, $qrCode];
}

function prompt124GuestCookieName(QrCode $qrCode): string
{
    return 'guest_token_'.substr(hash('sha256', $qrCode->public_token), 0, 24);
}

function createPrompt124Order(TableSession $tableSession, DraftOrder $draftOrder, OrderStatus $status): Order
{
    return Order::factory()
        ->for($tableSession)
        ->for($draftOrder, 'draftOrder')
        ->create([
            'branch_id' => $tableSession->branch_id,
            'service_point_id' => $tableSession->service_point_id,
            'table_session_id' => $tableSession->id,
            'draft_order_id' => $draftOrder->id,
            'status' => $status,
            'total_price_cents' => 1000,
        ]);
}

function createPrompt124OrderItem(Order $order, TableSessionGuest $guest, string $name): OrderItem
{
    return OrderItem::factory()
        ->for($order)
        ->for($guest, 'guest')
        ->create([
            'item_name' => $name,
            'item_name_snapshot' => $name,
            'guest_name' => $guest->guest_name,
            'guest_name_snapshot' => $guest->guest_name,
            'quantity' => 1,
            'unit_price_cents' => 1000,
            'unit_price_snapshot_cents' => 1000,
            'total_price_cents' => 1000,
        ]);
}

function createPrompt124TicketItem(
    KitchenTicket $ticket,
    OrderItem $orderItem,
    KitchenTicketItemStatus $status,
    mixed $servedAt = null,
): KitchenTicketItem {
    return KitchenTicketItem::factory()
        ->for($ticket, 'kitchenTicket')
        ->for($orderItem, 'orderItem')
        ->create([
            'table_session_guest_id' => $orderItem->table_session_guest_id,
            'menu_item_id' => $orderItem->menu_item_id,
            'guest_name' => $orderItem->guest_name,
            'item_name' => $orderItem->item_name,
            'quantity' => $orderItem->quantity,
            'status' => $status,
            'served_at' => $servedAt,
        ]);
}
