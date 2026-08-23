<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MenuItemVariantType;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\MenuItemVariantTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItemVariant>
 */
class MenuItemVariantFactory extends Factory
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
            'type' => MenuItemVariantType::Variant,
            'name' => fake()->unique()->words(2, true),
            'price_cents' => fake()->numberBetween(100, 12000),
            'weight' => null,
            'volume' => null,
            'is_default' => false,
            'is_available' => true,
            'sort_order' => 0,
        ];
    }

    public function variant(): static
    {
        return $this->state(fn (): array => ['type' => MenuItemVariantType::Variant]);
    }

    public function portion(): static
    {
        return $this->state(fn (): array => ['type' => MenuItemVariantType::Portion]);
    }

    public function default(): static
    {
        return $this->state(fn (): array => ['is_default' => true]);
    }

    public function available(): static
    {
        return $this->state(fn (): array => ['is_available' => true]);
    }

    public function unavailable(): static
    {
        return $this->state(fn (): array => ['is_available' => false]);
    }

    public function withTranslations(): static
    {
        return $this->afterCreating(function (MenuItemVariant $variant): void {
            foreach (['en', 'lt', 'ru'] as $languageCode) {
                MenuItemVariantTranslation::factory()
                    ->for($variant, 'variant')
                    ->create([
                        'language_code' => $languageCode,
                        'name' => $variant->name,
                    ]);
            }
        });
    }
}
