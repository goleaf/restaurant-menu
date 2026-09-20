<?php

declare(strict_types=1);

use App\Actions\Orders\SyncOrderStatusFromTicketItemsAction;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\OrderStatus;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionStatus;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ServicePoint;
use App\Models\User;

function preparationAggregateItem(Order $order, KitchenTicketItemStatus $status, ?KitchenTicket $ticket = null): KitchenTicketItem
{
    $ticket ??= KitchenTicket::factory()->forOrder($order)->create();
    $orderItem = OrderItem::factory()->for($order)->create(['table_session_guest_id' => null]);

    return KitchenTicketItem::factory()->forDispatchedOrderItem($ticket, $orderItem)->create(['status' => $status]);
}

test('order readiness considers every item beyond the former thousand row limit', function (): void {
    $order = Order::factory()->preparing()->create();
    $ticket = KitchenTicket::factory()->forOrder($order)->create();
    $orderItems = OrderItem::factory()->count(1000)->for($order)->create(['table_session_guest_id' => null]);
    foreach ($orderItems as $orderItem) {
        KitchenTicketItem::factory()->forDispatchedOrderItem($ticket, $orderItem)->ready()->create();
    }
    preparationAggregateItem($order, KitchenTicketItemStatus::InProgress, $ticket);

    $result = app(SyncOrderStatusFromTicketItemsAction::class)->handle($order, User::factory()->create());

    expect($result->status)->toBe(OrderStatus::InProgress);
});

test('serving one order preserves preparation of another order in the same visit', function (): void {
    $order = Order::factory()->ready()->create();
    $session = $order->tableSession;
    $order->servicePoint->forceFill(['status' => ServicePointStatus::ReadyToServe])->save();
    $servedItem = preparationAggregateItem($order, KitchenTicketItemStatus::Ready);
    $servedItem->forceFill(['served_at' => now()])->save();
    $additionalOrder = Order::factory()->forTableSession($session)->preparing()->create();
    preparationAggregateItem($additionalOrder, KitchenTicketItemStatus::InProgress);

    app(SyncOrderStatusFromTicketItemsAction::class)->handle($order, User::factory()->create());

    expect($order->fresh()->status)->toBe(OrderStatus::Served)
        ->and($additionalOrder->fresh()->status)->toBe(OrderStatus::InProgress)
        ->and($order->servicePoint->fresh()->status)->toBe(ServicePointStatus::Cooking);
});

test('production synchronization follows the active serving place and preserves the original place', function (): void {
    $order = Order::factory()->preparing()->create();
    $original = $order->servicePoint;
    $original->forceFill(['status' => ServicePointStatus::Occupied])->save();
    $destination = ServicePoint::factory()->for($order->branch)->create(['status' => ServicePointStatus::Cooking]);
    $order->tableSession->forceFill(['service_point_id' => $destination->id])->save();
    preparationAggregateItem($order, KitchenTicketItemStatus::Ready);

    app(SyncOrderStatusFromTicketItemsAction::class)->handle($order, User::factory()->create());

    expect($destination->fresh()->status)->toBe(ServicePointStatus::ReadyToServe)
        ->and($original->fresh()->status)->toBe(ServicePointStatus::Occupied);
});

test('production synchronization never revives a terminal visit', function (TableSessionStatus $status): void {
    $order = Order::factory()->preparing()->create();
    $order->servicePoint->forceFill(['status' => ServicePointStatus::Occupied])->save();
    $order->tableSession->forceFill(['status' => $status])->save();
    preparationAggregateItem($order, KitchenTicketItemStatus::Ready);

    app(SyncOrderStatusFromTicketItemsAction::class)->handle($order, User::factory()->create());

    expect($order->fresh()->status)->toBe(OrderStatus::InProgress)
        ->and($order->servicePoint->fresh()->status)->toBe(ServicePointStatus::Occupied);
})->with([TableSessionStatus::Closed, TableSessionStatus::Cancelled, TableSessionStatus::Paid]);

test('an entirely cancelled ticket is not a successfully prepared order', function (): void {
    $order = Order::factory()->preparing()->create();
    preparationAggregateItem($order, KitchenTicketItemStatus::Cancelled);

    app(SyncOrderStatusFromTicketItemsAction::class)->handle($order, User::factory()->create());

    expect($order->fresh()->status)->toBe(OrderStatus::InProgress);
});
