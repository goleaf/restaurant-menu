<?php

declare(strict_types=1);

namespace App\Services\Organizations;

use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\OrganizationSubscriptionStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\RestaurantOnboarding;
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
        $scope = $this->access->authorizedBranchQuery($actor, includeArchived: ($filters['lifecycle'] ?? 'active') === 'archived');
        $query = Branch::query()->select(['id', 'organization_id', 'brand_id', 'name', 'address', 'city', 'country', 'is_active', 'logo_path', 'deleted_at'])
            ->with(['organization:id,owner_user_id,name,deleted_at', 'organization.subscription:id,organization_id,status', 'brand:id,name',
                'restaurantOnboarding' => fn ($query) => $query->select(['id', 'branch_id', 'purpose', 'completed_at'])->where('user_id', $actor->id)])
            ->withExists(['restaurantOnboarding as preparation_confirmed' => fn ($query) => $query->whereNotNull('completed_at')]);
        if (($filters['lifecycle'] ?? 'active') === 'archived') {
            $query->onlyTrashed();
            $scope->withTrashed();
            $query->whereIn('id', $this->access->handle($actor, SystemPermission::ManageBranches, includeArchived: true));
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
            $query->whereDoesntHave('restaurantOnboarding', fn ($query) => $query->whereNotNull('completed_at'));
        }

        $rows = $query->orderBy('name', ($filters['sort'] ?? '') === 'name_desc' ? 'desc' : 'asc')->orderBy('id')->simplePaginate(20)->withQueryString();
        $needsPermissions = $rows->getCollection()->contains(fn (Branch $branch): bool => $branch->restaurantOnboarding?->purpose === 'additional');
        $manageable = $needsPermissions ? $this->access->handle($actor, SystemPermission::ManageBranches) : collect();
        foreach ($rows as $branch) {
            $setup = $branch->restaurantOnboarding;
            $allowed = $setup !== null && ! $branch->trashed() && ! $branch->organization->trashed()
                && $branch->organization->subscription?->status === OrganizationSubscriptionStatus::Active
                && ($setup->purpose === 'additional' ? $manageable->contains($branch->id) : (int) $branch->organization->owner_user_id === (int) $actor->id);
            $branch->setAttribute('can_continue_setup', $allowed);
        }

        return $rows;
    }

    /** @return Paginator<int,Organization> */
    public function organizations(User $actor, string $search, string $lifecycle = 'active', string $sort = 'name_asc'): Paginator
    {
        $query = $this->organizationQuery($actor);
        if ($lifecycle === 'archived') {
            $query->onlyTrashed()->whereHas('memberships', fn ($membership) => $membership
                ->where('user_id', $actor->id)->where('status', OrganizationUserStatus::Active->value)
                ->whereHas('role', fn ($role) => $role->where('code', SystemRole::Owner->value)));
        }

        return $query->when(trim($search) !== '', fn ($query) => $query->where('name', 'like', '%'.trim($search).'%'))
            ->orderBy('name', $sort === 'name_desc' ? 'desc' : 'asc')->orderBy('id')->simplePaginate(20, pageName: 'organizationsPage')->withQueryString();
    }

    /** @return Paginator<int,Brand> */
    public function brands(User $actor, int $organizationId, string $search, string $lifecycle = 'active', string $sort = 'name_asc'): Paginator
    {
        $organization = Organization::query()->withTrashed()->whereKey($organizationId)->firstOrFail();
        Gate::forUser($actor)->authorize($organization->trashed() ? 'restore' : 'view', $organization);
        $query = $organization->brands()->select(['id', 'organization_id', 'name', 'logo_path', 'deleted_at']);
        if ($lifecycle === 'archived') {
            $query->onlyTrashed();
            if (! Gate::forUser($actor)->allows('create', [Brand::class, $organization])) {
                $query->whereKey([]);
            }
        }

        return $query->when(trim($search) !== '', fn ($query) => $query->where('name', 'like', '%'.trim($search).'%'))
            ->orderBy('name', $sort === 'name_desc' ? 'desc' : 'asc')->orderBy('id')->simplePaginate(20, pageName: 'brandsPage')->withQueryString();
    }

    /** @param array<string,string> $filters */
    public function emptyState(User $actor, array $filters): string
    {
        if (! $this->organizationQuery($actor)->withTrashed()->exists() && ! Gate::forUser($actor)->allows('create', Organization::class)) {
            return 'no_access';
        }

        return $filters['search'] !== '' || $filters['active'] !== 'all' || $filters['setup'] !== 'all' || $filters['lifecycle'] !== 'active'
            ? 'search' : 'empty';
    }

    public function canCreateRestaurant(User $actor, string $organizationId): bool
    {
        if ($organizationId === '') {
            return Gate::forUser($actor)->allows('create', Organization::class);
        }
        $organization = $this->organizationQuery($actor)->whereKey($organizationId)->first();

        return $organization !== null && Gate::forUser($actor)->allows('createAdditional', [RestaurantOnboarding::class, $organization]);
    }

    public function canCreateBrand(User $actor, string $organizationId): bool
    {
        if ($organizationId === '') {
            return false;
        }
        $organization = $this->organizationQuery($actor)->whereKey($organizationId)->first();

        return $organization !== null && Gate::forUser($actor)->allows('create', [Brand::class, $organization]);
    }

    public function structureCreationParent(User $actor, string $kind, ?int $organizationId): ?Organization
    {
        abort_unless(in_array($kind, ['organization', 'brand'], true), 404);
        if ($kind === 'organization') {
            abort_unless($organizationId === null, 404);
            Gate::forUser($actor)->authorize('create', Organization::class);

            return null;
        }
        $organization = Organization::query()->select(['id', 'owner_user_id', 'name', 'deleted_at'])->whereKey($organizationId)->firstOrFail();
        Gate::forUser($actor)->authorize('create', [Brand::class, $organization]);

        return $organization;
    }

    public function canContinueSetup(User $actor, Branch $branch): bool
    {
        if (! Gate::forUser($actor)->allows('update', $branch)) {
            return false;
        }
        $organization = Organization::query()->select(['id', 'owner_user_id', 'deleted_at'])->whereKey($branch->organization_id)->first();
        if ($organization === null || ! Gate::forUser($actor)->allows('createAdditional', [RestaurantOnboarding::class, $organization])
            || ! Brand::query()->where('organization_id', $organization->id)->whereKey($branch->brand_id)->exists()) {
            return false;
        }
        $setup = RestaurantOnboarding::query()->where('branch_id', $branch->id)->first();

        return $setup === null || Gate::forUser($actor)->allows('view', $setup);
    }

    /** @return array<int,string> */
    public function filterOrganizations(User $actor, string $search, mixed $selected = null): array
    {
        $rows = $this->creationOrganizations($actor, $search, $selected);
        if ((is_int($selected) || (is_string($selected) && ctype_digit($selected))) && ! isset($rows[(int) $selected])) {
            $chosen = $this->organizationQuery($actor)->onlyTrashed()->whereKey($selected)->first();
            if ($chosen !== null && Gate::forUser($actor)->allows('restore', $chosen)) {
                $rows[$chosen->id] = $chosen->name;
            }
        }

        return $rows;
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
        return $this->brandOptions($actor, $organizationId, $search, $selected, false);
    }

    /** @return array<int,string> */
    public function filterBrands(User $actor, mixed $organizationId, string $search, mixed $selected = null): array
    {
        return $this->brandOptions($actor, $organizationId, $search, $selected, true);
    }

    /** @return array<int,string> */
    private function brandOptions(User $actor, mixed $organizationId, string $search, mixed $selected, bool $includeArchived): array
    {
        if (! is_int($organizationId) && ! (is_string($organizationId) && ctype_digit($organizationId))) {
            return [];
        }
        $organization = $this->organizationQuery($actor)->when($includeArchived, fn ($query) => $query->withTrashed())->whereKey($organizationId)->firstOrFail();
        Gate::forUser($actor)->authorize($organization->trashed() ? 'restore' : 'view', $organization);
        $query = $organization->brands()->select(['id', 'organization_id', 'name', 'deleted_at']);
        $rows = (clone $query)->where('name', 'like', '%'.trim($search).'%')->orderBy('name')->limit(20)->pluck('name', 'id')->all();
        if (is_int($selected) || (is_string($selected) && ctype_digit($selected))) {
            $chosen = $query->when($includeArchived, fn ($query) => $query->withTrashed())->whereKey($selected)->first();
            if ($chosen instanceof Brand && (! $chosen->trashed() || Gate::forUser($actor)->allows('restore', $chosen))) {
                $rows[$chosen->id] = $chosen->name;
            }
        }

        return $rows;
    }

    /** @param array<string,string> $filters @return array<string,string> */
    public function consistentFilters(User $actor, array $filters): array
    {
        if ($filters['brand'] === '') {
            return $filters;
        }
        $brand = $filters['organization'] === '' ? null : Brand::query()->select(['id', 'organization_id', 'deleted_at'])
            ->when($filters['lifecycle'] === 'archived', fn ($query) => $query->withTrashed())
            ->whereIn('organization_id', $this->organizationQuery($actor)->select('id'))
            ->where('organization_id', $filters['organization'])->whereKey($filters['brand'])->first();
        if ($brand === null || ! Gate::forUser($actor)->allows($brand->trashed() ? 'restore' : 'view', $brand)) {
            $filters['brand'] = '';
        }

        return $filters;
    }

    /** @param array<string,int|string> $parameters @return array<string,mixed> */
    public function row(Organization|Brand|Branch $resource, string $kind, array $parameters): array
    {
        $setup = $resource instanceof Branch ? $resource->restaurantOnboarding : null;
        $confirmed = $resource instanceof Branch ? (bool) $resource->getAttribute('preparation_confirmed') : null;
        $properties = route('restaurants.index', [...$parameters, 'kind' => $kind, 'object' => $resource->id]);
        $continuation = $resource instanceof Branch && $resource->getAttribute('can_continue_setup') && $setup !== null && $setup->completed_at === null ? route('restaurants.setup', ['setup' => $setup->id]) : null;
        $work = $resource instanceof Branch && ! $resource->trashed() ? route('dashboard', ['branch' => $resource->id]) : null;

        return [
            'id' => $resource->id, 'name' => $resource->name, 'kind' => $kind,
            'logo_url' => $resource->logoUrl(),
            'description' => $resource instanceof Branch ? implode(' · ', [$resource->organization->name, $resource->brand->name, $resource->city, $resource->address]) : '',
            'state' => $resource->trashed() ? __('center.archived') : ($resource instanceof Branch ? ($resource->is_active ? __('center.administrative_active') : __('center.administrative_inactive')) : ''),
            'properties' => $properties,
            'children' => $resource instanceof Organization ? route('restaurants.index', [...$parameters, 'view' => 'structure', 'organization' => $resource->id, 'brand' => '', 'brandsPage' => 1]) : ($resource instanceof Brand ? route('restaurants.index', [...$parameters, 'view' => 'restaurants', 'organization' => $resource->organization_id, 'brand' => $resource->id, 'page' => 1]) : null),
            'work' => $work,
            'setup' => $continuation,
            'preparation_confirmed' => $confirmed,
            'missing_step' => $confirmed === false ? 'confirmation' : null,
            'primary_url' => $continuation ?? ($confirmed ? $work : null) ?? $properties,
            'primary_label' => $continuation !== null ? 'center.continue' : ($confirmed && $work !== null ? 'center.open_work' : 'center.properties'),
        ];
    }

    public function identity(User $actor, string $kind, int $id): Organization|Brand|Branch
    {
        $query = match ($kind) {
            'organization' => Organization::query()->select(['id', 'owner_user_id', 'name', 'logo_path', 'deleted_at']),
            'brand' => Brand::query()->select(['id', 'organization_id', 'name', 'logo_path', 'deleted_at'])->with('organization:id,name'),
            'branch' => Branch::query()->select(['id', 'organization_id', 'brand_id', 'name', 'address', 'city', 'country', 'timezone', 'currency', 'is_active', 'logo_path', 'deleted_at'])->with(['organization:id,name', 'brand:id,name']),
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
