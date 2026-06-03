<?php

namespace Database\Factories;

use App\Enums\ServicePointStatus;
use App\Enums\ServicePointType;
use App\Models\Branch;
use App\Models\ServicePoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServicePoint>
 */
class ServicePointFactory extends Factory
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
            'area_node_id' => null,
            'type' => fake()->randomElement(ServicePointType::values()),
            'name' => fake()->unique()->words(2, true),
            'display_number' => (string) fake()->numberBetween(1, 999),
            'internal_code' => fake()->unique()->bothify('SP-####'),
            'capacity' => fake()->numberBetween(1, 12),
            'icon' => null,
            'status' => ServicePointStatus::Available,
            'position_x' => fake()->randomFloat(2, 0, 1000),
            'position_y' => fake()->randomFloat(2, 0, 1000),
            'is_active' => true,
            'metadata' => [],
        ];
    }
}
