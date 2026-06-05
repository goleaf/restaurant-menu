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
