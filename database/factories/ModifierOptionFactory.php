<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\ModifierOptionTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModifierOption>
 */
class ModifierOptionFactory extends Factory
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
            'name' => fake()->words(2, true),
            'price_delta_cents' => fake()->numberBetween(-500, 2500),
            'is_available' => true,
            'sort_order' => 0,
        ];
    }

    public function available(): static
    {
        return $this->state(fn (): array => [
            'is_available' => true,
        ]);
    }

    public function unavailable(): static
    {
        return $this->state(fn (): array => [
            'is_available' => false,
        ]);
    }

    public function free(): static
    {
        return $this->state(fn (): array => [
            'price_delta_cents' => 0,
        ]);
    }

    public function surcharge(int $cents): static
    {
        return $this->state(fn (): array => [
            'price_delta_cents' => abs($cents),
        ]);
    }

    public function discount(int $cents): static
    {
        return $this->state(fn (): array => [
            'price_delta_cents' => -abs($cents),
        ]);
    }

    public function withTranslations(): static
    {
        return $this->afterCreating(function (ModifierOption $option): void {
            foreach (['en', 'lt', 'ru'] as $languageCode) {
                ModifierOptionTranslation::factory()->for($option, 'option')->create([
                    'language_code' => $languageCode,
                    'name' => $option->name,
                ]);
            }
        });
    }
}
