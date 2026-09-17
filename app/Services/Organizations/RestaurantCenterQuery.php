<?php

declare(strict_types=1);

namespace App\Services\Organizations;

use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\OrganizationSubscriptionStatus;
use App\Enums\OrganizationUserStatus;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;

final readonly class RestaurantCenterQuery
{
    public function __construct(private ResolveWaiterAccessibleBranchIdsAction $access) {}

    /** @param array<string,string> $filters @return Paginator<int,Branch> */
    public function restaurants(User $actor, array $filters): Paginator
    {
        $scope = $this->access->authorizedBranchQuery($actor);
        $query = Branch::query()->select(['id', 'organization_id', 'brand_id', 'name', 'address', 'city', 'country', 'is_active', 'logo_path', 'deleted_at'])
            ->with(['organization:id,name', 'brand:id,name',
                'restaurantOnboarding' => fn ($query) => $query->select(['id', 'branch_id', 'completed_at'])->where('user_id', $actor->id)])
            ->withExists(['menus as has_menu', 'servicePoints as has_tables']);
        if (($filters['lifecycle'] ?? 'active') === 'archived') {
            $query->onlyTrashed();
            $scope->withTrashed();
        }
        $query->whereIn('id', $scope);
        foreach (['organization' => 'organization_id', 'brand' => 'brand_id'] as $key => $column) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $query->where($column, $filters[$key]);
            }
        }
        $search = trim($filters['search'] ?? '');
        $query->when($search !== '', fn ($query) => $query->whereAny(['name', 'address', 'city'], 'like', '%'.$search.'%'));
        if (in_array($filters['active'] ?? 'all', ['active', 'inactive'], true)) {
            $query->where('is_active', $filters['active'] === 'active');
        }
        if (($filters['setup'] ?? 'all') === 'unfinished') {
            $query->whereHas('restaurantOnboarding', fn ($query) => $query->where('user_id', $actor->id)->whereNull('completed_at'));
        }

        return $query->orderBy('name')->orderBy('id')->simplePaginate(20)->withQueryString();
    }

    /** @return Paginator<int,Organization> */
    public function organizations(User $actor, string $search, string $lifecycle = 'active'): Paginator
    {
        $query = $this->organizationQuery($actor);
        if ($lifecycle === 'archived') {
            $query->onlyTrashed();
        }

        return $query->when(trim($search) !== '', fn ($query) => $query->where('name', 'like', '%'.trim($search).'%'))
            ->orderBy('name')->orderBy('id')->simplePaginate(20, pageName: 'organizationsPage')->withQueryString();
    }

    /** @return Paginator<int,Brand> */
    public function brands(User $actor, int $organizationId, string $search, string $lifecycle = 'active'): Paginator
    {
        $organization = Organization::query()->withTrashed()->whereKey($organizationId)->firstOrFail();
        Gate::forUser($actor)->authorize($organization->trashed() ? 'restore' : 'view', $organization);
        $query = $organization->brands()->select(['id', 'organization_id', 'name', 'logo_path', 'deleted_at']);
        if ($lifecycle === 'archived') {
            $query->onlyTrashed();
        }

        return $query->when(trim($search) !== '', fn ($query) => $query->where('name', 'like', '%'.trim($search).'%'))
            ->orderBy('name')->orderBy('id')->simplePaginate(20, pageName: 'brandsPage')->withQueryString();
    }

    /** @return array<int,string> */
    public function creationOrganizations(User $actor, string $search, mixed $selected = null): array
    {
        $query = $this->organizationQuery($actor);
        $rows = (clone $query)->where('name', 'like', '%'.trim($search).'%')->orderBy('name')->limit(20)->pluck('name', 'id')->all();
        if (is_int($selected) || (is_string($selected) && ctype_digit($selected))) {
            $chosen = $query->whereKey($selected)->first();
            if ($chosen instanceof Organization) {
                $rows[$chosen->id] = $chosen->name;
            }
        }

        return $rows;
    }

    /** @return array<int,string> */
    public function creationBrands(User $actor, mixed $organizationId, string $search, mixed $selected = null): array
    {
        if (! is_int($organizationId) && ! (is_string($organizationId) && ctype_digit($organizationId))) {
            return [];
        }
        $organization = $this->organizationQuery($actor)->whereKey($organizationId)->firstOrFail();
        Gate::forUser($actor)->authorize('view', $organization);
        $query = $organization->brands()->select(['id', 'name']);
        $rows = (clone $query)->where('name', 'like', '%'.trim($search).'%')->orderBy('name')->limit(20)->pluck('name', 'id')->all();
        if (is_int($selected) || (is_string($selected) && ctype_digit($selected))) {
            $chosen = $query->whereKey($selected)->first();
            if ($chosen instanceof Brand) {
                $rows[$chosen->id] = $chosen->name;
            }
        }

        return $rows;
    }

    /** @param array<string,int|string> $parameters @return array<string,mixed> */
    public function row(Organization|Brand|Branch $resource, string $kind, array $parameters): array
    {
        $setup = $resource instanceof Branch ? $resource->restaurantOnboarding : null;

        return [
            'id' => $resource->id, 'name' => $resource->name, 'kind' => $kind,
            'description' => $resource instanceof Branch ? implode(' · ', [$resource->organization->name, $resource->brand->name, $resource->city, $resource->address]) : '',
            'state' => $resource->trashed() ? __('center.archived') : ($resource instanceof Branch ? ($resource->is_active ? __('center.administrative_active') : __('center.administrative_inactive')) : ''),
            'properties' => route('restaurants.index', [...$parameters, 'kind' => $kind, 'object' => $resource->id]),
            'children' => $resource instanceof Organization ? route('restaurants.index', ['view' => 'structure', 'organization' => $resource->id]) : ($resource instanceof Brand ? route('restaurants.index', ['organization' => $resource->organization_id, 'brand' => $resource->id]) : null),
            'work' => $resource instanceof Branch && ! $resource->trashed() ? route('dashboard', ['branch' => $resource->id]) : null,
            'setup' => $setup !== null && $setup->completed_at === null ? route('restaurants.setup', ['setup' => $setup->id]) : null,
        ];
    }

    public function identity(User $actor, string $kind, int $id): Organization|Brand|Branch
    {
        $query = match ($kind) {
            'organization' => Organization::query()->select(['id', 'owner_user_id', 'name', 'logo_path', 'deleted_at']),
            'brand' => Brand::query()->select(['id', 'organization_id', 'name', 'logo_path', 'deleted_at']),
            'branch' => Branch::query()->select(['id', 'organization_id', 'brand_id', 'name', 'address', 'city', 'country', 'timezone', 'currency', 'is_active', 'logo_path', 'deleted_at']),
            default => abort(404),
        };
        $resource = $query->withTrashed()->whereKey($id)->firstOrFail();
        Gate::forUser($actor)->authorize($resource->trashed() ? 'restore' : 'view', $resource);

        return $resource;
    }

    /** @return Builder<Organization> */
    private function organizationQuery(User $actor): Builder
    {
        $query = Organization::query()->select(['id', 'owner_user_id', 'name', 'logo_path', 'deleted_at']);
        if (! $actor->isSuperadmin()) {
            $query->whereHas('memberships', fn ($query) => $query->where('user_id', $actor->id)->where('status', OrganizationUserStatus::Active->value));
        }

        return $query->where(fn ($query) => $query->whereDoesntHave('subscription')
            ->orWhereHas('subscription', fn ($query) => $query->where('status', OrganizationSubscriptionStatus::Active->value)));
    }
}
