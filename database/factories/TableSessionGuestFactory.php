<?php

namespace Database\Factories;

use App\Enums\TableSessionGuestStatus;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TableSessionGuest>
 */
class TableSessionGuestFactory extends Factory
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
            'name' => fake()->firstName(),
            'status' => TableSessionGuestStatus::Active,
            'joined_at' => now(),
            'left_at' => null,
            'metadata' => [],
        ];
    }
}
