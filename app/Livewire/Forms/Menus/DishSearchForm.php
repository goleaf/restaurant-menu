<?php

namespace App\Livewire\Forms\Menus;

use Livewire\Form;

final class DishSearchForm extends Form
{
    public mixed $menu = '';

    public mixed $category = '';

    public mixed $department = '';

    /** @return array{menu: string, category: string, department: string} */
    public function terms(): array
    {
        return ['menu' => $this->term($this->menu), 'category' => $this->term($this->category), 'department' => $this->term($this->department)];
    }

    private function term(mixed $value): string
    {
        return is_string($value) ? mb_substr(trim($value), 0, 160) : '';
    }
}
