<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->company(),
        ];
    }

    public function bellaPizza(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Bella Pizza',
            'logo_path' => null,
        ]);
    }

    public function sushiMaster(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Sushi Master',
            'logo_path' => null,
        ]);
    }

    public function coffeeBarDemo(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Coffee Bar Demo',
            'logo_path' => null,
        ]);
    }

    public function withBranches(int $count = 1): static
    {
        return $this->afterCreating(function (Brand $brand) use ($count): void {
            Branch::factory()
                ->count($count)
                ->forBrand($brand)
                ->create();
        });
    }
}
