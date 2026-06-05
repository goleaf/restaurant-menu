<?php

namespace Database\Factories;

use App\Models\Permission;
use App\Models\PermissionRole;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PermissionRole>
 */
class PermissionRoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role_id' => Role::factory(),
            'permission_id' => Permission::factory(),
            'enabled' => false,
        ];
    }

    public function enabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'enabled' => true,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'enabled' => false,
        ]);
    }

    public function forRole(Role $role): static
    {
        return $this->state(fn (array $attributes): array => [
            'role_id' => $role->id,
        ]);
    }

    public function forPermission(Permission $permission): static
    {
        return $this->state(fn (array $attributes): array => [
            'permission_id' => $permission->id,
        ]);
    }
}
