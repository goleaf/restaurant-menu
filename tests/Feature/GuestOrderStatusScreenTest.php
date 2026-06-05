<?php

use App\Enums\DraftOrderStatus;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\OrderStatus;
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
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use Livewire\Livewire;

test('guest sees friendly draft sent and waiter review statuses', function () {
    [$tableSession, $guest] = createPrompt124TableSession();
    $draftOrder = DraftOrder::factory()
        ->for($tableSession)
        ->create(['status' => DraftOrderStatus::Draft]);

    DraftOrderItem::factory()
        ->for($draftOrder, 'draftOrder')
        ->for($guest, 'guest')
        ->create([
            'item_name' => 'Маргарита',
            'quantity' => 2,
            'total_price' => '18.00',
        ]);

    $component = Livewire::test(OrderStatuses::class, [
        'tableSessionId' => $tableSession->id,
        'pollingIntervalSeconds' => 1,
    ])
        ->assertSet('overallStatusLabel', 'Вы выбираете')
        ->assertSet('itemStatuses.0.status_label', 'В черновике')
        ->assertSeeText('Вы выбираете')
        ->assertSeeText('Маргарита')
        ->assertSeeText('В черновике');

    expect(collect($component->get('guestSteps'))->firstWhere('key', 'draft')['state'])->toBe('current');

    $draftOrder->update([
        'status' => DraftOrderStatus::SentToWaiter,
        'sent_to_waiter_at' => now(),
        'sent_by_guest_id' => $guest->id,
    ]);

    $component
        ->call('refreshOrderStatuses')
        ->assertSet('overallStatusLabel', 'Отправлено официанту')
        ->assertSet('itemStatuses.0.status_label', 'Ждёт официанта')
        ->assertSeeText('Отправлено официанту')
        ->assertSeeText('Ждёт официанта');

    expect(collect($component->get('guestSteps'))->firstWhere('key', 'sent_to_waiter')['state'])->toBe('current');

    $draftOrder->update(['status' => DraftOrderStatus::WaiterReview]);

    $component
        ->call('refreshOrderStatuses')
        ->assertSet('overallStatusLabel', 'Официант проверяет')
        ->assertSet('itemStatuses.0.status_label', 'Официант проверяет')
        ->assertSeeText('Официант проверяет');

    expect(collect($component->get('guestSteps'))->firstWhere('key', 'waiter_review')['state'])->toBe('current');
});

test('guest sees accepted cooking ready and served item statuses', function () {
    [$tableSession, $guest] = createPrompt124TableSession();
    $draftOrder = DraftOrder::factory()
        ->for($tableSession)
        ->create(['status' => DraftOrderStatus::ConvertedToOrder]);
    $order = createPrompt124Order($tableSession, $draftOrder, OrderStatus::ConfirmedByWaiter);
    $acceptedItem = createPrompt124OrderItem($order, $guest, 'Суп дня');

    Livewire::test(OrderStatuses::class, [
        'tableSessionId' => $tableSession->id,
        'pollingIntervalSeconds' => 1,
    ])
        ->assertSet('overallStatusLabel', 'Заказ принят')
        ->assertSet('itemStatuses.0.status_label', 'Заказ принят')
        ->assertSeeText('Заказ принят')
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

    Livewire::test(OrderStatuses::class, [
        'tableSessionId' => $tableSession->id,
        'pollingIntervalSeconds' => 1,
    ])
        ->assertSet('overallStatusLabel', 'Готовится')
        ->assertSet('itemStatuses.0.status_label', 'Принято')
        ->assertSet('itemStatuses.1.status_label', 'Готовится')
        ->assertSet('itemStatuses.2.status_label', 'Готово')
        ->assertSet('itemStatuses.3.status_label', 'Подано')
        ->assertSeeText('Принято')
        ->assertSeeText('Готовится')
        ->assertSeeText('Готово')
        ->assertSeeText('Подано');
});

test('guest sees whole table bill and paid statuses', function () {
    [$tableSession] = createPrompt124TableSession();

    $tableSession->update(['status' => TableSessionStatus::PaymentRequested]);

    $component = Livewire::test(OrderStatuses::class, [
        'tableSessionId' => $tableSession->id,
        'pollingIntervalSeconds' => 1,
    ])
        ->assertSet('overallStatusLabel', 'Счёт запрошен')
        ->assertSeeText('Счёт запрошен');

    expect(collect($component->get('guestSteps'))->firstWhere('key', 'bill')['state'])->toBe('current');

    $tableSession->update(['status' => TableSessionStatus::Paid]);

    $component
        ->call('refreshOrderStatuses')
        ->assertSet('overallStatusLabel', 'Оплачено')
        ->assertSeeText('Оплачено');

    expect(collect($component->get('guestSteps'))->firstWhere('key', 'paid')['state'])->toBe('current');
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

    return [$tableSession, $guest];
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
            'total_price' => '10.00',
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
            'unit_price' => '10.00',
            'unit_price_snapshot' => '10.00',
            'total_price' => '10.00',
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
