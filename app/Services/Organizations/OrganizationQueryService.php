<?php

declare(strict_types=1);

namespace App\Services\Organizations;

use App\Enums\OrganizationSubscriptionStatus;
use App\Enums\OrganizationUserStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Pagination\Paginator;

final class OrganizationQueryService
{
    /** @return Paginator<int, Organization> */
    public function paginateAccessibleTo(User $user, string $search, int $perPage): Paginator
    {
        $search = trim($search);

        return $user->organizations()
            ->wherePivot('status', OrganizationUserStatus::Active->value)
            ->where(function ($query): void {
                $query
                    ->whereDoesntHave('subscription')
                    ->orWhereHas('subscription', function ($subscriptionQuery): void {
                        $subscriptionQuery->where('status', OrganizationSubscriptionStatus::Active->value);
                    });
            })
            ->when($search !== '', fn ($query) => $query
                ->where('organizations.name', 'like', '%'.$search.'%'))
            ->select([
                'organizations.id',
                'organizations.owner_user_id',
                'organizations.name',
                'organizations.logo_path',
                'organizations.created_at',
                'organizations.updated_at',
            ])
            ->orderBy('organizations.name')
            ->orderBy('organizations.id')
            ->simplePaginate($perPage, pageName: 'organizationsPage');
    }

    public function find(int $organizationId): Organization
    {
        return Organization::query()
            ->select([
                'id',
                'owner_user_id',
                'name',
                'logo_path',
                'created_at',
                'updated_at',
            ])
            ->whereKey($organizationId)
            ->firstOrFail();
    }
}
