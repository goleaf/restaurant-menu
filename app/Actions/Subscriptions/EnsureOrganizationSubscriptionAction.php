<?php

namespace App\Actions\Subscriptions;

use App\Enums\OrganizationSubscriptionPaymentStatus;
use App\Enums\OrganizationSubscriptionStatus;
use App\Models\Organization;
use App\Models\OrganizationSubscription;

class EnsureOrganizationSubscriptionAction
{
    public function handle(Organization $organization): OrganizationSubscription
    {
        return OrganizationSubscription::query()->firstOrCreate(
            ['organization_id' => $organization->id],
            [
                'status' => OrganizationSubscriptionStatus::Active,
                'started_at' => now(),
                'next_payment_at' => now()->addMonthNoOverflow(),
                'payment_status' => OrganizationSubscriptionPaymentStatus::Pending,
            ],
        );
    }
}
