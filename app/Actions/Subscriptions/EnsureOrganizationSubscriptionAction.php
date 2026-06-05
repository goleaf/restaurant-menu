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
        $subscription = OrganizationSubscription::query()
            ->where('organization_id', $organization->id)
            ->first();

        if ($subscription instanceof OrganizationSubscription) {
            return $subscription;
        }

        $subscription = new OrganizationSubscription;
        $subscription->forceFill([
            'organization_id' => $organization->id,
            'status' => OrganizationSubscriptionStatus::Active,
            'started_at' => now(),
            'next_payment_at' => now()->addMonthNoOverflow(),
            'payment_status' => OrganizationSubscriptionPaymentStatus::Pending,
        ])->save();

        return $subscription;
    }
}
