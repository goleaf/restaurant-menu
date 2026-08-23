<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AreaNode;
use App\Models\AreaNodeWaiter;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AreaNodeWaiter>
 */
class AreaNodeWaiterFactory extends Factory
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
            'area_node_id' => fn (array $attributes): int => AreaNode::factory()
                ->create(['branch_id' => $attributes['branch_id']])
                ->id,
            'user_id' => User::factory(),
            'assigned_by_user_id' => User::factory(),
            'assigned_at' => now(),
        ];
    }
}
