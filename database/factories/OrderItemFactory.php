<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TableSessionGuest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'table_session_guest_id' => function (array $attributes): int {
                $order = Order::query()
                    ->select(['id', 'table_session_id'])
                    ->whereKey($attributes['order_id'])
                    ->firstOrFail();

                return TableSessionGuest::factory()
                    ->for($order->tableSession)
                    ->create()
                    ->id;
            },
            'menu_item_id' => null,
            'guest_name' => fake()->firstName(),
            'item_name' => fake()->words(3, true),
            'quantity' => 1,
            'unit_price' => '10.00',
            'modifier_total' => '0.00',
            'total_price' => '10.00',
            'selected_modifiers' => [],
            'comment' => null,
        ];
    }
}
