<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Actions\Menus\CreateMenuItemAction;
use App\Data\Menus\MenuItemData;
use App\Enums\KitchenDepartmentType;
use App\Enums\MenuStatus;
use App\Models\Branch;
use App\Models\KitchenDepartment;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\RestaurantOnboarding;
use App\Models\User;
use App\Support\MoneyFormatter;
use App\Support\Validation\Menus\MenuItemRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class SaveOnboardingStarterMenuAction
{
    public function __construct(private CreateMenuItemAction $createItem) {}

    /** @param array<string, mixed> $data */
    public function handle(User $user, int $onboardingId, array $data): RestaurantOnboarding
    {
        $validated = Validator::make([
            'menuName' => $data['menu_name'] ?? null, 'categoryName' => $data['category_name'] ?? null,
            'itemName' => $data['item_name'] ?? null, 'itemPrice' => $data['item_price'] ?? null,
        ], MenuItemRules::onboardingStarterMenu())->validate();

        return DB::transaction(function () use ($user, $onboardingId, $validated): RestaurantOnboarding {
            $user = User::query()->whereKey($user->id)->firstOrFail();
            $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->whereKey($onboardingId)->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize('update', $onboarding);
            abort_if($onboarding->branch_id === null, 409);
            $branch = Branch::query()->select(['id', 'organization_id', 'brand_id', 'name'])
                ->where('organization_id', $onboarding->organization_id)->where('brand_id', $onboarding->brand_id)
                ->whereHas('brand', fn ($query) => $query->where('organization_id', $onboarding->organization_id))
                ->whereKey($onboarding->branch_id)->firstOrFail();
            Gate::forUser($user)->authorize('manageMenu', $branch);
            if ($onboarding->menu_id !== null) {
                return $this->preserveExisting($onboarding, $branch, $validated);
            }
            Gate::forUser($user)->authorize('changeMenuPrices', $branch);
            Gate::forUser($user)->authorize('create', [Menu::class, $branch]);
            $menu = $branch->menus()->make(['name' => $validated['menuName'], 'sort_order' => 0]);
            $menu->forceFill(['status' => MenuStatus::Draft]);
            if (! $menu->save()) {
                throw new RuntimeException('Required starter menu could not be saved.');
            }
            $category = $menu->categories()->make(['name' => $validated['categoryName'], 'description' => null, 'icon' => 'book-open', 'sort_order' => 0, 'is_active' => true]);
            if (! $category->save()) {
                throw new RuntimeException('Required starter category could not be saved.');
            }
            $item = $this->createItem->handle($user, $branch, $menu, $category, $this->defaultKitchenDepartmentId($branch), new MenuItemData(
                name: $validated['itemName'], description: null, weight: null, volume: null, calories: null, sortOrder: 0,
                price: $validated['itemPrice'], isAvailable: false, translations: ['en' => ['name' => $validated['itemName'], 'description' => null]],
            ), unpublished: true);
            $onboarding->forceFill(['menu_id' => $menu->id, 'menu_category_id' => $category->id, 'menu_item_id' => $item->id, 'setup_version' => $onboarding->setup_version + 1]);
            if (! $onboarding->save()) {
                throw new RuntimeException('Required menu setup could not be saved.');
            }

            return $onboarding->refresh();
        }, attempts: 3);
    }

    /** @param array<string,mixed> $data */
    private function preserveExisting(RestaurantOnboarding $setup, Branch $branch, array $data): RestaurantOnboarding
    {
        $menu = Menu::query()->select(['id', 'name'])->where('branch_id', $branch->id)->whereKey($setup->menu_id)->firstOrFail();
        $category = MenuCategory::query()->select(['id', 'name'])->where('menu_id', $menu->id)->whereKey($setup->menu_category_id)->firstOrFail();
        $item = MenuItem::query()->select(['id', 'name', 'price_cents'])->where('menu_id', $menu->id)->where('category_id', $category->id)->whereKey($setup->menu_item_id)->firstOrFail();
        if ($menu->name !== $data['menuName'] || $category->name !== $data['categoryName'] || $item->name !== $data['itemName'] || $item->price_cents !== MoneyFormatter::decimalToCents($data['itemPrice'])) {
            throw ValidationException::withMessages(['form.menuName' => __('center.use_menu_editor')]);
        }

        return $setup;
    }

    private function defaultKitchenDepartmentId(Branch $branch): ?int
    {
        return KitchenDepartment::query()->select(['id'])->where('branch_id', $branch->id)
            ->where('type', KitchenDepartmentType::Kitchen->value)->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')->value('id');
    }
}
