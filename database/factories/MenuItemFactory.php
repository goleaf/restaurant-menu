<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MenuAllergen;
use App\Enums\MenuDietaryLabel;
use App\Models\KitchenDepartment;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
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
            'category_id' => fn (array $attributes): int => MenuCategory::factory()
                ->create(['menu_id' => $attributes['menu_id']])
                ->id,
            'kitchen_department_id' => null,
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'price_cents' => fake()->numberBetween(100, 8000),
            'allergens' => [],
            'dietary_labels' => [],
            'image' => null,
            'weight' => fake()->optional()->randomFloat(2, 50, 1200),
            'volume' => fake()->optional()->randomFloat(2, 0.1, 2),
            'calories' => fake()->optional()->numberBetween(50, 1600),
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

    public function withAllergens(MenuAllergen ...$allergens): static
    {
        if ($allergens === []) {
            $allergens = [MenuAllergen::Gluten, MenuAllergen::Milk];
        }

        return $this->state(fn (): array => [
            'allergens' => array_map(
                fn (MenuAllergen $allergen): string => $allergen->value,
                $allergens,
            ),
        ]);
    }

    public function withDietaryLabels(MenuDietaryLabel ...$labels): static
    {
        if ($labels === []) {
            $labels = [MenuDietaryLabel::Vegetarian];
        }

        return $this->state(fn (): array => [
            'dietary_labels' => array_map(
                fn (MenuDietaryLabel $label): string => $label->value,
                $labels,
            ),
        ]);
    }

    public function withModifiers(int $groups = 1, int $optionsPerGroup = 2): static
    {
        return $this->afterCreating(function (MenuItem $menuItem) use ($groups, $optionsPerGroup): void {
            $menuItem->loadMissing('menu.branch');

            for ($index = 0; $index < $groups; $index++) {
                $modifierGroup = ModifierGroup::factory()
                    ->for($menuItem->menu->branch)
                    ->has(
                        ModifierOption::factory()->count($optionsPerGroup),
                        'options',
                    )
                    ->create();

                $menuItem->modifierGroups()->syncWithoutDetaching([$modifierGroup->id]);
            }
        });
    }

    public function withVariants(int $count = 2): static
    {
        return $this->afterCreating(function (MenuItem $menuItem) use ($count): void {
            foreach (range(1, max(1, $count)) as $position) {
                MenuItemVariant::factory()
                    ->for($menuItem, 'item')
                    ->portion()
                    ->state([
                        'name' => match ($position) {
                            1 => 'Regular portion',
                            2 => 'Large portion',
                            default => "Portion {$position}",
                        },
                        'price_cents' => $menuItem->price_cents + (($position - 1) * 300),
                        'is_default' => $position === 1,
                        'sort_order' => $position * 10,
                    ])
                    ->create();
            }
        });
    }

    public function assignedToDepartment(?KitchenDepartment $department = null): static
    {
        return $this
            ->state(fn (): array => $department instanceof KitchenDepartment
                ? ['kitchen_department_id' => $department->id]
                : [])
            ->afterCreating(function (MenuItem $menuItem) use ($department): void {
                if ($department instanceof KitchenDepartment) {
                    return;
                }

                $menuItem->loadMissing('menu.branch');

                $createdDepartment = KitchenDepartment::factory()
                    ->for($menuItem->menu->branch)
                    ->create();

                $menuItem->forceFill([
                    'kitchen_department_id' => $createdDepartment->id,
                ])->save();
            });
    }
}
