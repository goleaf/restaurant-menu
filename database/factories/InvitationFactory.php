<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvitationStatus;
use App\Enums\SystemRole;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
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
            'brand_id' => null,
            'branch_id' => null,
            'role_id' => Role::query()->firstOrCreate(
                ['code' => SystemRole::Waiter->value],
                ['name' => SystemRole::Waiter->label(), 'sort_order' => 6],
            )->id,
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->e164PhoneNumber(),
            'invite_token' => null,
            'invite_code' => null,
            'invite_token_hash' => hash('sha256', Str::random(64)),
            'invite_code_hash' => hash('sha256', Str::upper(Str::random(8))),
            'expires_at' => now()->addDays(7),
            'status' => InvitationStatus::Pending,
            'invited_by_user_id' => User::factory(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => InvitationStatus::Pending,
            'expires_at' => now()->addDays(7),
            'accepted_by_user_id' => null,
            'accepted_at' => null,
        ]);
    }

    public function acceptedBy(?User $user = null): static
    {
        return $this->state(fn (): array => [
            'status' => InvitationStatus::Accepted,
            'expires_at' => now()->addDays(7),
            'accepted_by_user_id' => $user instanceof User ? $user->id : User::factory(),
            'accepted_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => InvitationStatus::Expired,
            'expires_at' => now()->subMinute(),
            'accepted_by_user_id' => null,
            'accepted_at' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => InvitationStatus::Cancelled,
            'accepted_by_user_id' => null,
            'accepted_at' => null,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => InvitationStatus::Rejected,
            'accepted_by_user_id' => null,
            'accepted_at' => null,
        ]);
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $organization->id,
        ]);
    }

    public function forRole(Role $role): static
    {
        return $this->state(fn (): array => [
            'role_id' => $role->id,
        ]);
    }
}
