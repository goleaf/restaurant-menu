<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MenuItemVariant;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TableSessionGuest;
use App\Models\User;
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
            'menu_item_variant_id' => null,
            'original_menu_item_id' => fn (array $attributes): ?int => $attributes['menu_item_id'] ?? null,
            'kitchen_department_id' => null,
            'kitchen_department_type' => null,
            'kitchen_department_name' => null,
            'guest_name' => fake()->firstName(),
            'guest_name_snapshot' => fn (array $attributes): ?string => $attributes['guest_name'] ?? null,
            'item_name' => fake()->words(3, true),
            'item_name_snapshot' => fn (array $attributes): ?string => $attributes['item_name'] ?? null,
            'item_description_snapshot' => null,
            'variant_name' => null,
            'variant_type' => null,
            'quantity' => 1,
            'unit_price_cents' => 1000,
            'unit_price_snapshot_cents' => fn (array $attributes): int => (int) ($attributes['unit_price_cents'] ?? 1000),
            'modifier_total_cents' => 0,
            'total_price_cents' => 1000,
            'selected_modifiers' => [],
            'modifiers_snapshot' => fn (array $attributes): array => $attributes['selected_modifiers'] ?? [],
            'tax_snapshot' => [],
            'service_snapshot' => [],
            'comment' => null,
            'cancelled_at' => null,
            'cancelled_by_user_id' => null,
            'cancellation_reason' => null,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'cancelled_at' => now(),
            'cancelled_by_user_id' => null,
            'cancellation_reason' => 'Cancelled for a test scenario.',
        ]);
    }

    public function forVariant(MenuItemVariant $variant): static
    {
        return $this->state(fn (): array => [
            'menu_item_id' => $variant->menu_item_id,
            'menu_item_variant_id' => $variant->id,
            'original_menu_item_id' => $variant->menu_item_id,
            'item_name' => $variant->item->name,
            'item_name_snapshot' => $variant->item->name,
            'variant_name' => $variant->name,
            'variant_type' => $variant->type,
            'unit_price_cents' => $variant->price_cents,
            'unit_price_snapshot_cents' => $variant->price_cents,
            'total_price_cents' => $variant->price_cents,
        ]);
    }

    public function cancelledBy(User $user, string $reason = 'Cancelled for a test scenario.'): static
    {
        return $this->state(fn (): array => [
            'cancelled_at' => now(),
            'cancelled_by_user_id' => $user->id,
            'cancellation_reason' => $reason,
        ]);
    }
}
