<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Menus;

use App\Models\Branch;
use App\Models\Menu;
use App\Support\Validation\Menus\MenuRules;
use App\Support\Validation\Menus\MenuTranslationRules;
use App\Support\Validation\Menus\MenuFieldLabels;
use App\Support\Validation\Menus\MenuScopeRules;
use Livewire\Form;

final class MenuForm extends Form
{
    public mixed $menuName = '';

    public mixed $menuStatus = 'draft';

    public mixed $menuSortOrder = 0;

    public mixed $menuTranslations = ['en' => '', 'lt' => '', 'ru' => ''];

    /** @return array{name: string, status: string, sort_order: int, translations: array<string, string>} */
    public function validated(Branch $branch, ?Menu $menu = null): array
    {
        $this->menuName = is_string($this->menuName) ? trim($this->menuName) : $this->menuName;
        $rules = MenuRules::menu();
        $rules['menuName'][] = MenuScopeRules::menuName($branch, $menu);
        $values = $this->validate([...$rules, ...MenuTranslationRules::translatedNames('menuTranslations')]);

        return ['name' => $values['menuName'], 'status' => $values['menuStatus'], 'sort_order' => (int) $values['menuSortOrder'], 'translations' => $values['menuTranslations']];
    }

    /** @param array<string, string> $translations */
    public function populate(Menu $menu, array $translations): void
    {
        $this->menuName = $menu->name;
        $this->menuStatus = $menu->status->value;
        $this->menuSortOrder = $menu->sort_order;
        $this->menuTranslations = $translations;
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return MenuFieldLabels::forEditor('menu');
    }
}
