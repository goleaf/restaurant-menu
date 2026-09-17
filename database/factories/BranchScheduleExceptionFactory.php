<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\BranchScheduleException;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BranchScheduleException>
 */
class BranchScheduleExceptionFactory extends Factory
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
            'local_date' => fake()->unique()->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            'is_closed' => true,
            'intervals' => [],
        ];
    }
}
