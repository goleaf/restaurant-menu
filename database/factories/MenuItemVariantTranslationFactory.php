<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SupportedLocale;
use App\Models\MenuItemVariant;
use App\Models\MenuItemVariantTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItemVariantTranslation>
 */
class MenuItemVariantTranslationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'menu_item_variant_id' => MenuItemVariant::factory(),
            'language_code' => fake()->randomElement(SupportedLocale::values()),
            'name' => fake()->words(2, true),
        ];
    }

    public function english(): static
    {
        return $this->forLocale(SupportedLocale::English);
    }

    public function lithuanian(): static
    {
        return $this->forLocale(SupportedLocale::Lithuanian);
    }

    public function russian(): static
    {
        return $this->forLocale(SupportedLocale::Russian);
    }

    public function forLocale(SupportedLocale $locale): static
    {
        return $this->state(fn (): array => ['language_code' => $locale->value]);
    }
}
