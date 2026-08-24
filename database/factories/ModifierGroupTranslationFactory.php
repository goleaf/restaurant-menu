<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SupportedLocale;
use App\Models\ModifierGroup;
use App\Models\ModifierGroupTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModifierGroupTranslation>
 */
class ModifierGroupTranslationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'modifier_group_id' => ModifierGroup::factory(),
            'language_code' => fake()->randomElement(SupportedLocale::values()),
            'name' => fake()->words(2, true),
        ];
    }
}
