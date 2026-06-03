<?php

namespace Database\Factories;

use App\Enums\DraftOrderStatus;
use App\Models\DraftOrder;
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
        ];
    }
}
