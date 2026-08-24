<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RestaurantOnboarding;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantOnboarding>
 */
class RestaurantOnboardingFactory extends Factory
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
            'organization_id' => null,
            'brand_id' => null,
            'branch_id' => null,
            'area_node_id' => null,
            'expected_service_point_count' => null,
            'menu_id' => null,
            'menu_category_id' => null,
            'menu_item_id' => null,
            'completed_at' => null,
        ];
    }
}
