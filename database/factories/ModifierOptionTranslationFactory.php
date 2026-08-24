<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SupportedLocale;
use App\Models\ModifierOption;
use App\Models\ModifierOptionTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModifierOptionTranslation>
 */
class ModifierOptionTranslationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'modifier_option_id' => ModifierOption::factory(),
            'language_code' => fake()->randomElement(SupportedLocale::values()),
            'name' => fake()->words(2, true),
        ];
    }
}
