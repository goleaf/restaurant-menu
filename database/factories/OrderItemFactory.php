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
            'original_menu_item_id' => fn (array $attributes): ?int => $attributes['menu_item_id'] ?? null,
            'kitchen_department_id' => null,
            'kitchen_department_type' => null,
            'kitchen_department_name' => null,
            'guest_name' => fake()->firstName(),
            'guest_name_snapshot' => fn (array $attributes): ?string => $attributes['guest_name'] ?? null,
            'item_name' => fake()->words(3, true),
            'item_name_snapshot' => fn (array $attributes): ?string => $attributes['item_name'] ?? null,
            'item_description_snapshot' => null,
            'quantity' => 1,
            'unit_price' => '10.00',
            'unit_price_snapshot' => fn (array $attributes): string => (string) ($attributes['unit_price'] ?? '10.00'),
            'modifier_total' => '0.00',
            'total_price' => '10.00',
            'selected_modifiers' => [],
            'modifiers_snapshot' => fn (array $attributes): array => $attributes['selected_modifiers'] ?? [],
            'tax_snapshot' => [],
            'service_snapshot' => [],
            'comment' => null,
        ];
    }
}
