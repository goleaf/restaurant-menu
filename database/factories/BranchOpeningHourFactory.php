<?php

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
}
