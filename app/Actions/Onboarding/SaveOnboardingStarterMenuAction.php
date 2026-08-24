<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Enums\KitchenDepartmentType;
use App\Enums\MenuStatus;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\KitchenDepartment;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\RestaurantOnboarding;
use App\Models\User;
use App\Support\MoneyFormatter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SaveOnboardingStarterMenuAction
{
    /** @param array{menu_name: string, category_name: string, item_name: string, item_price: string|int} $data */
    public function handle(User $user, int $onboardingId, array $data): RestaurantOnboarding
    {
        return DB::transaction(function () use ($user, $onboardingId, $data): RestaurantOnboarding {
            $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->whereKey($onboardingId)->lockForUpdate()->firstOrFail();
            $branch = Branch::query()
                ->select(['id', 'organization_id', 'brand_id', 'name'])
                ->where('organization_id', $onboarding->organization_id)
                ->where('brand_id', $onboarding->brand_id)
                ->whereHas('brand', fn ($query) => $query->where('organization_id', $onboarding->organization_id))
                ->whereKey($onboarding->branch_id)
                ->firstOrFail();
            Gate::forUser($user)->authorize('update', $onboarding);
            $area = AreaNode::query()
                ->select(['id', 'branch_id'])
                ->where('branch_id', $branch->id)
                ->whereKey($onboarding->area_node_id)
                ->firstOrFail();
            $servicePoints = $onboarding->servicePoints()
                ->withTrashed()
                ->select(['service_points.id', 'service_points.branch_id', 'service_points.area_node_id', 'service_points.type', 'service_points.deleted_at'])
                ->withExists('activeQrCode')
                ->get();
            $servicePointCount = $servicePoints->count();
            $servicePointsWithQrCount = $servicePoints->where('active_qr_code_exists', true)->count();

            abort_if($servicePoints->contains(fn ($point): bool => (int) $point->branch_id !== (int) $branch->id), 404);
            abort_unless($onboarding->hasCompleteServicePointSet(
                $servicePoints,
                (int) $branch->id,
                (int) $area->id,
                $servicePointCount,
            ), 409);

            if ($servicePointCount === 0 || $servicePointsWithQrCount !== $servicePointCount) {
                throw ValidationException::withMessages([
                    'form.menuName' => __('ui.onboarding.restaurant_setup.validation.qr_required'),
                ]);
            }

            Gate::forUser($user)->authorize('manageMenu', $branch);
            Gate::forUser($user)->authorize('changeMenuPrices', $branch);
            Gate::forUser($user)->authorize('changeMenuAvailability', $branch);

            $menu = $this->menu($onboarding, $branch, $user, $data['menu_name']);
            $category = $this->category($onboarding, $menu, $user, $data['category_name']);
            $item = $this->item($onboarding, $branch, $menu, $category, $user, $data);

            $onboarding->forceFill([
                'menu_id' => $menu->id,
                'menu_category_id' => $category->id,
                'menu_item_id' => $item->id,
                'completed_at' => $onboarding->completed_at ?? now(),
            ])->save();

            return $onboarding->refresh();
        }, attempts: 3);
    }

    private function menu(RestaurantOnboarding $onboarding, Branch $branch, User $user, string $name): Menu
    {
        $menu = $onboarding->menu_id === null ? null : Menu::query()
            ->withTrashed()
            ->select(['id', 'branch_id', 'name', 'status', 'sort_order', 'deleted_at'])
            ->where('branch_id', $branch->id)->whereKey($onboarding->menu_id)->firstOrFail();

        if ($menu instanceof Menu) {
            if ($menu->trashed()) {
                Gate::forUser($user)->authorize('restoreCheckpointResource', [$onboarding, $menu]);
                $menu->restore();
            }

            $menu->setRelation('branch', $branch);
            Gate::forUser($user)->authorize('update', $menu);
            $menu->fill(['name' => $name, 'sort_order' => 0]);
            $menu->forceFill(['status' => MenuStatus::Active])->save();

            return $menu;
        }

        Gate::forUser($user)->authorize('create', [Menu::class, $branch]);
        $menu = $branch->menus()->make(['name' => $name, 'sort_order' => 0]);
        $menu->forceFill(['status' => MenuStatus::Active])->save();

        return $menu;
    }

    private function category(RestaurantOnboarding $onboarding, Menu $menu, User $user, string $name): MenuCategory
    {
        $category = $onboarding->menu_category_id === null ? null : $menu->categories()
            ->withTrashed()
            ->select(['id', 'menu_id', 'name', 'description', 'icon', 'sort_order', 'is_active', 'deleted_at'])
            ->whereKey($onboarding->menu_category_id)->firstOrFail();

        if (! $category instanceof MenuCategory) {
            return $menu->categories()->create([
                'name' => $name, 'description' => null, 'icon' => 'book-open', 'sort_order' => 0, 'is_active' => true,
            ]);
        }

        if ($category->trashed()) {
            Gate::forUser($user)->authorize('restoreCheckpointResource', [$onboarding, $category]);
            $category->restore();
        }

        $category->fill(['name' => $name, 'description' => null, 'icon' => 'book-open', 'sort_order' => 0, 'is_active' => true])->save();

        return $category;
    }

    /** @param array{menu_name: string, category_name: string, item_name: string, item_price: string|int} $data */
    private function item(RestaurantOnboarding $onboarding, Branch $branch, Menu $menu, MenuCategory $category, User $user, array $data): MenuItem
    {
        $values = [
            'category_id' => $category->id,
            'kitchen_department_id' => $this->defaultKitchenDepartmentId($branch),
            'name' => $data['item_name'],
            'description' => null,
            'price_cents' => MoneyFormatter::decimalToCents($data['item_price']),
            'is_available' => true,
            'sort_order' => 0,
        ];
        $item = $onboarding->menu_item_id === null ? null : $menu->items()
            ->withTrashed()
            ->select(['id', 'menu_id', 'category_id', 'kitchen_department_id', 'name', 'description', 'price_cents', 'is_available', 'sort_order', 'deleted_at'])
            ->where('category_id', $category->id)->whereKey($onboarding->menu_item_id)->firstOrFail();

        if (! $item instanceof MenuItem) {
            return $menu->items()->create($values);
        }

        if ($item->trashed()) {
            Gate::forUser($user)->authorize('restoreCheckpointResource', [$onboarding, $item]);
            $item->restore();
        }

        $item->fill($values)->save();

        return $item;
    }

    private function defaultKitchenDepartmentId(Branch $branch): ?int
    {
        return KitchenDepartment::query()
            ->select(['id', 'branch_id', 'type', 'is_active', 'sort_order'])
            ->where('branch_id', $branch->id)
            ->where('type', KitchenDepartmentType::Kitchen->value)
            ->where('is_active', true)
            ->oldest('sort_order')->oldest('id')->value('id');
    }
}
