<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\TableSession;
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
            'menu_item_id' => function (array $attributes): int {
                $draftOrder = DraftOrder::query()
                    ->select(['id', 'table_session_id'])
                    ->whereKey($attributes['draft_order_id'])
                    ->firstOrFail();
                $tableSession = TableSession::query()
                    ->select(['id', 'branch_id'])
                    ->whereKey($draftOrder->table_session_id)
                    ->firstOrFail();
                $menu = Menu::factory()
                    ->active()
                    ->create(['branch_id' => $tableSession->branch_id]);

                return MenuItem::factory()
                    ->create(['menu_id' => $menu->id])
                    ->id;
            },
            'menu_item_variant_id' => null,
            'item_name' => fake()->words(3, true),
            'variant_name' => null,
            'variant_type' => null,
            'quantity' => 1,
            'unit_price_cents' => 1000,
            'modifier_total_cents' => 0,
            'total_price_cents' => 1000,
            'selected_modifiers' => [],
            'comment' => null,
        ];
    }

    public function forVariant(MenuItemVariant $variant): static
    {
        return $this->state(fn (): array => [
            'menu_item_id' => $variant->menu_item_id,
            'menu_item_variant_id' => $variant->id,
            'item_name' => $variant->item->name,
            'variant_name' => $variant->name,
            'variant_type' => $variant->type,
            'unit_price_cents' => $variant->price_cents,
            'total_price_cents' => $variant->price_cents,
        ]);
    }
}
