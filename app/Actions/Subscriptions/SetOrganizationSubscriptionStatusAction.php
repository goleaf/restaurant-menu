<?php

namespace App\Actions\Subscriptions;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Enums\OrganizationSubscriptionStatus;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\User;

class SetOrganizationSubscriptionStatusAction
{
    public function __construct(
        private readonly EnsureOrganizationSubscriptionAction $ensureOrganizationSubscription,
        private readonly RecordAuditLogAction $recordAuditLog,
    ) {}

    public function handle(
        Organization $organization,
        OrganizationSubscriptionStatus $status,
        ?User $changedBy = null,
        ?string $reason = null,
    ): OrganizationSubscription {
        $subscription = $this->ensureOrganizationSubscription->handle($organization);
        $previousStatus = $subscription->status;

        $subscription->status = $status;

        if ($status === OrganizationSubscriptionStatus::Active) {
            $subscription->started_at ??= now();
            $subscription->next_payment_at ??= now()->addMonthNoOverflow();
        }

        $subscription->save();

        if ($previousStatus !== $status) {
            $this->recordAuditLog->handle(
                action: AuditLogAction::OrganizationSubscriptionChanged,
                entityType: 'organization',
                entityId: $organization->id,
                actorUser: $changedBy,
                organizationId: $organization->id,
                oldValues: [
                    'subscription_status' => $previousStatus,
                ],
                newValues: [
                    'subscription_status' => $status,
                    'reason' => $this->normalizeReason($reason),
                ],
            );
        }

        return $subscription;
    }

    private function normalizeReason(?string $reason): ?string
    {
        $normalized = trim((string) $reason);

        return $normalized === '' ? null : mb_substr($normalized, 0, 500);
    }
}
