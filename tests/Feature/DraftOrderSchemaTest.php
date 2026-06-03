<?php

use App\Enums\DraftOrderStatus;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\MenuItem;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

test('draft order tables expose the required shared draft columns', function () {
    expect(Schema::hasTable('draft_orders'))->toBeTrue()
        ->and(Schema::hasColumns('draft_orders', [
            'id',
            'table_session_id',
            'status',
            'sent_to_waiter_at',
            'sent_by_guest_id',
            'created_at',
            'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasTable('draft_order_items'))->toBeTrue()
        ->and(Schema::hasColumns('draft_order_items', [
            'id',
            'draft_order_id',
            'table_session_guest_id',
            'menu_item_id',
            'item_name',
            'quantity',
            'unit_price',
            'modifier_total',
            'total_price',
            'selected_modifiers',
            'comment',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
});

test('draft order statuses include the fixed table draft lifecycle', function () {
    expect(DraftOrderStatus::values())->toBe([
        'draft',
        'sent_to_waiter',
        'waiter_review',
        'rejected',
        'converted_to_order',
    ]);
});

test('table session has one common draft order with guest item totals sorted alphabetically', function () {
    $tableSession = TableSession::factory()->active()->create();
    $zara = TableSessionGuest::factory()
        ->for($tableSession)
        ->create(['guest_name' => 'Zara']);
    $ana = TableSessionGuest::factory()
        ->for($tableSession)
        ->create(['guest_name' => 'Ana']);
    $menuItem = MenuItem::factory()->create([
        'name' => 'Margherita',
        'price' => '12.50',
    ]);
    $draftOrder = DraftOrder::factory()
        ->for($tableSession)
        ->create();

    DraftOrderItem::factory()
        ->for($draftOrder)
        ->for($zara, 'guest')
        ->for($menuItem, 'menuItem')
        ->create([
            'item_name' => 'Margherita',
            'quantity' => 1,
            'unit_price' => '12.50',
            'modifier_total' => '0.00',
            'total_price' => '12.50',
            'selected_modifiers' => [],
        ]);
    $anaItem = DraftOrderItem::factory()
        ->for($draftOrder)
        ->for($ana, 'guest')
        ->for($menuItem, 'menuItem')
        ->create([
            'item_name' => 'Margherita',
            'quantity' => 1,
            'unit_price' => '7.25',
            'modifier_total' => '0.00',
            'total_price' => '7.25',
            'selected_modifiers' => [
                [
                    'group' => 'Pizza size',
                    'option' => 'Small',
                    'price_delta' => '0.00',
                ],
            ],
            'comment' => 'No garlic',
        ]);
    DraftOrderItem::factory()
        ->for($draftOrder)
        ->for($ana, 'guest')
        ->for($menuItem, 'menuItem')
        ->create([
            'item_name' => 'Water',
            'quantity' => 1,
            'unit_price' => '2.75',
            'modifier_total' => '0.00',
            'total_price' => '2.75',
        ]);

    $draftOrder = $draftOrder->fresh()->load(['items.guest']);

    expect($tableSession->fresh()->draftOrder->is($draftOrder))->toBeTrue()
        ->and($ana->draftOrderItems()->pluck('draft_order_items.id')->all())->toHaveCount(2)
        ->and($anaItem->fresh()->selected_modifiers)->toBe([
            [
                'group' => 'Pizza size',
                'option' => 'Small',
                'price_delta' => '0.00',
            ],
        ])
        ->and($draftOrder->guestTotals())->toBe([
            [
                'guest_id' => $ana->id,
                'guest_name' => 'Ana',
                'total' => '10.00',
            ],
            [
                'guest_id' => $zara->id,
                'guest_name' => 'Zara',
                'total' => '12.50',
            ],
        ])
        ->and($draftOrder->totalAmount())->toBe('22.50');
});

test('table session cannot receive a second common draft order', function () {
    $tableSession = TableSession::factory()->active()->create();

    DraftOrder::factory()
        ->for($tableSession)
        ->create();

    expect(fn () => DraftOrder::factory()
        ->for($tableSession)
        ->create()
    )->toThrow(QueryException::class);
});

test('draft order tracks the guest who sent the shared draft to waiter review', function () {
    $tableSession = TableSession::factory()->active()->create();
    $guest = TableSessionGuest::factory()
        ->for($tableSession)
        ->create(['guest_name' => 'Sender']);
    $sentAt = now()->startOfSecond();

    $draftOrder = DraftOrder::factory()
        ->for($tableSession)
        ->for($guest, 'sentByGuest')
        ->create([
            'status' => DraftOrderStatus::SentToWaiter,
            'sent_to_waiter_at' => $sentAt,
        ]);

    expect($draftOrder->fresh()->status)->toBe(DraftOrderStatus::SentToWaiter)
        ->and($draftOrder->fresh()->sent_to_waiter_at?->equalTo($sentAt))->toBeTrue()
        ->and($draftOrder->fresh()->sentByGuest->is($guest))->toBeTrue();
});
