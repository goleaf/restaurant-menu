<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SupportedLocale;
use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItemTranslation>
 */
class MenuItemTranslationFactory extends Factory
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
            'language_code' => fake()->randomElement(['ru', 'en', 'lt']),
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->sentence(),
        ];
    }

    public function forLocale(SupportedLocale $locale): static
    {
        return $this->state(fn (): array => [
            'language_code' => $locale->value,
        ]);
    }
}
