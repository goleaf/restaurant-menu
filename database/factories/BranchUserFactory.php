<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BranchUser>
 */
class BranchUserFactory extends Factory
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
            'branch_id' => fn (array $attributes): int => Branch::factory()
                ->create(['organization_id' => $attributes['organization_id']])
                ->id,
            'user_id' => User::factory(),
            'role_id' => Role::query()->firstOrCreate(
                ['code' => SystemRole::Waiter->value],
                ['name' => SystemRole::Waiter->label(), 'sort_order' => 6],
            )->id,
            'status' => OrganizationUserStatus::Active,
            'assigned_at' => now(),
            'assigned_by_user_id' => User::factory(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => OrganizationUserStatus::Active,
            'assigned_at' => now(),
        ]);
    }

    public function invited(): static
    {
        return $this->state(fn (): array => [
            'status' => OrganizationUserStatus::Invited,
            'assigned_at' => null,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => OrganizationUserStatus::Suspended,
        ]);
    }

    public function removed(): static
    {
        return $this->state(fn (): array => [
            'status' => OrganizationUserStatus::Removed,
        ]);
    }

    public function forBranch(Branch $branch): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user->id,
        ]);
    }

    public function forRole(Role $role): static
    {
        return $this->state(fn (): array => [
            'role_id' => $role->id,
        ]);
    }
}
