<?php

namespace Database\Factories;

use App\Enums\SupportedLocale;
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
            'locale' => SupportedLocale::English->value,
            'status' => TableSessionGuestStatus::Active,
            'ready_at' => null,
            'joined_at' => now(),
            'left_at' => null,
            'metadata' => [],
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => TableSessionGuestStatus::Active,
            'joined_at' => now(),
            'left_at' => null,
        ]);
    }

    public function pendingApproval(): static
    {
        return $this->state(fn (): array => [
            'status' => TableSessionGuestStatus::PendingApproval,
            'joined_at' => null,
            'left_at' => null,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => TableSessionGuestStatus::Rejected,
            'joined_at' => null,
            'left_at' => now(),
        ]);
    }

    public function removed(): static
    {
        return $this->state(fn (): array => [
            'status' => TableSessionGuestStatus::Removed,
            'left_at' => now(),
        ]);
    }

    public function left(): static
    {
        return $this->state(fn (): array => [
            'status' => TableSessionGuestStatus::Left,
            'left_at' => now(),
        ]);
    }
}
