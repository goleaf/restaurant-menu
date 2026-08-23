<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Branch;
use App\Models\BranchOpeningHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BranchOpeningHour>
 */
class BranchOpeningHourFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'day_of_week' => fake()->numberBetween(1, 7),
            'is_closed' => false,
            'opens_at' => '10:00',
            'closes_at' => '22:00',
            'sort_order' => 10,
        ];
    }

    public function open(string $opensAt = '10:00', string $closesAt = '22:00'): static
    {
        return $this->state(fn (): array => [
            'is_closed' => false,
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (): array => [
            'is_closed' => true,
            'opens_at' => null,
            'closes_at' => null,
        ]);
    }
}
