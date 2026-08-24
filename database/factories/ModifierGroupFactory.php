<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Branch;
use App\Models\ModifierGroup;
use App\Models\ModifierGroupTranslation;
use App\Models\ModifierOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModifierGroup>
 */
class ModifierGroupFactory extends Factory
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
            'name' => fake()->words(2, true),
            'is_required' => false,
            'min_select' => 0,
            'max_select' => 1,
            'sort_order' => 0,
        ];
    }

    public function required(int $min = 1, int $max = 1): static
    {
        return $this->state(fn (): array => [
            'is_required' => true,
            'min_select' => max(1, $min),
            'max_select' => max(max(1, $min), $max),
        ]);
    }

    public function optional(int $max = 1): static
    {
        return $this->state(fn (): array => [
            'is_required' => false,
            'min_select' => 0,
            'max_select' => max(1, $max),
        ]);
    }

    public function withOptions(int $count = 2): static
    {
        return $this->has(ModifierOption::factory()->count($count), 'options');
    }

    public function withTranslations(): static
    {
        return $this->afterCreating(function (ModifierGroup $group): void {
            foreach (['en', 'lt', 'ru'] as $languageCode) {
                ModifierGroupTranslation::factory()->for($group, 'group')->create([
                    'language_code' => $languageCode,
                    'name' => $group->name,
                ]);
            }
        });
    }
}
