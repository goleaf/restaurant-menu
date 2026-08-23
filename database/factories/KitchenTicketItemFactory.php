<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\KitchenTicketItemStatus;
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
            'status' => KitchenTicketItemStatus::New,
            'served_at' => null,
            'served_by_user_id' => null,
            'selected_modifiers' => $orderItem->selected_modifiers ?? [],
            'comment' => $orderItem->comment,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => KitchenTicketItemStatus::New,
            'served_at' => null,
            'served_by_user_id' => null,
        ]);
    }

    public function preparing(): static
    {
        return $this->state(fn (): array => [
            'status' => KitchenTicketItemStatus::InProgress,
            'served_at' => null,
            'served_by_user_id' => null,
        ]);
    }

    public function ready(): static
    {
        return $this->state(fn (): array => [
            'status' => KitchenTicketItemStatus::Ready,
            'served_at' => null,
            'served_by_user_id' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => KitchenTicketItemStatus::Cancelled,
            'served_at' => null,
            'served_by_user_id' => null,
        ]);
    }
}
