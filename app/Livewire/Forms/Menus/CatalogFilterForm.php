<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Menus;

use Livewire\Form;

class CatalogFilterForm extends Form
{
    public mixed $search = '';

    public mixed $availability = '';

    public mixed $menuId = '';

    public int $page = 1;

    public function searchTerm(): string
    {
        return is_string($this->search) ? mb_substr(trim($this->search), 0, 200) : '';
    }

    public function availabilityValue(): string
    {
        return in_array($this->availability, ['available', 'unavailable'], true) ? $this->availability : '';
    }

    public function menuSelection(): string
    {
        return is_string($this->menuId) || is_int($this->menuId) ? (string) $this->menuId : '';
    }
}
