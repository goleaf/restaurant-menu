<?php

namespace Database\Factories;

use App\Enums\SystemRole;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_user_id' => User::factory(),
            'name' => fake()->company(),
        ];
    }

    public function withBrands(int $count = 1): static
    {
        return $this->afterCreating(function (Organization $organization) use ($count): void {
            Brand::factory()
                ->count($count)
                ->for($organization)
                ->create();
        });
    }

    public function withUsers(int $count = 1, SystemRole $role = SystemRole::Waiter): static
    {
        return $this->afterCreating(function (Organization $organization) use ($count, $role): void {
            OrganizationUser::factory()
                ->count($count)
                ->forOrganization($organization)
                ->forSystemRole($role)
                ->active()
                ->create();
        });
    }
}
