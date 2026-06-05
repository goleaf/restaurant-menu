<?php

namespace Database\Factories;

use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketStatus;
use App\Models\KitchenTicket;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KitchenTicket>
 */
class KitchenTicketFactory extends Factory
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
            'kitchen_department_id' => null,
            'department_type' => KitchenDepartmentType::Kitchen->value,
            'department_name' => 'Kitchen',
            'status' => KitchenTicketStatus::Sent,
            'sent_by_user_id' => null,
            'sent_at' => now(),
            'metadata' => [],
        ];
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn (): array => [
            'order_id' => $order->id,
            'branch_id' => $order->branch_id,
            'service_point_id' => $order->service_point_id,
            'table_session_id' => $order->table_session_id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function orderFor(array $attributes): Order
    {
        return Order::query()
            ->select(['id', 'branch_id', 'service_point_id', 'table_session_id'])
            ->whereKey($attributes['order_id'])
            ->firstOrFail();
    }
}
