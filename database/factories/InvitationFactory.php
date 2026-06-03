<?php

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
            'invite_token' => Str::random(64),
            'invite_code' => Str::upper(Str::random(8)),
            'expires_at' => now()->addDays(7),
            'status' => InvitationStatus::Pending,
            'invited_by_user_id' => User::factory(),
        ];
    }
}
