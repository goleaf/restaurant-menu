<?php

namespace Database\Factories;

use App\Enums\SystemRole;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $role = $this->nextAvailableRole();

        return [
            'code' => $role,
            'name' => $role->label(),
            'sort_order' => $this->sortOrderFor($role),
        ];
    }

    public function forSystemRole(SystemRole $role): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => $role,
            'name' => $role->label(),
            'sort_order' => $this->sortOrderFor($role),
        ]);
    }

    private function nextAvailableRole(): SystemRole
    {
        return collect(SystemRole::cases())
            ->first(
                fn (SystemRole $role): bool => ! Role::query()
                    ->where('code', $role->value)
                    ->exists()
            ) ?? SystemRole::Waiter;
    }

    private function sortOrderFor(SystemRole $role): int
    {
        return array_search($role, SystemRole::cases(), true) + 1;
    }
}
