<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Menus;

use App\Models\Branch;
use App\Models\MenuCategory;
use App\Services\Menus\CatalogData;
use App\Support\Validation\Menus\CategoryRules;
use App\Support\Validation\Menus\MenuFieldLabels;
use App\Support\Validation\Menus\MenuScopeRules;
use App\Support\Validation\Menus\MenuTranslationRules;
use Livewire\Form;

final class CategoryForm extends Form
{
    public mixed $categoryMenuId = '';

    public mixed $categoryParentId = '';

    public mixed $categoryName = '';

    public mixed $categoryDescription = '';

    public mixed $categoryIcon = 'bookmark';

    public mixed $categorySortOrder = 0;

    public mixed $categoryIsActive = true;

    public mixed $categoryTranslations = [
        'en' => ['name' => '', 'description' => ''],
        'lt' => ['name' => '', 'description' => ''],
        'ru' => ['name' => '', 'description' => ''],
    ];

    /** @return array{menuId: int, data: array{name: string, description: ?string, icon: ?string, sort_order: int, is_active: bool, translations: array<string, array{name: string, description: ?string}>, parent_id?: ?int}} */
    public function validated(Branch $branch, ?MenuCategory $category = null): array
    {
        $this->categoryName = is_string($this->categoryName) ? trim($this->categoryName) : $this->categoryName;
        $this->categoryDescription = is_string($this->categoryDescription) ? trim($this->categoryDescription) : $this->categoryDescription;
        $rules = [...CategoryRules::category(iconValues: array_keys(CatalogData::iconOptions())), ...MenuTranslationRules::menuTranslations('categoryTranslations', 160, 1000)];
        $menuId = $category->menu_id ?? $this->categoryMenuId;
        $unique = MenuScopeRules::categoryName($menuId, $category);
        if ($unique !== null) {
            $rules['categoryName'][] = $unique;
        }
        if ($category === null) {
            $rules['categoryMenuId'] = ['bail', 'required', 'numeric', 'integer', MenuScopeRules::menu($branch)];
            $rules['categoryParentId'] = ['bail', 'nullable', 'numeric', 'integer', MenuScopeRules::category($this->categoryMenuId)];
        }
        $values = $this->validate($rules);
        $data = ['name' => $values['categoryName'], 'description' => $values['categoryDescription'] ?: null, 'icon' => $values['categoryIcon'] ?: null, 'sort_order' => (int) $values['categorySortOrder'], 'is_active' => (bool) $values['categoryIsActive'], 'translations' => $values['categoryTranslations']];
        if ($category === null) {
            $data['parent_id'] = MenuScopeRules::identifier($values['categoryParentId'] ?? null);
        }

        return ['menuId' => (int) $menuId, 'data' => $data];
    }

    /** @param array<string, array{name: string, description: string}> $translations */
    public function populate(MenuCategory $category, array $translations): void
    {
        $this->categoryName = $category->name;
        $this->categoryDescription = $category->description ?? '';
        $this->categoryIcon = CatalogData::supportedCategoryIcon($category->icon);
        $this->categorySortOrder = $category->sort_order;
        $this->categoryIsActive = $category->is_active;
        $this->categoryTranslations = $translations;
    }

    public function clearPreservingMenu(): void
    {
        $menuId = $this->categoryMenuId;
        $this->reset();
        $this->categoryMenuId = $menuId;
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return MenuFieldLabels::forEditor('category');
    }
}
