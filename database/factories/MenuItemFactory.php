<?php

namespace Database\Factories;

use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'menu_id' => Menu::factory(),
            'category_id' => fn (array $attributes): int => MenuCategory::factory()
                ->create(['menu_id' => $attributes['menu_id']])
                ->id,
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'price' => fake()->randomFloat(2, 1, 80),
            'image' => null,
            'weight' => fake()->optional()->randomFloat(2, 50, 1200),
            'volume' => fake()->optional()->randomFloat(2, 0.1, 2),
            'calories' => fake()->optional()->numberBetween(50, 1600),
            'is_available' => true,
            'sort_order' => 0,
        ];
    }
}
