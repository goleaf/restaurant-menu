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
        return [
            'order_item_id' => OrderItem::factory(),
            'kitchen_ticket_id' => function (array $attributes): int {
                $orderItem = $this->orderItemFor($attributes);

                return KitchenTicket::factory()
                    ->for($orderItem->order)
                    ->create([
                        'branch_id' => $orderItem->order->branch_id,
                        'service_point_id' => $orderItem->order->service_point_id,
                        'table_session_id' => $orderItem->order->table_session_id,
                    ])
                    ->id;
            },
            'table_session_guest_id' => fn (array $attributes): ?int => $this->orderItemFor($attributes)->table_session_guest_id,
            'menu_item_id' => fn (array $attributes): ?int => $this->orderItemFor($attributes)->menu_item_id,
            'guest_name' => fn (array $attributes): ?string => $this->orderItemFor($attributes)->guest_name,
            'item_name' => fn (array $attributes): string => $this->orderItemFor($attributes)->item_name,
            'quantity' => fn (array $attributes): int => $this->orderItemFor($attributes)->quantity,
            'status' => KitchenTicketItemStatus::New,
            'served_at' => null,
            'served_by_user_id' => null,
            'selected_modifiers' => fn (array $attributes): array => $this->orderItemFor($attributes)->selected_modifiers ?? [],
            'comment' => fn (array $attributes): ?string => $this->orderItemFor($attributes)->comment,
        ];
    }

    public function forDispatchedOrderItem(KitchenTicket $ticket, OrderItem $orderItem): static
    {
        return $this->state(fn (): array => [
            'kitchen_ticket_id' => $ticket->id,
            'order_item_id' => $orderItem->id,
            'table_session_guest_id' => $orderItem->table_session_guest_id,
            'menu_item_id' => $orderItem->menu_item_id,
            'guest_name' => $orderItem->guest_name,
            'item_name' => $orderItem->item_name,
            'quantity' => $orderItem->quantity,
            'selected_modifiers' => $orderItem->selected_modifiers ?? [],
            'comment' => $orderItem->comment,
        ]);
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function orderItemFor(array $attributes): OrderItem
    {
        return OrderItem::query()
            ->select([
                'id',
                'order_id',
                'table_session_guest_id',
                'menu_item_id',
                'guest_name',
                'item_name',
                'quantity',
                'selected_modifiers',
                'comment',
            ])
            ->whereKey($attributes['order_item_id'])
            ->firstOrFail();
    }
}
