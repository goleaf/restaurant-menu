<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MenuItem;
use App\Models\MenuItemImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItemImage>
 */
class MenuItemImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'menu_item_id' => MenuItem::factory(),
            'path' => 'media/testing/menu-item-images/'.fake()->unique()->uuid().'.jpg',
            'sort_order' => 0,
        ];
    }

    public function withPresentation(): static
    {
        return $this->state(fn (): array => ['presentation' => [
            'focal_x' => 35, 'focal_y' => 60,
            'translations' => [
                'en' => ['alt' => 'A freshly prepared dish', 'caption' => 'Serving suggestion'],
                'lt' => ['alt' => 'Šviežiai paruoštas patiekalas', 'caption' => 'Patiekimo pasiūlymas'],
                'ru' => ['alt' => 'Свежеприготовленное блюдо', 'caption' => 'Вариант подачи'],
            ],
        ]]);
    }
}
