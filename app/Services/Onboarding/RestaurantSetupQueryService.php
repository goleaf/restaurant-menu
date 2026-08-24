<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\RestaurantOnboarding;
use App\Models\ServicePoint;
use App\Models\User;
use App\Support\MoneyFormatter;
use App\Support\RestaurantSetupOptions;
use Illuminate\Support\Collection;

final class RestaurantSetupQueryService
{
    /**
     * @return array{onboarding: RestaurantOnboarding|null, step: int, highest_step: int, completed: bool, done: array<int, bool>, summary: array<string, string|int|null>, form: array<string, string|int>}
     */
    public function presentation(User $user, ?int $onboardingId = null): array
    {
        $onboarding = $this->findForUser($user, $onboardingId);

        if (! $onboarding instanceof RestaurantOnboarding) {
            return ['onboarding' => null, 'step' => 1, 'highest_step' => 1, 'completed' => false, 'done' => array_fill(1, 8, false), 'summary' => $this->emptySummary(), 'form' => []];
        }

        $organizationReference = $this->organization($onboarding, $user);
        $brandReference = $this->brand($onboarding, $organizationReference);
        $branchReference = $this->branch($onboarding, $brandReference, $organizationReference);
        $areaReference = $this->area($onboarding, $branchReference);
        $menuReference = $this->menu($onboarding, $branchReference);
        $categoryReference = $this->category($onboarding, $menuReference);
        $itemReference = $this->item($onboarding, $menuReference, $categoryReference);

        $organization = $organizationReference instanceof Organization && ! $organizationReference->trashed() ? $organizationReference : null;
        $brand = $organization instanceof Organization && $brandReference instanceof Brand && ! $brandReference->trashed() ? $brandReference : null;
        $branch = $brand instanceof Brand && $branchReference instanceof Branch && ! $branchReference->trashed() ? $branchReference : null;
        $area = $branch instanceof Branch && $areaReference instanceof AreaNode && ! $areaReference->trashed() ? $areaReference : null;
        $linkedPoints = $onboarding->servicePoints;
        $scopedPoints = $areaReference instanceof AreaNode && $branchReference instanceof Branch
            ? $linkedPoints->filter(fn (ServicePoint $point): bool => (int) $point->branch_id === (int) $branchReference->id && ((int) $point->area_node_id === (int) $areaReference->id || $point->area_node_id === null))
            : collect();
        $points = $linkedPoints->reject(fn (ServicePoint $point): bool => $point->trashed());
        $pointsValid = $area instanceof AreaNode
            && $onboarding->hasCompleteServicePointSet(
                $linkedPoints,
                (int) $branch->id,
                (int) $area->id,
                (int) $onboarding->getAttribute('service_points_count'),
            );
        $qrCodes = $pointsValid ? $points->map(fn (ServicePoint $point): ?QrCode => $point->activeQrCode)->filter() : collect();
        $qrValid = $pointsValid && $qrCodes->count() === $points->count();
        $menu = $branch instanceof Branch && $menuReference instanceof Menu && ! $menuReference->trashed() ? $menuReference : null;
        $category = $menu instanceof Menu && $categoryReference instanceof MenuCategory && ! $categoryReference->trashed() ? $categoryReference : null;
        $item = $category instanceof MenuCategory && $itemReference instanceof MenuItem && ! $itemReference->trashed() ? $itemReference : null;
        $menuValid = $qrValid && $item instanceof MenuItem;
        $completed = $menuValid && $onboarding->completed_at !== null;
        $step = match (true) {
            $completed => 8, $qrValid => 7, $pointsValid => 6, $area instanceof AreaNode => 5,
            $branch instanceof Branch => 4, $brand instanceof Brand => 3, $organization instanceof Organization => 2,
            default => 1,
        };
        $qrCode = $qrCodes->first();

        return [
            'onboarding' => $onboarding,
            'step' => $step,
            'highest_step' => $step,
            'completed' => $completed,
            'done' => [1 => $organization instanceof Organization, 2 => $brand instanceof Brand, 3 => $branch instanceof Branch, 4 => $area instanceof AreaNode, 5 => $pointsValid, 6 => $qrValid, 7 => $menuValid, 8 => $completed],
            'summary' => [
                'organization' => $organization?->name, 'brand' => $brand?->name, 'branch' => $branch?->name, 'area' => $area?->name,
                'service_points' => $pointsValid ? $points->count() : 0, 'qr_codes' => $qrValid ? $qrCodes->count() : 0, 'menu' => $menu?->name,
                'guest_url' => $qrCode instanceof QrCode ? route('public.qr.show', ['token' => $qrCode->public_token]) : null,
                'branch_url' => $organization instanceof Organization && $brand instanceof Brand ? route('organizations.brands.branches.index', [$organization, $brand]) : null,
                'menu_url' => $organization instanceof Organization && $brand instanceof Brand && $branch instanceof Branch ? route('organizations.brands.branches.menu.index', [$organization, $brand, $branch]) : null,
                'print_url' => $organization instanceof Organization && $brand instanceof Brand && $branch instanceof Branch ? route('organizations.brands.branches.qr.print', [$organization, $brand, $branch]) : null,
            ],
            'form' => $this->formValues(
                $organizationReference,
                $brandReference,
                $branchReference,
                $areaReference,
                $scopedPoints,
                $menuReference,
                $categoryReference,
                $itemReference,
                $areaReference instanceof AreaNode ? $onboarding->expectedServicePointCount($onboarding->servicePoints->count()) : 0,
            ),
        ];
    }

    public function findForUser(User $user, ?int $onboardingId = null): ?RestaurantOnboarding
    {
        $onboarding = RestaurantOnboarding::query()
            ->select(['id', 'user_id', 'organization_id', 'brand_id', 'branch_id', 'area_node_id', 'expected_service_point_count', 'menu_id', 'menu_category_id', 'menu_item_id', 'completed_at', 'created_at', 'updated_at'])
            ->withCount('servicePoints')
            ->where('user_id', $user->id)
            ->when($onboardingId !== null, fn ($query) => $query->whereKey($onboardingId))
            ->first();

        if (! $onboarding instanceof RestaurantOnboarding) {
            return null;
        }

        return $this->loadScopedRelations($onboarding, $user);
    }

    public function findForUserOrFail(User $user, int $onboardingId): RestaurantOnboarding
    {
        return $this->findForUser($user, $onboardingId) ?? abort(404);
    }

    private function organization(RestaurantOnboarding $state, User $user): ?Organization
    {
        $model = $state->organization;

        return $model instanceof Organization && (int) $model->owner_user_id === (int) $user->id ? $model : null;
    }

    private function loadScopedRelations(RestaurantOnboarding $onboarding, User $user): RestaurantOnboarding
    {
        $onboarding->setRelations([
            'organization' => null,
            'brand' => null,
            'branch' => null,
            'areaNode' => null,
            'servicePoints' => $onboarding->newCollection(),
            'menu' => null,
            'menuCategory' => null,
            'menuItem' => null,
        ]);

        $onboarding->load([
            'organization' => fn ($query) => $query
                ->select(['id', 'owner_user_id', 'name', 'deleted_at'])
                ->where('owner_user_id', $user->id),
        ]);

        $organization = $onboarding->organization;

        if (! $organization instanceof Organization) {
            return $onboarding;
        }

        $onboarding->load([
            'brand' => fn ($query) => $query
                ->select(['id', 'organization_id', 'name', 'deleted_at'])
                ->where('organization_id', $organization->id),
        ]);

        $brand = $onboarding->brand;

        if (! $brand instanceof Brand) {
            return $onboarding;
        }

        $onboarding->load([
            'branch' => fn ($query) => $query
                ->select(['id', 'organization_id', 'brand_id', 'name', 'address', 'city', 'country', 'timezone', 'currency', 'is_active', 'deleted_at'])
                ->where('organization_id', $organization->id)
                ->where('brand_id', $brand->id),
        ]);

        $branch = $onboarding->branch;

        if (! $branch instanceof Branch) {
            return $onboarding;
        }

        $onboarding->load([
            'areaNode' => fn ($query) => $query
                ->select(['id', 'branch_id', 'type', 'name', 'icon', 'sort_order', 'is_active', 'deleted_at'])
                ->where('branch_id', $branch->id),
            'servicePoints' => fn ($query) => $query
                ->withTrashed()
                ->select(['service_points.id', 'service_points.branch_id', 'service_points.area_node_id', 'service_points.type', 'service_points.name', 'service_points.display_number', 'service_points.capacity', 'service_points.icon', 'service_points.is_active', 'service_points.deleted_at'])
                ->where('service_points.branch_id', $branch->id),
            'servicePoints.activeQrCode:id,service_point_id,public_token,status',
            'menu' => fn ($query) => $query
                ->select(['id', 'branch_id', 'name', 'status', 'sort_order', 'deleted_at'])
                ->where('branch_id', $branch->id),
        ]);

        $menu = $onboarding->menu;

        if (! $menu instanceof Menu) {
            return $onboarding;
        }

        $onboarding->load([
            'menuCategory' => fn ($query) => $query
                ->select(['id', 'menu_id', 'name', 'description', 'icon', 'sort_order', 'is_active', 'deleted_at'])
                ->where('menu_id', $menu->id),
        ]);

        $category = $onboarding->menuCategory;

        if (! $category instanceof MenuCategory) {
            return $onboarding;
        }

        $onboarding->load([
            'menuItem' => fn ($query) => $query
                ->select(['id', 'menu_id', 'category_id', 'kitchen_department_id', 'name', 'price_cents', 'is_available', 'sort_order', 'deleted_at'])
                ->where('menu_id', $menu->id)
                ->where('category_id', $category->id),
        ]);

        return $onboarding;
    }

    private function brand(RestaurantOnboarding $state, ?Organization $parent): ?Brand
    {
        $model = $state->brand;

        return $model instanceof Brand && $parent instanceof Organization && (int) $model->organization_id === (int) $parent->id ? $model : null;
    }

    private function branch(RestaurantOnboarding $state, ?Brand $brand, ?Organization $organization): ?Branch
    {
        $model = $state->branch;

        return $model instanceof Branch && $brand instanceof Brand && $organization instanceof Organization && (int) $model->brand_id === (int) $brand->id && (int) $model->organization_id === (int) $organization->id ? $model : null;
    }

    private function area(RestaurantOnboarding $state, ?Branch $parent): ?AreaNode
    {
        $model = $state->areaNode;

        return $model instanceof AreaNode && $parent instanceof Branch && (int) $model->branch_id === (int) $parent->id ? $model : null;
    }

    private function menu(RestaurantOnboarding $state, ?Branch $parent): ?Menu
    {
        $model = $state->menu;

        return $model instanceof Menu && $parent instanceof Branch && (int) $model->branch_id === (int) $parent->id ? $model : null;
    }

    private function category(RestaurantOnboarding $state, ?Menu $parent): ?MenuCategory
    {
        $model = $state->menuCategory;

        return $model instanceof MenuCategory && $parent instanceof Menu && (int) $model->menu_id === (int) $parent->id ? $model : null;
    }

    private function item(RestaurantOnboarding $state, ?Menu $menu, ?MenuCategory $category): ?MenuItem
    {
        $model = $state->menuItem;

        return $model instanceof MenuItem && $menu instanceof Menu && $category instanceof MenuCategory && (int) $model->menu_id === (int) $menu->id && (int) $model->category_id === (int) $category->id ? $model : null;
    }

    /** @param Collection<int, ServicePoint> $points
     * @return array<string, string|int>
     */
    private function formValues(?Organization $organization, ?Brand $brand, ?Branch $branch, ?AreaNode $area, Collection $points, ?Menu $menu, ?MenuCategory $category, ?MenuItem $item, int $linkedPointCount): array
    {
        $point = $points->first();

        return array_filter([
            'organizationName' => $organization?->name, 'brandName' => $brand?->name,
            'branchName' => $branch?->name, 'branchAddress' => $branch?->address, 'branchCity' => $branch?->city,
            'branchCountryCode' => $branch instanceof Branch ? RestaurantSetupOptions::countryCode($branch->country) : null,
            'branchTimezone' => $branch?->timezone, 'branchCurrency' => $branch?->currency,
            'areaName' => $area?->name, 'areaType' => $area?->type?->value, 'areaIcon' => $area?->icon,
            'tableCount' => $linkedPointCount ?: null,
            'tablePrefix' => $point instanceof ServicePoint ? preg_replace('/\s+\d+$/u', '', $point->name) : null,
            'tableCapacity' => $point?->capacity, 'menuName' => $menu?->name, 'categoryName' => $category?->name,
            'itemName' => $item?->name, 'itemPrice' => $item instanceof MenuItem ? MoneyFormatter::centsToDecimal($item->price_cents) : null,
        ], fn ($value): bool => $value !== null);
    }

    /** @return array<string, string|int|null> */
    private function emptySummary(): array
    {
        return ['organization' => null, 'brand' => null, 'branch' => null, 'area' => null, 'service_points' => 0, 'qr_codes' => 0, 'menu' => null, 'guest_url' => null, 'branch_url' => null, 'menu_url' => null, 'print_url' => null];
    }
}
