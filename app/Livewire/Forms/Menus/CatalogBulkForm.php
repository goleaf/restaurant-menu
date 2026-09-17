<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Menus;

use Illuminate\Validation\Rule;
use Livewire\Form;

class CatalogBulkForm extends Form
{
    public mixed $operation = '';

    public mixed $categoryId = '';

    public mixed $confirmArchive = false;

    /** @return array<string, list<mixed>> */
    protected function rules(): array
    {
        return [
            'operation' => ['required', 'string', Rule::in(['move', 'archive'])],
            'categoryId' => ['bail', Rule::requiredIf($this->operation === 'move'), 'nullable', 'numeric', 'integer', 'min:1'],
            'confirmArchive' => $this->operation === 'archive' ? ['accepted'] : ['boolean'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return ['operation' => __('menu.bulk.action'), 'categoryId' => __('menu.bulk.category'), 'confirmArchive' => __('menu.bulk.archive_confirm')];
    }
}
