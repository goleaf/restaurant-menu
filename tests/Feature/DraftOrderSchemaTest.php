<?php

use App\Enums\DraftOrderStatus;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\MenuItem;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
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
            'menu_item_variant_id',
            'item_name',
            'variant_name',
            'variant_type',
            'quantity',
            'unit_price_cents',
            'modifier_total_cents',
            'total_price_cents',
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
        'price_cents' => 1250,
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
            'unit_price_cents' => 1250,
            'modifier_total_cents' => 0,
            'total_price_cents' => 1250,
            'selected_modifiers' => [],
        ]);
    $anaItem = DraftOrderItem::factory()
        ->for($draftOrder)
        ->for($ana, 'guest')
        ->for($menuItem, 'menuItem')
        ->create([
            'item_name' => 'Margherita',
            'quantity' => 1,
            'unit_price_cents' => 725,
            'modifier_total_cents' => 0,
            'total_price_cents' => 725,
            'selected_modifiers' => [
                [
                    'group' => 'Pizza size',
                    'option' => 'Small',
                    'price_delta_cents' => 0,
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
            'unit_price_cents' => 275,
            'modifier_total_cents' => 0,
            'total_price_cents' => 275,
        ]);

    $draftOrder = $draftOrder->fresh()->load(['items.guest']);

    expect($tableSession->fresh()->draftOrder->is($draftOrder))->toBeTrue()
        ->and($ana->draftOrderItems()->pluck('draft_order_items.id')->all())->toHaveCount(2)
        ->and($anaItem->fresh()->selected_modifiers)->toBe([
            [
                'group' => 'Pizza size',
                'option' => 'Small',
                'price_delta_cents' => 0,
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

test('table session can keep repeat draft history and expose the latest draft order', function () {
    $tableSession = TableSession::factory()->active()->create();

    $firstDraftOrder = DraftOrder::factory()
        ->for($tableSession)
        ->create([
            'status' => DraftOrderStatus::ConvertedToOrder,
            'converted_to_order_at' => now(),
        ]);
    $secondDraftOrder = DraftOrder::factory()
        ->for($tableSession)
        ->create(['status' => DraftOrderStatus::Draft]);

    expect(DraftOrder::query()->where('table_session_id', $tableSession->id)->orderBy('id')->get())->toHaveCount(2)
        ->and($tableSession->fresh()->draftOrder->is($secondDraftOrder))->toBeTrue()
        ->and($tableSession->fresh()->draftOrders()->reorder()->oldest('id')->first()?->is($firstDraftOrder))->toBeTrue();
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
