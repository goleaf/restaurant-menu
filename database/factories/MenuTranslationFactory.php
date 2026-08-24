<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SupportedLocale;
use App\Models\Menu;
use App\Models\MenuTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuTranslation>
 */
class MenuTranslationFactory extends Factory
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
            'language_code' => fake()->randomElement(SupportedLocale::values()),
            'name' => fake()->words(2, true),
        ];
    }
}
