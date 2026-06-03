<?php

namespace Database\Factories;

use App\Enums\MenuStatus;
use App\Models\Branch;
use App\Models\Menu;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Menu>
 */
class MenuFactory extends Factory
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
            'name' => fake()->unique()->words(2, true),
            'status' => MenuStatus::Draft,
            'sort_order' => 0,
        ];
    }
}
