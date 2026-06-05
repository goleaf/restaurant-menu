<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\DraftOrder;
use App\Models\Order;
use App\Models\TableSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tableSession = TableSession::factory()->active()->create();

        return [
            'branch_id' => $tableSession->branch_id,
            'service_point_id' => $tableSession->service_point_id,
            'table_session_id' => $tableSession->id,
            'draft_order_id' => DraftOrder::factory()->for($tableSession),
            'status' => OrderStatus::ConfirmedByWaiter,
            'confirmed_by_user_id' => null,
            'confirmed_at' => now(),
            'total_price' => '0.00',
            'currency' => 'EUR',
            'metadata' => [],
        ];
    }

    public function confirmedByWaiter(): static
    {
        return $this->state(fn (): array => [
            'status' => OrderStatus::ConfirmedByWaiter,
            'confirmed_at' => now(),
        ]);
    }

    public function sentToDepartments(): static
    {
        return $this->state(fn (): array => [
            'status' => OrderStatus::SentToKitchenBar,
            'confirmed_at' => now()->subMinute(),
        ]);
    }

    public function preparing(): static
    {
        return $this->state(fn (): array => [
            'status' => OrderStatus::InProgress,
            'confirmed_at' => now()->subMinutes(5),
        ]);
    }

    public function ready(): static
    {
        return $this->state(fn (): array => [
            'status' => OrderStatus::Ready,
            'confirmed_at' => now()->subMinutes(10),
        ]);
    }

    public function served(): static
    {
        return $this->state(fn (): array => [
            'status' => OrderStatus::Served,
            'confirmed_at' => now()->subMinutes(15),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => OrderStatus::Cancelled,
            'confirmed_at' => now()->subMinutes(15),
        ]);
    }
}
