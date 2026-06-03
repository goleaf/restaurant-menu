<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\ModifierGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModifierGroup>
 */
class ModifierGroupFactory extends Factory
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
            'name' => fake()->words(2, true),
            'is_required' => false,
            'min_select' => 0,
            'max_select' => 1,
            'sort_order' => 0,
        ];
    }
}
