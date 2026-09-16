<?php

declare(strict_types=1);

namespace App\Support\Validation\Menus;

use App\Models\Branch;
use App\Models\KitchenDepartment;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\Unique;

final class MenuScopeRules
{
    public static function menu(Branch $branch): Exists
    {
        return Rule::exists(Menu::class, 'id')->where('branch_id', $branch->id);
    }

    public static function category(mixed $menuId): Exists|In
    {
        $menuId = self::identifier($menuId);

        return $menuId === null ? Rule::in([]) : Rule::exists(MenuCategory::class, 'id')->where('menu_id', $menuId);
    }

    public static function department(Branch $branch): Exists
    {
        return Rule::exists(KitchenDepartment::class, 'id')->where('branch_id', $branch->id);
    }

    public static function menuName(Branch $branch, ?Menu $menu): Unique
    {
        $rule = Rule::unique(Menu::class, 'name')->where('branch_id', $branch->id)->withoutTrashed();

        return $menu === null ? $rule : $rule->ignore($menu);
    }

    public static function categoryName(mixed $menuId, ?MenuCategory $category): ?Unique
    {
        $menuId = self::identifier($menuId);

        if ($menuId === null) {
            return null;
        }

        $rule = Rule::unique(MenuCategory::class, 'name')->where('menu_id', $menuId)->withoutTrashed();

        return $category === null ? $rule : $rule->ignore($category);
    }

    public static function itemName(mixed $categoryId, ?MenuItem $item): ?Unique
    {
        $categoryId = self::identifier($categoryId);

        if ($categoryId === null) {
            return null;
        }

        $rule = Rule::unique(MenuItem::class, 'name')->where('category_id', $categoryId)->withoutTrashed();

        return $item === null ? $rule : $rule->ignore($item);
    }

    public static function identifier(mixed $value): ?int
    {
        if (! is_int($value) && ! is_string($value)) {
            return null;
        }

        $identifier = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $identifier === false ? null : $identifier;
    }
}
