<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\OrderStatusLogEvent;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderStatusLog>
 */
class OrderStatusLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $order = Order::factory()->create();
        $actor = User::factory()->create();

        return [
            'branch_id' => $order->branch_id,
            'service_point_id' => $order->service_point_id,
            'table_session_id' => $order->table_session_id,
            'draft_order_id' => $order->draft_order_id,
            'order_id' => $order->id,
            'actor_user_id' => $actor->id,
            'actor_guest_id' => null,
            'actor_type' => 'user',
            'actor_name' => $actor->name,
            'event' => OrderStatusLogEvent::OrderStatusChanged,
            'status_type' => 'order',
            'previous_status' => OrderStatus::ConfirmedByWaiter->value,
            'new_status' => OrderStatus::InProgress->value,
            'reason' => null,
            'metadata' => [],
            'occurred_at' => now(),
        ];
    }
}
