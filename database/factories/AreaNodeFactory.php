<?php

namespace Database\Factories;

use App\Enums\AreaNodeType;
use App\Models\AreaNode;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AreaNode>
 */
class AreaNodeFactory extends Factory
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
            'parent_id' => null,
            'type' => fake()->randomElement(AreaNodeType::values()),
            'name' => fake()->unique()->words(2, true),
            'icon' => null,
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active' => true,
            'metadata' => [],
        ];
    }
}
