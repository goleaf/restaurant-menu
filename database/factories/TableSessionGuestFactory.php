<?php

namespace Database\Factories;

use App\Enums\TableSessionGuestStatus;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
            'guest_name' => fake()->firstName(),
            'guest_token' => Str::random(64),
            'status' => TableSessionGuestStatus::Active,
            'ready_at' => null,
            'joined_at' => now(),
            'left_at' => null,
            'metadata' => [],
        ];
    }
}
