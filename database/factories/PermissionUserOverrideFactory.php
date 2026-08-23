<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Permission;
use App\Models\PermissionUserOverride;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PermissionUserOverride>
 */
class PermissionUserOverrideFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'permission_id' => Permission::factory(),
            'enabled' => true,
        ];
    }

    public function allowed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'enabled' => true,
        ]);
    }

    public function denied(): static
    {
        return $this->state(fn (array $attributes): array => [
            'enabled' => false,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user->id,
        ]);
    }

    public function forPermission(Permission $permission): static
    {
        return $this->state(fn (array $attributes): array => [
            'permission_id' => $permission->id,
        ]);
    }
}
