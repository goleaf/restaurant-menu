<?php

namespace App\Actions\Subscriptions;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Branches\ForgetBranchCacheAction;
use App\Enums\AuditLogAction;
use App\Enums\OrganizationSubscriptionStatus;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SetOrganizationSubscriptionStatusAction
{
    public function __construct(
        private readonly EnsureOrganizationSubscriptionAction $ensureOrganizationSubscription,
        private readonly RecordAuditLogAction $recordAuditLog,
        private readonly ForgetBranchCacheAction $forgetBranchCache,
    ) {}

    public function handle(
        Organization $organization,
        OrganizationSubscriptionStatus $status,
        ?User $changedBy = null,
        ?string $reason = null,
    ): OrganizationSubscription {
        return DB::transaction(function () use ($organization, $status, $changedBy, $reason): OrganizationSubscription {
            $subscription = $this->ensureOrganizationSubscription->handle($organization);
            $previousStatus = $subscription->status;

            $subscription->status = $status;

            if ($status === OrganizationSubscriptionStatus::Active) {
                $subscription->started_at ??= now();
                $subscription->next_payment_at ??= now()->addMonthNoOverflow();
            }

            if (! $subscription->save()) {
                throw new RuntimeException('Organization subscription status could not be saved.');
            }

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
                $branchIds = Branch::query()->select('id')->where('organization_id', $organization->id)
                    ->lazyById(500)->pluck('id');
                $this->forgetBranchCache->handleMany($branchIds);
            }

            return $subscription;
        }, attempts: 3);
    }

    private function normalizeReason(?string $reason): ?string
    {
        $normalized = trim((string) $reason);

        return $normalized === '' ? null : mb_substr($normalized, 0, 500);
    }
}
