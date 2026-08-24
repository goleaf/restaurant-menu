<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\Menu\Concerns;

use App\Livewire\Organizations\Brands\Branches\Menu\Catalog;
use App\Models\KitchenDepartment;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Validation\Rule;

/** @mixin Catalog */
trait BuildsCatalogScopeRules
{
    private function menuRule(): mixed
    {
        return Rule::exists((new Menu)->getTable(), 'id')
            ->where(fn ($query) => $query->where('branch_id', $this->branch->id));
    }

    private function categoryRule(string $menuId): mixed
    {
        return Rule::exists((new MenuCategory)->getTable(), 'id')
            ->where(fn ($query) => $query->where('menu_id', (int) $menuId));
    }

    private function kitchenDepartmentRule(): mixed
    {
        return Rule::exists((new KitchenDepartment)->getTable(), 'id')
            ->where(fn ($query) => $query->where('branch_id', $this->branch->id));
    }

    private function categoryNameUniqueRule(int $menuId, ?int $ignoreCategoryId = null): mixed
    {
        $rule = Rule::unique((new MenuCategory)->getTable(), 'name')
            ->where(fn ($query) => $query->where('menu_id', $menuId))
            ->withoutTrashed();

        return $ignoreCategoryId === null ? $rule : $rule->ignore($ignoreCategoryId);
    }

    private function itemNameUniqueRule(int $categoryId, ?int $ignoreItemId = null): mixed
    {
        $rule = Rule::unique((new MenuItem)->getTable(), 'name')
            ->where(fn ($query) => $query->where('category_id', $categoryId))
            ->withoutTrashed();

        return $ignoreItemId === null ? $rule : $rule->ignore($ignoreItemId);
    }
}
