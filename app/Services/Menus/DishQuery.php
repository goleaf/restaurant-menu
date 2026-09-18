<?php

declare(strict_types=1);

namespace App\Services\Menus;

use App\Enums\MenuAllergen;
use App\Enums\MenuDietaryLabel;
use App\Enums\SupportedLocale;
use App\Models\Branch;
use App\Models\KitchenDepartment;
use App\Models\Menu;
use App\Models\MenuCategory;

final class DishQuery
{
    public function __construct(private readonly CatalogData $catalog) {}

    /**
     * @param  array{menu: string, category: string, department: string}  $search
     * @return array<string, mixed>
     */
    public function editor(Branch $branch, ?int $itemId, string $menuId, string $categoryId, string $departmentId, array $search = ['menu' => '', 'category' => '', 'department' => '']): array
    {
        $menus = Menu::query()->select(['id', 'name'])->where('branch_id', $branch->id)
            ->when($search['menu'] !== '', fn ($query) => $query->where('name', 'like', '%'.$search['menu'].'%'))
            ->orderBy('name')->orderBy('id')->limit(20)->get();
        if ($menuId !== '' && ! $menus->contains('id', (int) $menuId)) {
            $selected = Menu::query()->select(['id', 'name'])->where('branch_id', $branch->id)->whereKey($menuId)->first();
            if ($selected !== null) {
                $menus->push($selected);
            }
        }
        $categories = MenuCategory::query()->select(['id', 'name'])->where('menu_id', $menuId)
            ->whereHas('menu', fn ($query) => $query->where('branch_id', $branch->id))
            ->when($search['category'] !== '', fn ($query) => $query->where('name', 'like', '%'.$search['category'].'%'))
            ->orderBy('name')->orderBy('id')->limit(20)->get();
        if ($categoryId !== '' && ! $categories->contains('id', (int) $categoryId)) {
            $selected = MenuCategory::query()->select(['id', 'name'])->where('menu_id', $menuId)
                ->whereHas('menu', fn ($query) => $query->where('branch_id', $branch->id))->whereKey($categoryId)->first();
            if ($selected !== null) {
                $categories->push($selected);
            }
        }
        $departments = KitchenDepartment::query()->select(['id', 'name', 'is_active'])->where('branch_id', $branch->id)
            ->where(fn ($query) => $query->where('is_active', true)->orWhereKey($departmentId))
            ->when($search['department'] !== '', fn ($query) => $query->where('name', 'like', '%'.$search['department'].'%'))
            ->orderBy('name')->orderBy('id')->limit(20)->get();
        if ($departmentId !== '' && ! $departments->contains('id', (int) $departmentId)) {
            $selected = KitchenDepartment::query()->select(['id', 'name', 'is_active'])->where('branch_id', $branch->id)->whereKey($departmentId)->first();
            if ($selected !== null) {
                $departments->push($selected);
            }
        }
        $item = $this->catalog->editingItem($branch, $itemId, evaluateAvailability: false);

        return [
            'item' => $item,
            'menuOptions' => $menus->map(fn (Menu $menu): array => ['value' => (string) $menu->id, 'label' => $menu->name])->all(),
            'editingItemCategoryOptions' => $categories->map(fn (MenuCategory $category): array => ['value' => (string) $category->id, 'label' => $category->name])->all(),
            'activeKitchenDepartmentOptions' => $departments->map(fn (KitchenDepartment $department): array => ['value' => (string) $department->id, 'label' => $department->name, 'is_active' => $department->is_active])->all(),
            'languageOptions' => SupportedLocale::labels(), 'allergenOptions' => MenuAllergen::options(), 'dietaryLabelOptions' => MenuDietaryLabel::options(),
        ];
    }
}
