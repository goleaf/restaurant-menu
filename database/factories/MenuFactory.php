<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MenuStatus;
use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuCategory;
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

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => MenuStatus::Draft,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => MenuStatus::Active,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'status' => MenuStatus::Archived,
        ]);
    }

    public function forBranch(Branch $branch): static
    {
        return $this->state(fn (): array => [
            'branch_id' => $branch->id,
        ]);
    }

    public function withCategories(int $count = 1): static
    {
        return $this->afterCreating(function (Menu $menu) use ($count): void {
            MenuCategory::factory()
                ->count($count)
                ->for($menu)
                ->create();
        });
    }
}
