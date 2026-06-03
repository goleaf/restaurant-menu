<?php

namespace Database\Factories;

use App\Enums\TableSessionJoinRequestStatus;
use App\Models\TableSession;
use App\Models\TableSessionJoinRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TableSessionJoinRequest>
 */
class TableSessionJoinRequestFactory extends Factory
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
            'status' => TableSessionJoinRequestStatus::Pending,
            'approved_by_guest_id' => null,
            'rejected_by_guest_id' => null,
            'approved_by_user_id' => null,
            'rejected_by_user_id' => null,
            'expires_at' => now()->addMinutes(30),
        ];
    }
}
