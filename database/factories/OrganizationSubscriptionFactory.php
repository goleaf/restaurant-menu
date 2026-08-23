<?php

declare(strict_types=1);

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

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => OrganizationSubscriptionStatus::Active,
            'started_at' => now(),
        ]);
    }

    public function paymentPending(): static
    {
        return $this->state(fn (): array => [
            'payment_status' => OrganizationSubscriptionPaymentStatus::Pending,
            'next_payment_at' => now()->addMonthNoOverflow(),
        ]);
    }

    public function paymentPaid(): static
    {
        return $this->state(fn (): array => [
            'payment_status' => OrganizationSubscriptionPaymentStatus::Paid,
            'next_payment_at' => now()->addMonthNoOverflow(),
        ]);
    }

    public function paymentOverdue(): static
    {
        return $this->state(fn (): array => [
            'payment_status' => OrganizationSubscriptionPaymentStatus::Overdue,
            'next_payment_at' => now()->subDay(),
        ]);
    }

    public function paymentFailed(): static
    {
        return $this->state(fn (): array => [
            'payment_status' => OrganizationSubscriptionPaymentStatus::Failed,
            'next_payment_at' => now()->subDay(),
        ]);
    }
}
