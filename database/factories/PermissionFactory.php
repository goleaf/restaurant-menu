<?php

namespace Database\Factories;

use App\Enums\SystemPermission;
use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $permission = $this->nextAvailablePermission();

        return [
            'code' => $permission->value,
            'name' => $permission->label(),
            'sort_order' => $this->sortOrderFor($permission),
        ];
    }

    public function forSystemPermission(SystemPermission $permission): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => $permission->value,
            'name' => $permission->label(),
            'sort_order' => $this->sortOrderFor($permission),
        ]);
    }

    private function nextAvailablePermission(): SystemPermission
    {
        return collect(SystemPermission::cases())
            ->first(
                fn (SystemPermission $permission): bool => ! Permission::query()
                    ->where('code', $permission->value)
                    ->exists()
            ) ?? SystemPermission::ViewRestaurant;
    }

    private function sortOrderFor(SystemPermission $permission): int
    {
        return array_search($permission, SystemPermission::cases(), true) + 1;
    }
}
