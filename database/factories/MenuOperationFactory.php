<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MenuOperationKind;
use App\Enums\MenuOperationPhase;
use App\Models\Menu;
use App\Models\MenuOperation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MenuOperation> */
class MenuOperationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['request_id' => fake()->uuid(), 'menu_id' => Menu::factory(),
            'branch_id' => fn (array $attributes): int => (int) Menu::query()->whereKey($attributes['menu_id'])->value('branch_id'),
            'actor_user_id' => User::factory(), 'target_id' => fn (array $attributes): int => (int) $attributes['menu_id'],
            'kind' => MenuOperationKind::DeleteMenu, 'phase' => MenuOperationPhase::Items];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => ['phase' => MenuOperationPhase::Completed, 'completed_at' => now(), 'active_scope' => null]);
    }
}
