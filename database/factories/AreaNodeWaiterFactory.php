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
        $organization = Organization::factory();
        $branch = Branch::factory()->for($organization);

        return [
            'organization_id' => $organization,
            'branch_id' => $branch,
            'area_node_id' => AreaNode::factory()->for($branch),
            'user_id' => User::factory(),
            'assigned_by_user_id' => User::factory(),
            'assigned_at' => now(),
        ];
    }
}
