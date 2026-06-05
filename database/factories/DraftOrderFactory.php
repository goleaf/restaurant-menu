<?php

namespace Database\Factories;

use App\Enums\DraftOrderStatus;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\TableSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DraftOrder>
 */
class DraftOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'table_session_id' => TableSession::factory(),
            'status' => DraftOrderStatus::Draft,
            'sent_to_waiter_at' => null,
            'sent_by_guest_id' => null,
            'rejected_at' => null,
            'rejected_by_user_id' => null,
            'rejection_reason' => null,
            'converted_to_order_at' => null,
            'converted_by_user_id' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => DraftOrderStatus::Draft,
            'sent_to_waiter_at' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'converted_to_order_at' => null,
        ]);
    }

    public function sentToWaiter(): static
    {
        return $this->state(fn (): array => [
            'status' => DraftOrderStatus::SentToWaiter,
            'sent_to_waiter_at' => now(),
            'rejected_at' => null,
            'rejection_reason' => null,
            'converted_to_order_at' => null,
        ]);
    }

    public function waiterReview(): static
    {
        return $this->state(fn (): array => [
            'status' => DraftOrderStatus::WaiterReview,
            'sent_to_waiter_at' => now(),
            'rejected_at' => null,
            'rejection_reason' => null,
            'converted_to_order_at' => null,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => DraftOrderStatus::Rejected,
            'sent_to_waiter_at' => now()->subMinute(),
            'rejected_at' => now(),
            'rejection_reason' => 'Factory rejection reason.',
            'converted_to_order_at' => null,
        ]);
    }

    public function convertedToOrder(): static
    {
        return $this->state(fn (): array => [
            'status' => DraftOrderStatus::ConvertedToOrder,
            'sent_to_waiter_at' => now()->subMinute(),
            'rejected_at' => null,
            'rejection_reason' => null,
            'converted_to_order_at' => now(),
        ]);
    }

    public function forTableSession(TableSession $tableSession): static
    {
        return $this->state(fn (): array => [
            'table_session_id' => $tableSession->id,
        ]);
    }

    public function withItems(int $count = 1): static
    {
        return $this->afterCreating(function (DraftOrder $draftOrder) use ($count): void {
            DraftOrderItem::factory()
                ->count($count)
                ->for($draftOrder)
                ->create();
        });
    }
}
