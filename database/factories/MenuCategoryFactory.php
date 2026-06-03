<?php

namespace Database\Factories;

use App\Models\Menu;
use App\Models\MenuCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuCategory>
 */
class MenuCategoryFactory extends Factory
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
            'parent_id' => null,
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'image' => null,
            'icon' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
