<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Enums\OrganizationSubscriptionStatus;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\Role;
use DomainException;

final class EnsureInvitationScopeAction
{
    public function handle(Invitation $invitation): void
    {
        if (! is_string($invitation->email) || trim($invitation->email) === '') {
            throw new DomainException('Invitation recipient is not bound.');
        }
        $role = Role::query()->select(['id', 'code'])->whereKey($invitation->role_id)->first();
        if (! $role instanceof Role || $role->code === SystemRole::Superadmin) {
            throw new DomainException('Invitation role is not available.');
        }
        if (! Organization::query()->whereKey($invitation->organization_id)
            ->where(fn ($query) => $query->whereDoesntHave('subscription')->orWhereHas('subscription', fn ($subscription) => $subscription->where('status', OrganizationSubscriptionStatus::Active->value)))
            ->exists()) {
            throw new DomainException('Invitation organization is not available.');
        }
        if ($invitation->brand_id !== null && ! Brand::query()->where('organization_id', $invitation->organization_id)->whereKey($invitation->brand_id)->exists()) {
            throw new DomainException('Invitation brand is not available.');
        }
        if ($invitation->branch_id !== null && ($invitation->brand_id === null || ! Branch::query()
            ->where('organization_id', $invitation->organization_id)->where('brand_id', $invitation->brand_id)->whereKey($invitation->branch_id)->exists())) {
            throw new DomainException('Invitation branch is not available.');
        }
    }
}
