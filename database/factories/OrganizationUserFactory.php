<?php

namespace Database\Factories;

use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationUser>
 */
class OrganizationUserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'role_id' => Role::factory(),
            'status' => OrganizationUserStatus::Active,
            'joined_at' => now(),
            'invited_by_user_id' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrganizationUserStatus::Active,
            'joined_at' => now(),
        ]);
    }

    public function invited(?User $invitedBy = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrganizationUserStatus::Invited,
            'joined_at' => null,
            'invited_by_user_id' => $invitedBy?->id ?? User::factory(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrganizationUserStatus::Suspended,
        ]);
    }

    public function removed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrganizationUserStatus::Removed,
        ]);
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (array $attributes): array => [
            'organization_id' => $organization->id,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user->id,
        ]);
    }

    public function forRole(Role $role): static
    {
        return $this->state(fn (array $attributes): array => [
            'role_id' => $role->id,
        ]);
    }

    public function forSystemRole(SystemRole $role): static
    {
        return $this->state(fn (array $attributes): array => [
            'role_id' => Role::query()->firstOrCreate(
                ['code' => $role->value],
                ['name' => $role->label(), 'sort_order' => $this->sortOrderFor($role)],
            )->id,
        ]);
    }

    private function sortOrderFor(SystemRole $role): int
    {
        return array_search($role, SystemRole::cases(), true) + 1;
    }
}
