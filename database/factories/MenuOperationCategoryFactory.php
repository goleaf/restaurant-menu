<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MenuCategory;
use App\Models\MenuOperation;
use App\Models\MenuOperationCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MenuOperationCategory> */
class MenuOperationCategoryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['menu_operation_id' => MenuOperation::factory(),
            'menu_category_id' => fn (array $attributes): int => MenuCategory::factory()->create([
                'menu_id' => MenuOperation::query()->whereKey($attributes['menu_operation_id'])->value('menu_id'),
            ])->id];
    }

    public function discovered(): static
    {
        return $this->state(fn (): array => ['discovered' => true]);
    }
}
