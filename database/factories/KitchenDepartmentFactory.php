<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\KitchenDepartmentType;
use App\Models\Branch;
use App\Models\KitchenDepartment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KitchenDepartment>
 */
class KitchenDepartmentFactory extends Factory
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
            'type' => KitchenDepartmentType::Kitchen,
            'name' => fake()->unique()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function forType(KitchenDepartmentType $type): static
    {
        return $this->state(fn (): array => [
            'type' => $type,
            'name' => $type->label(),
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
