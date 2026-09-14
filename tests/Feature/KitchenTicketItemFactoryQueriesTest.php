<?php

declare(strict_types=1);

use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\OrderItem;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

test('ticket item factory reads each source snapshot once', function (): void {
    $reads = 0;
    DB::listen(function (QueryExecuted $query) use (&$reads): void {
        if (str_starts_with($query->sql, 'select') && str_contains($query->sql, 'from "order_items"')) {
            $reads++;
        }
    });

    $items = KitchenTicketItem::factory()->count(2)->create();

    expect($reads)->toBe(2)
        ->and($items->pluck('order_item_id')->unique())->toHaveCount(2);

    $items->load(['orderItem', 'kitchenTicket']);

    foreach ($items as $item) {
        expect($item->item_name)->toBe($item->orderItem->item_name)
            ->and($item->quantity)->toBe($item->orderItem->quantity)
            ->and($item->orderItem->order_id)->toBe($item->kitchenTicket->order_id);
    }
});

test('reusing a ticket item factory reads updated source values and preserves explicit states', function (): void {
    $source = OrderItem::factory()->create(['item_name' => 'Initial dish', 'quantity' => 1]);
    $ticket = KitchenTicket::factory()->for($source->order)->create();
    $factory = KitchenTicketItem::factory()->state([
        'order_item_id' => $source->id,
        'kitchen_ticket_id' => $ticket->id,
    ]);
    $first = $factory->make();
    $source->update(['item_name' => 'Updated dish', 'quantity' => 3]);
    $second = $factory->make(['guest_name' => 'Explicit guest']);

    expect($first->item_name)->toBe('Initial dish')
        ->and($first->quantity)->toBe(1)
        ->and($second->item_name)->toBe('Updated dish')
        ->and($second->quantity)->toBe(3)
        ->and($second->guest_name)->toBe('Explicit guest');
});
