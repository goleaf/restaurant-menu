<?php

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
        $organization = Organization::factory();

        return [
            'organization_id' => $organization,
            'branch_id' => Branch::factory()->for($organization),
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
}
