<?php

namespace App\Actions\Subscriptions;

use App\Enums\OrganizationSubscriptionStatus;
use App\Models\Organization;
use App\Models\OrganizationSubscription;

class SetOrganizationSubscriptionStatusAction
{
    public function __construct(
        private readonly EnsureOrganizationSubscriptionAction $ensureOrganizationSubscription,
    ) {}

    public function handle(Organization $organization, OrganizationSubscriptionStatus $status): OrganizationSubscription
    {
        $subscription = $this->ensureOrganizationSubscription->handle($organization);

        $subscription->status = $status;

        if ($status === OrganizationSubscriptionStatus::Active) {
            $subscription->started_at ??= now();
            $subscription->next_payment_at ??= now()->addMonthNoOverflow();
        }

        $subscription->save();

        return $subscription;
    }
}
