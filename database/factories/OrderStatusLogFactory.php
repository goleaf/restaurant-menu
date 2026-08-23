<?php

declare(strict_types=1);

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
        return [
            'order_id' => Order::factory(),
            'branch_id' => fn (array $attributes): int => $this->orderFor($attributes)->branch_id,
            'service_point_id' => fn (array $attributes): int => $this->orderFor($attributes)->service_point_id,
            'table_session_id' => fn (array $attributes): int => $this->orderFor($attributes)->table_session_id,
            'draft_order_id' => fn (array $attributes): int => $this->orderFor($attributes)->draft_order_id,
            'actor_user_id' => User::factory(),
            'actor_guest_id' => null,
            'actor_type' => 'user',
            'actor_name' => fn (array $attributes): string => $this->actorFor($attributes)->name,
            'event' => OrderStatusLogEvent::OrderStatusChanged,
            'status_type' => 'order',
            'previous_status' => OrderStatus::ConfirmedByWaiter->value,
            'new_status' => OrderStatus::InProgress->value,
            'reason' => null,
            'metadata' => [],
            'occurred_at' => now(),
        ];
    }

    public function forOrderTransition(
        Order $order,
        User $actor,
        OrderStatus $previousStatus,
        OrderStatus $newStatus,
    ): static {
        return $this->state(fn (): array => [
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
            'previous_status' => $previousStatus->value,
            'new_status' => $newStatus->value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function orderFor(array $attributes): Order
    {
        return Order::query()
            ->select(['id', 'branch_id', 'service_point_id', 'table_session_id', 'draft_order_id'])
            ->whereKey($attributes['order_id'])
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function actorFor(array $attributes): User
    {
        return User::query()
            ->select(['id', 'name'])
            ->whereKey($attributes['actor_user_id'])
            ->firstOrFail();
    }
}
