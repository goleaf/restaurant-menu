<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\DraftOrder;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\Order;
use App\Models\OrderItem;
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
        return [
            'table_session_id' => TableSession::factory()->active(),
            'branch_id' => fn (array $attributes): int => $this->tableSessionFor($attributes)->branch_id,
            'service_point_id' => fn (array $attributes): int => $this->tableSessionFor($attributes)->service_point_id,
            'draft_order_id' => fn (array $attributes): int => DraftOrder::factory()
                ->forTableSession($this->tableSessionFor($attributes))
                ->create()
                ->id,
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

    public function forTableSession(TableSession $tableSession): static
    {
        return $this->state(fn (): array => [
            'branch_id' => $tableSession->branch_id,
            'service_point_id' => $tableSession->service_point_id,
            'table_session_id' => $tableSession->id,
            'draft_order_id' => DraftOrder::factory()->forTableSession($tableSession),
        ]);
    }

    public function withItems(int $count = 1): static
    {
        return $this->afterCreating(function (Order $order) use ($count): void {
            OrderItem::factory()
                ->count($count)
                ->for($order)
                ->create();
        });
    }

    public function withDepartmentReadiness(int $count = 1): static
    {
        return $this->afterCreating(function (Order $order) use ($count): void {
            $ticket = KitchenTicket::factory()
                ->forOrder($order)
                ->create();

            $items = $order->items()
                ->take($count)
                ->get();

            while ($items->count() < $count) {
                $items->push(
                    OrderItem::factory()
                        ->for($order)
                        ->create()
                );
            }

            $items->each(function (OrderItem $orderItem) use ($ticket): void {
                KitchenTicketItem::factory()
                    ->for($orderItem, 'orderItem')
                    ->for($ticket, 'kitchenTicket')
                    ->pending()
                    ->create();
            });
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function tableSessionFor(array $attributes): TableSession
    {
        return TableSession::query()
            ->select(['id', 'branch_id', 'service_point_id'])
            ->whereKey($attributes['table_session_id'])
            ->firstOrFail();
    }
}
