<?php

namespace Database\Factories;

use App\Models\KitchenDepartment;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
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
            'price' => fake()->randomFloat(2, 1, 80),
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

    /**
     * Menu variants are currently represented by modifier groups and options.
     */
    public function withVariants(int $groups = 1, int $optionsPerGroup = 2): static
    {
        return $this->withModifiers($groups, $optionsPerGroup);
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
