<?php

declare(strict_types=1);

namespace App\Services\Organizations;

use App\Enums\OrganizationSubscriptionStatus;
use App\Enums\OrganizationUserStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Pagination\Paginator;

final class OrganizationQueryService
{
    /** @return Paginator<int, Organization> */
    public function paginateAccessibleTo(
        User $user,
        string $search,
        int $perPage,
        string $lifecycle = 'active',
        string $sort = 'name_asc',
    ): Paginator {
        $search = trim($search);

        $organizations = $user->organizations();

        if ($lifecycle === 'archived') {
            $organizations->onlyTrashed();
        }

        $organizations
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
                'organizations.deleted_at',
            ]);

        $this->applySort($organizations, $sort);

        return $organizations
            ->simplePaginate($perPage, pageName: 'organizationsPage');
    }

    public function findAccessibleTo(User $user, int $organizationId, bool $withTrashed = false): Organization
    {
        return $user->organizations()
            ->when($withTrashed, fn ($query) => $query->withTrashed())
            ->wherePivot('status', OrganizationUserStatus::Active->value)
            ->select([
                'organizations.id',
                'organizations.owner_user_id',
                'organizations.name',
                'organizations.logo_path',
                'organizations.created_at',
                'organizations.updated_at',
                'organizations.deleted_at',
            ])
            ->whereKey($organizationId)
            ->firstOrFail();
    }

    /** @param BelongsToMany<Organization, User> $query */
    private function applySort(BelongsToMany $query, string $sort): void
    {
        match ($sort) {
            'name_desc' => $query->orderByDesc('organizations.name')->orderByDesc('organizations.id'),
            'newest' => $query->orderByDesc('organizations.created_at')->orderByDesc('organizations.id'),
            'oldest' => $query->orderBy('organizations.created_at')->orderBy('organizations.id'),
            default => $query->orderBy('organizations.name')->orderBy('organizations.id'),
        };
    }
}
