<?php

namespace Database\Factories;

use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\MenuItem;
use App\Models\TableSessionGuest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DraftOrderItem>
 */
class DraftOrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'draft_order_id' => DraftOrder::factory(),
            'table_session_guest_id' => function (array $attributes): int {
                $draftOrder = DraftOrder::query()
                    ->select(['id', 'table_session_id'])
                    ->whereKey($attributes['draft_order_id'])
                    ->firstOrFail();

                return TableSessionGuest::factory()
                    ->for($draftOrder->tableSession)
                    ->create()
                    ->id;
            },
            'menu_item_id' => MenuItem::factory(),
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
