<?php

declare(strict_types=1);

namespace App\Services\Menus;

use App\Actions\Menus\GetMenuAvailabilityStatusAction;
use App\Enums\MenuAllergen;
use App\Enums\MenuDietaryLabel;
use App\Enums\MenuStatus;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\KitchenDepartment;
use App\Models\Menu;
use App\Models\MenuAvailabilitySchedule;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Organization;
use App\Support\MoneyFormatter;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final readonly class CatalogData
{
    public function __construct(private GetMenuAvailabilityStatusAction $getMenuAvailabilityStatus) {}

    /**
     * @return array<string, mixed>
     */
    public function for(Branch $branch, string $categoryMenuId, string $itemMenuId, string $editingItemMenuId): array
    {
        $menus = $this->menus($branch);
        $departments = $this->departments($branch);

        return [
            'menuRows' => $menus->map(fn (Menu $menu): array => $this->presentMenu($menu, $branch))->all(),
            'menuStatusOptions' => MenuStatus::options(),
            'iconOptions' => self::iconOptions(),
            'allergenOptions' => MenuAllergen::options(),
            'dietaryLabelOptions' => MenuDietaryLabel::options(),
            'menuOptions' => $menus->map(fn (Menu $menu): array => [
                'value' => (string) $menu->id,
                'label' => $menu->name,
            ])->values()->all(),
            'categoryMenuOptions' => $this->categoryOptions($menus, $categoryMenuId),
            'itemCategoryOptions' => $this->categoryOptions($menus, $itemMenuId, false),
            'editingItemCategoryOptions' => $this->categoryOptions($menus, $editingItemMenuId, false),
            'kitchenDepartmentOptions' => $this->departmentOptions($departments),
            'activeKitchenDepartmentOptions' => $this->departmentOptions($departments, false),
            'scheduleDayOptions' => GetMenuAvailabilityStatusAction::dayLabels(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function iconOptions(): array
    {
        return [
            'bookmark' => __('permissions.actions.default'),
            'cake' => __('ui.livewire.organizations.brands.branches.menu.index.desserts'),
            'beaker' => __('ui.livewire.bar.dashboard.drinks'),
            'sparkles' => __('ui.livewire.organizations.brands.branches.menu.index.specials'),
            'shopping-bag' => __('ui.livewire.organizations.brands.branches.menu.index.takeaway'),
            'fire' => __('ui.livewire.organizations.brands.branches.menu.index.hot'),
            'sun' => __('ui.livewire.organizations.brands.branches.menu.index.seasonal'),
        ];
    }

    public static function supportedCategoryIcon(?string $icon): string
    {
        return array_key_exists((string) $icon, self::iconOptions()) ? (string) $icon : 'bookmark';
    }

    public function findBranchMenu(Branch $branch, int $menuId): Menu
    {
        return $branch->menus()
            ->select(['id', 'branch_id', 'name', 'status', 'sort_order', 'created_at', 'updated_at'])
            ->whereKey($menuId)
            ->firstOrFail();
    }

    public function findBranchCategory(int $branchId, int $categoryId): MenuCategory
    {
        return MenuCategory::query()
            ->select(['id', 'menu_id', 'parent_id', 'name', 'description', 'image', 'icon', 'sort_order', 'is_active', 'created_at', 'updated_at'])
            ->whereHas('menu', fn ($query) => $query->where('branch_id', $branchId))
            ->whereKey($categoryId)
            ->firstOrFail();
    }

    public function findBranchMenuSchedule(int $branchId, int $scheduleId): MenuAvailabilitySchedule
    {
        return MenuAvailabilitySchedule::query()
            ->select(['id', 'menu_id', 'day_of_week', 'starts_at', 'ends_at', 'created_at', 'updated_at'])
            ->whereHas('menu', fn ($query) => $query->where('branch_id', $branchId))
            ->whereKey($scheduleId)
            ->firstOrFail();
    }

    public function findMenuCategory(Menu $menu, int $categoryId): MenuCategory
    {
        return $menu->categories()
            ->select(['id', 'menu_id', 'parent_id', 'name', 'description', 'image', 'icon', 'sort_order', 'is_active', 'created_at', 'updated_at'])
            ->whereKey($categoryId)
            ->firstOrFail();
    }

    public function findBranchItem(int $branchId, int $itemId): MenuItem
    {
        return MenuItem::query()
            ->select(['id', 'menu_id', 'category_id', 'kitchen_department_id', 'name', 'description', 'price_cents', 'allergens', 'dietary_labels', 'image', 'weight', 'volume', 'calories', 'is_available', 'sort_order', 'created_at', 'updated_at'])
            ->whereHas('menu', fn ($query) => $query->where('branch_id', $branchId))
            ->whereKey($itemId)
            ->firstOrFail();
    }

    public function firstCategoryIdForMenu(Branch $branch, string $menuId): string
    {
        if ($menuId === '') {
            return '';
        }

        $categoryId = MenuCategory::query()
            ->select('menu_categories.id')
            ->where('menu_id', (int) $menuId)
            ->whereHas('menu', fn ($query) => $query->where('branch_id', $branch->id))
            ->oldest('sort_order')->oldest('name')->oldest('id')
            ->value('menu_categories.id');

        return is_int($categoryId) ? (string) $categoryId : '';
    }

    public function organization(int $organizationId): Organization
    {
        return Organization::query()
            ->select(['id', 'name'])
            ->findOrFail($organizationId);
    }

    public function brand(int $brandId): Brand
    {
        return Brand::query()
            ->select(['id', 'organization_id', 'name'])
            ->findOrFail($brandId);
    }

    public function branch(int $branchId): Branch
    {
        return Branch::query()
            ->select(['id', 'organization_id', 'brand_id', 'name', 'currency', 'timezone'])
            ->findOrFail($branchId);
    }

    /** @return EloquentCollection<int, Menu> */
    public function availabilityMenus(Branch $branch): EloquentCollection
    {
        return $branch->menus()
            ->select(['id', 'branch_id', 'name', 'sort_order'])
            ->with(['items' => fn ($query) => $query
                ->select([
                    'id',
                    'menu_id',
                    'category_id',
                    'kitchen_department_id',
                    'name',
                    'price_cents',
                    'is_available',
                    'sort_order',
                    'updated_at',
                ])
                ->with([
                    'category' => fn ($categoryQuery) => $categoryQuery->select(['id', 'menu_id', 'name']),
                    'kitchenDepartment' => fn ($departmentQuery) => $departmentQuery->select(['id', 'branch_id', 'name']),
                ])
                ->orderBy('sort_order')->orderBy('name')->orderBy('id')])
            ->orderBy('sort_order')->orderBy('name')->orderBy('id')
            ->get();
    }

    /** @return EloquentCollection<int, ModifierGroup> */
    public function modifierGroups(Branch $branch): EloquentCollection
    {
        return $branch->modifierGroups()
            ->select(['id', 'branch_id', 'name', 'is_required', 'min_select', 'max_select', 'sort_order', 'created_at', 'updated_at'])
            ->with(['options' => fn ($query) => $query
                ->select(['id', 'modifier_group_id', 'name', 'price_delta_cents', 'is_available', 'sort_order', 'created_at', 'updated_at'])
                ->orderBy('sort_order')->orderBy('name')->orderBy('id')])
            ->withCount('items')
            ->get();
    }

    /** @return list<array{value: string, label: string}> */
    public function menuOptions(Branch $branch): array
    {
        return $branch->menus()
            ->select(['id', 'branch_id', 'name', 'sort_order'])
            ->orderBy('sort_order')->orderBy('name')->orderBy('id')
            ->get()
            ->map(fn (Menu $menu): array => ['value' => (string) $menu->id, 'label' => $menu->name])
            ->all();
    }

    /** @return list<array{value: string, label: string}> */
    public function itemOptions(int $branchId, string $menuId): array
    {
        if ($menuId === '') {
            return [];
        }

        return MenuItem::query()
            ->select(['id', 'menu_id', 'name', 'sort_order'])
            ->where('menu_id', (int) $menuId)
            ->whereHas('menu', fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('sort_order')->orderBy('name')->orderBy('id')
            ->get()
            ->map(fn (MenuItem $item): array => ['value' => (string) $item->id, 'label' => $item->name])
            ->all();
    }

    public function findModifierGroup(Branch $branch, int $groupId): ModifierGroup
    {
        return $branch->modifierGroups()
            ->select(['id', 'branch_id', 'name', 'is_required', 'min_select', 'max_select', 'sort_order', 'created_at', 'updated_at'])
            ->whereKey($groupId)
            ->firstOrFail();
    }

    public function findModifierOption(int $branchId, int $optionId): ModifierOption
    {
        return ModifierOption::query()
            ->select(['id', 'modifier_group_id', 'name', 'price_delta_cents', 'is_available', 'sort_order', 'created_at', 'updated_at'])
            ->whereHas('group', fn ($query) => $query->where('branch_id', $branchId))
            ->whereKey($optionId)
            ->firstOrFail();
    }

    public function findModifierItem(int $branchId, int $itemId): MenuItem
    {
        return MenuItem::query()
            ->select(['id', 'menu_id', 'category_id', 'name', 'sort_order', 'created_at', 'updated_at'])
            ->whereHas('menu', fn ($query) => $query->where('branch_id', $branchId))
            ->whereKey($itemId)
            ->firstOrFail();
    }

    public function firstMenuId(Branch $branch): string
    {
        $id = $branch->menus()->select('menus.id')
            ->oldest('sort_order')->oldest('name')->oldest('id')->value('menus.id');

        return is_int($id) ? (string) $id : '';
    }

    public function firstItemId(int $branchId, string $menuId): string
    {
        if ($menuId === '') {
            return '';
        }

        $id = MenuItem::query()
            ->select('menu_items.id')
            ->where('menu_id', (int) $menuId)
            ->whereHas('menu', fn ($query) => $query->where('branch_id', $branchId))
            ->oldest('sort_order')->oldest('name')->oldest('id')
            ->value('menu_items.id');

        return is_int($id) ? (string) $id : '';
    }

    public function firstModifierGroupId(Branch $branch): string
    {
        $id = $branch->modifierGroups()->select('modifier_groups.id')
            ->oldest('sort_order')->oldest('name')->oldest('id')->value('modifier_groups.id');

        return is_int($id) ? (string) $id : '';
    }

    /** @return EloquentCollection<int, MenuItemVariant> */
    public function variants(int $branchId, string $itemId): EloquentCollection
    {
        if ($itemId === '') {
            return new EloquentCollection;
        }

        return MenuItemVariant::query()
            ->select(['id', 'menu_item_id', 'type', 'name', 'price_cents', 'weight', 'volume', 'is_default', 'is_available', 'sort_order'])
            ->with(['translations' => fn ($query) => $query
                ->select(['id', 'menu_item_variant_id', 'language_code', 'name'])
                ->orderBy('language_code')])
            ->where('menu_item_id', (int) $itemId)
            ->whereHas('item.menu', fn ($query) => $query->where('branch_id', $branchId))
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    public function findVariantItem(int $branchId, int $itemId): MenuItem
    {
        return MenuItem::query()
            ->select(['id', 'menu_id', 'name', 'price_cents'])
            ->whereHas('menu', fn ($query) => $query->where('branch_id', $branchId))
            ->whereKey($itemId)
            ->firstOrFail();
    }

    public function findVariant(int $branchId, int $variantId): MenuItemVariant
    {
        return MenuItemVariant::query()
            ->select(['id', 'menu_item_id', 'type', 'name', 'price_cents', 'weight', 'volume', 'is_default', 'is_available', 'sort_order'])
            ->with(['translations' => fn ($query) => $query
                ->select(['id', 'menu_item_variant_id', 'language_code', 'name'])
                ->orderBy('language_code')])
            ->whereHas('item.menu', fn ($query) => $query->where('branch_id', $branchId))
            ->whereKey($variantId)
            ->firstOrFail();
    }

    public function selectedItemPrice(int $branchId, string $itemId): string
    {
        if ($itemId === '') {
            return '0.00';
        }

        $priceCents = MenuItem::query()
            ->select('price_cents')
            ->whereKey((int) $itemId)
            ->whereHas('menu', fn ($query) => $query->where('branch_id', $branchId))
            ->value('price_cents');

        return is_numeric($priceCents) ? MoneyFormatter::centsToDecimal((int) $priceCents) : '0.00';
    }

    public function selectionExists(int $branchId, string $menuId, string $itemId): bool
    {
        return $itemId !== '' && MenuItem::query()
            ->whereKey((int) $itemId)
            ->where('menu_id', (int) $menuId)
            ->whereHas('menu', fn ($query) => $query->where('branch_id', $branchId))
            ->exists();
    }

    /** @return EloquentCollection<int, KitchenDepartment> */
    public function kitchenDepartments(Branch $branch): EloquentCollection
    {
        return $branch->kitchenDepartments()
            ->select(['id', 'branch_id', 'type', 'name', 'sort_order', 'is_active', 'created_at', 'updated_at'])
            ->withCount('menuItems')
            ->get();
    }

    public function findKitchenDepartment(Branch $branch, int $departmentId): KitchenDepartment
    {
        return $branch->kitchenDepartments()
            ->select(['id', 'branch_id', 'type', 'name', 'sort_order', 'is_active', 'created_at', 'updated_at'])
            ->whereKey($departmentId)
            ->firstOrFail();
    }

    /**
     * @return EloquentCollection<int, Menu>
     */
    private function menus(Branch $branch): EloquentCollection
    {
        return $branch->menus()
            ->select(['id', 'branch_id', 'name', 'status', 'sort_order', 'created_at', 'updated_at'])
            ->with([
                'branch' => fn ($query) => $query->select(['id', 'timezone']),
                'availabilitySchedules' => fn ($query) => $query
                    ->select(['id', 'menu_id', 'day_of_week', 'starts_at', 'ends_at', 'created_at', 'updated_at']),
                'categories' => fn ($query) => $query
                    ->select(['id', 'menu_id', 'parent_id', 'name', 'description', 'image', 'icon', 'sort_order', 'is_active', 'created_at', 'updated_at'])
                    ->orderBy('sort_order')->orderBy('name')->orderBy('id'),
                'items' => fn ($query) => $query
                    ->select(['id', 'menu_id', 'category_id', 'kitchen_department_id', 'name', 'description', 'price_cents', 'allergens', 'dietary_labels', 'image', 'weight', 'volume', 'calories', 'is_available', 'sort_order', 'created_at', 'updated_at'])
                    ->with([
                        'category' => fn ($categoryQuery) => $categoryQuery->select(['id', 'menu_id', 'name', 'is_active']),
                        'kitchenDepartment' => fn ($departmentQuery) => $departmentQuery->select(['id', 'branch_id', 'type', 'name', 'is_active']),
                        'modifierGroups' => fn ($groupQuery) => $groupQuery->select([
                            'modifier_groups.id',
                            'modifier_groups.branch_id',
                            'modifier_groups.name',
                            'modifier_groups.is_required',
                            'modifier_groups.min_select',
                            'modifier_groups.max_select',
                            'modifier_groups.sort_order',
                        ]),
                    ])->orderBy('sort_order')->orderBy('name')->orderBy('id'),
            ])
            ->withCount(['categories', 'items'])
            ->orderBy('sort_order')->orderBy('name')->orderBy('id')
            ->get();
    }

    /**
     * @return EloquentCollection<int, KitchenDepartment>
     */
    private function departments(Branch $branch): EloquentCollection
    {
        return $branch->kitchenDepartments()
            ->select(['id', 'branch_id', 'type', 'name', 'sort_order', 'is_active', 'created_at', 'updated_at'])
            ->get();
    }

    /**
     * @param  EloquentCollection<int, Menu>  $menus
     * @return list<array{value: string, label: string}>
     */
    private function categoryOptions(EloquentCollection $menus, string $menuId, bool $includeInactive = true): array
    {
        $menu = $menus->first(fn (Menu $candidate): bool => $candidate->id === (int) $menuId);

        if (! $menu instanceof Menu) {
            return [];
        }

        return $menu->categories
            ->when(! $includeInactive, fn ($categories) => $categories->where('is_active', true))
            ->map(fn (MenuCategory $category): array => ['value' => (string) $category->id, 'label' => $category->name])
            ->values()->all();
    }

    /**
     * @param  EloquentCollection<int, KitchenDepartment>  $departments
     * @return list<array{value: string, label: string, is_active: bool}>
     */
    private function departmentOptions(EloquentCollection $departments, bool $activeOnly = true): array
    {
        return $departments
            ->when($activeOnly, fn ($rows) => $rows->where('is_active', true))
            ->map(fn (KitchenDepartment $department): array => [
                'value' => (string) $department->id,
                'label' => $department->name,
                'is_active' => $department->is_active,
            ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentMenu(Menu $menu, Branch $branch): array
    {
        $availability = $this->getMenuAvailabilityStatus->handle($menu);
        $dayOptions = GetMenuAvailabilityStatusAction::dayLabels();

        return [
            'id' => $menu->id,
            'name' => $menu->name,
            'status_color' => $menu->status->badgeColor(),
            'localized_status' => __($menu->status->label()),
            'sort_order' => $menu->sort_order,
            'categories_count' => $menu->categories_count,
            'items_count' => $menu->items_count,
            'availability_color' => match ($availability['tone']) {
                'success' => 'green',
                'warning' => 'amber',
                default => 'zinc',
            },
            'availability_label' => (string) $availability['label'],
            'availability_detail' => (string) $availability['detail'],
            'schedules' => $menu->availabilitySchedules->map(
                fn (MenuAvailabilitySchedule $schedule): array => [
                    'id' => $schedule->id,
                    'day_label' => $dayOptions[$schedule->day_of_week]
                        ?? __('ui.organizations.brands.branches.menu.index.day'),
                    'time_range' => substr((string) $schedule->starts_at, 0, 5).'-'.substr((string) $schedule->ends_at, 0, 5),
                ],
            )->all(),
            'categories' => $menu->categories->map(fn (MenuCategory $category): array => [
                'id' => $category->id,
                'icon' => array_key_exists((string) $category->icon, self::iconOptions()) ? $category->icon : 'bookmark',
                'name' => $category->name,
                'is_active' => $category->is_active,
                'description' => $category->description,
                'sort_order' => $category->sort_order,
            ])->all(),
            'items' => $menu->items->map(fn (MenuItem $item): array => $this->presentItem($item, $branch))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentItem(MenuItem $item, Branch $branch): array
    {
        $category = $item->getRelation('category');
        $departmentRelation = $item->getRelation('kitchenDepartment');
        $department = $departmentRelation instanceof KitchenDepartment ? $departmentRelation : null;
        $imageUrl = $item->imageUrl();

        return [
            'id' => $item->id,
            'image_url' => $imageUrl,
            'has_image' => $imageUrl !== null,
            'name' => $item->name,
            'category_name' => $category instanceof MenuCategory
                ? $category->name
                : __('ui.livewire.organizations.brands.branches.menu.index.no_category'),
            'has_department' => $department !== null,
            'department_color' => $department?->type->badgeColor() ?? 'zinc',
            'department_name' => $department?->name,
            'is_available' => $item->is_available,
            'description' => $item->description,
            'formatted_price' => MoneyFormatter::formatCents($item->price_cents, $branch->currency),
            'allergens' => $this->selectedLabelOptions($item->allergens, MenuAllergen::options()),
            'dietary_labels' => $this->selectedLabelOptions($item->dietary_labels, MenuDietaryLabel::options()),
            'sort_order' => $item->sort_order,
            'weight' => $item->weight ?? '—',
            'volume' => $item->volume ?? '—',
            'calories' => $item->calories ?? '—',
            'modifier_groups' => $item->modifierGroups->map(
                fn (ModifierGroup $group): array => ['id' => $group->id, 'name' => $group->name],
            )->all(),
        ];
    }

    /**
     * @param  list<string>  $selectedValues
     * @param  list<array{value: string, label: string}>  $options
     * @return list<array{value: string, label: string}>
     */
    private function selectedLabelOptions(array $selectedValues, array $options): array
    {
        return array_values(array_filter(
            $options,
            fn (array $option): bool => in_array($option['value'], $selectedValues, true),
        ));
    }
}
