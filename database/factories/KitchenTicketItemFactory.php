<?php

namespace Database\Factories;

use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KitchenTicketItem>
 */
class KitchenTicketItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $orderItem = OrderItem::factory()->create();

        return [
            'kitchen_ticket_id' => function () use ($orderItem): int {
                return KitchenTicket::factory()
                    ->for($orderItem->order)
                    ->create([
                        'branch_id' => $orderItem->order->branch_id,
                        'service_point_id' => $orderItem->order->service_point_id,
                        'table_session_id' => $orderItem->order->table_session_id,
                    ])
                    ->id;
            },
            'order_item_id' => $orderItem->id,
            'table_session_guest_id' => $orderItem->table_session_guest_id,
            'menu_item_id' => $orderItem->menu_item_id,
            'guest_name' => $orderItem->guest_name,
            'item_name' => $orderItem->item_name,
            'quantity' => $orderItem->quantity,
            'selected_modifiers' => $orderItem->selected_modifiers ?? [],
            'comment' => $orderItem->comment,
        ];
    }
}
