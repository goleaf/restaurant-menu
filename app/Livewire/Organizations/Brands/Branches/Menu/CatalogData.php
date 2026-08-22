<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\Menu;

use App\Actions\Menus\GetMenuAvailabilityStatusAction;
use App\Enums\MenuStatus;
use App\Models\Branch;
use App\Models\KitchenDepartment;
use App\Models\Menu;
use App\Models\MenuAvailabilitySchedule;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
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
                    ->select(['id', 'menu_id', 'category_id', 'kitchen_department_id', 'name', 'description', 'price', 'image', 'weight', 'volume', 'calories', 'is_available', 'sort_order', 'created_at', 'updated_at'])
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
            'formatted_price' => MoneyFormatter::format($item->price, $branch->currency),
            'sort_order' => $item->sort_order,
            'weight' => $item->weight ?? '—',
            'volume' => $item->volume ?? '—',
            'calories' => $item->calories ?? '—',
            'modifier_groups' => $item->modifierGroups->map(
                fn (ModifierGroup $group): array => ['id' => $group->id, 'name' => $group->name],
            )->all(),
        ];
    }
}
