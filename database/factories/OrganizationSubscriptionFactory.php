<?php

namespace Database\Factories;

use App\Enums\OrganizationSubscriptionPaymentStatus;
use App\Enums\OrganizationSubscriptionStatus;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationSubscription>
 */
class OrganizationSubscriptionFactory extends Factory
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
            'status' => OrganizationSubscriptionStatus::Active,
            'started_at' => now(),
            'next_payment_at' => now()->addMonthNoOverflow(),
            'payment_status' => OrganizationSubscriptionPaymentStatus::Pending,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => OrganizationSubscriptionStatus::Inactive,
        ]);
    }
}
