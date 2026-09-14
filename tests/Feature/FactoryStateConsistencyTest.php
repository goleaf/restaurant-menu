<?php

declare(strict_types=1);

use App\Enums\DraftOrderStatus;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\OrderStatus;
use App\Models\Branch;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\MenuItemVariant;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ServicePoint;
use App\Models\TableSession;

test('variant item factory defaults share the menu branch and parent session', function (string $model) {
    $variant = MenuItemVariant::factory()->create(['price_cents' => 725]);

    $item = $model::factory()->forVariant($variant)->create();
    $parent = $item instanceof DraftOrderItem ? $item->draftOrder : $item->order;

    expect($parent->tableSession->branch_id)->toBe($variant->item->menu->branch_id)
        ->and($item->guest->table_session_id)->toBe($parent->table_session_id)
        ->and($item->menu_item_id)->toBe($variant->menu_item_id)
        ->and($item->menu_item_variant_id)->toBe($variant->id)
        ->and($item->item_name)->toBe($variant->item->name)
        ->and($item->variant_name)->toBe($variant->name)
        ->and($item->unit_price_cents)->toBe(725)
        ->and($item->total_price_cents)->toBe(725)
        ->and(Branch::query()->count())->toBe(1);
})->with([
    'draft item' => DraftOrderItem::class,
    'order item' => OrderItem::class,
]);

test('variant item factories accept persisted variant collections with incomplete relationships', function (string $model, ?string $relations) {
    $createdVariants = MenuItemVariant::factory()->count(2)->create();
    $query = MenuItemVariant::query()->whereKey($createdVariants->modelKeys());

    if ($relations !== null) {
        $query->with($relations);
    }

    $variant = $query->get()->firstOrFail();

    expect($variant->preventsLazyLoading)->toBeTrue();

    $item = $model::factory()->forVariant($variant)->create();
    $parent = $item instanceof DraftOrderItem ? $item->draftOrder : $item->order;

    expect($item->menu_item_variant_id)->toBe($variant->id)
        ->and($parent->tableSession->branch_id)->toBe($variant->item->menu->branch_id);
})->with([
    'draft item' => DraftOrderItem::class,
    'order item' => OrderItem::class,
])->with([
    'unloaded relationships' => null,
    'loaded item' => 'item',
    'loaded item and menu' => 'item.menu',
]);

test('variant item factories reuse already eager loaded relationships without queries', function (string $model) {
    $createdVariants = MenuItemVariant::factory()->count(2)->create();
    $variant = MenuItemVariant::query()
        ->whereKey($createdVariants->modelKeys())
        ->with('item.menu.branch')
        ->get()
        ->firstOrFail();
    $originalItem = $variant->item;
    $originalMenu = $originalItem->menu;
    $originalBranch = $originalMenu->branch;

    $queries = countDatabaseQueries(fn () => $model::factory()->forVariant($variant));

    expect($queries)->toBe(0)
        ->and($variant->item)->toBe($originalItem)
        ->and($variant->item->menu)->toBe($originalMenu)
        ->and($variant->item->menu->branch)->toBe($originalBranch);
})->with([
    'draft item' => DraftOrderItem::class,
    'order item' => OrderItem::class,
]);

test('variant item factories preserve explicitly supplied compatible parents', function (string $model, string $assignment) {
    $variant = MenuItemVariant::factory()->create();
    $servicePoint = ServicePoint::factory()->forBranch($variant->item->menu->branch)->create();
    $session = TableSession::factory()->forServicePoint($servicePoint)->active()->create();
    $parent = $model === DraftOrderItem::class
        ? DraftOrder::factory()->forTableSession($session)->create()
        : Order::factory()->forTableSession($session)->create();
    $foreignKey = $model === DraftOrderItem::class ? 'draft_order_id' : 'order_id';

    $item = match ($assignment) {
        'before variant' => $model::factory()->for($parent)->forVariant($variant)->create(),
        'after variant' => $model::factory()->forVariant($variant)->for($parent)->create(),
        'foreign key' => $model::factory()->forVariant($variant)->create([$foreignKey => $parent->id]),
    };

    expect($item->{$foreignKey})->toBe($parent->id)
        ->and($item->guest->table_session_id)->toBe($session->id)
        ->and($item->menuItem->menu->branch_id)->toBe($session->branch_id)
        ->and(Branch::query()->count())->toBe(1);
})->with([
    'draft item' => DraftOrderItem::class,
    'order item' => OrderItem::class,
])->with(['before variant', 'after variant', 'foreign key']);

test('order factory with items persists the integer line total', function (int $count) {
    $order = Order::factory()->withItems($count)->create();

    expect($order->items()->count())->toBe($count)
        ->and($order->total_price_cents)->toBe($count * 1000)
        ->and($order->fresh()->total_price_cents)->toBe($count * 1000);
})->with([0, 1, 3]);

test('order factory with items includes other active lines and excludes cancelled lines from its total', function () {
    $order = Order::factory()
        ->has(OrderItem::factory()->state([
            'unit_price_cents' => 1375,
            'total_price_cents' => 1375,
        ]), 'items')
        ->has(OrderItem::factory()->cancelled()->state([
            'unit_price_cents' => 355,
            'total_price_cents' => 355,
        ]), 'items')
        ->withItems(2)
        ->create();

    expect($order->items()->count())->toBe(4)
        ->and($order->total_price_cents)->toBe(3375)
        ->and($order->fresh()->total_price_cents)->toBe(3375);
});

test('order factory department readiness preserves existing lines and synchronizes integer totals', function (int $existingCount, int $expectedCount, int $expectedTotal) {
    $order = Order::factory()
        ->has(OrderItem::factory()->count($existingCount)->state([
            'unit_price_cents' => 1375,
            'total_price_cents' => 1375,
        ]), 'items')
        ->withDepartmentReadiness(2)
        ->create();

    expect($order->items()->count())->toBe($expectedCount)
        ->and($order->items()->where('unit_price_cents', 1375)->count())->toBe($existingCount)
        ->and($order->kitchenTicketItems()->count())->toBe(2)
        ->and($order->kitchenTicketItems()->where('kitchen_ticket_items.status', KitchenTicketItemStatus::New)->count())->toBe(2)
        ->and((int) $order->items()->active()->sum('total_price_cents'))->toBe($expectedTotal)
        ->and($order->total_price_cents)->toBe($expectedTotal)
        ->and($order->fresh()->total_price_cents)->toBe($expectedTotal);
})->with([
    'default lines' => [0, 2, 2000],
    'one existing line' => [1, 2, 2375],
    'more existing lines than tickets' => [3, 3, 4125],
]);

test('order factory creates a converted source draft for its default parent graph', function (bool $explicitSession) {
    $factory = Order::factory();

    if ($explicitSession) {
        $factory = $factory->forTableSession(TableSession::factory()->active()->create());
    }

    $order = $factory->create();

    expect($order->status)->toBe(OrderStatus::ConfirmedByWaiter)
        ->and($order->draftOrder->table_session_id)->toBe($order->table_session_id)
        ->and($order->draftOrder->status)->toBe(DraftOrderStatus::ConvertedToOrder)
        ->and($order->draftOrder->sent_to_waiter_at)->not->toBeNull()
        ->and($order->draftOrder->converted_to_order_at)->not->toBeNull();
})->with(['default session' => false, 'supplied session' => true]);

test('order factory preserves explicitly supplied draft lifecycle fixtures', function () {
    $draft = DraftOrder::factory()->waiterReview()->create();

    $order = Order::factory()
        ->forTableSession($draft->tableSession)
        ->create(['draft_order_id' => $draft->id]);

    expect($order->draft_order_id)->toBe($draft->id)
        ->and($draft->fresh()->status)->toBe(DraftOrderStatus::WaiterReview)
        ->and($draft->fresh()->converted_to_order_at)->toBeNull();
});
