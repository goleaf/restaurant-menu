<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Menus;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Form;

final class DishPreviewForm extends Form
{
    public mixed $source = 'saved';

    public mixed $variantId = '';

    public mixed $modifiers = [];

    /** Validate read-only controls without clearing another editor's errors.
     * @return array<string,mixed> */
    public function validated(): array
    {
        $rules = [];
        foreach ($this->rules() as $field => $rule) {
            $rules['previewForm.'.$field] = $rule;
        }

        return Validator::make(['previewForm' => $this->all()], $rules)->validate()['previewForm'];
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'source' => ['required', 'string', Rule::in(['saved', 'draft'])],
            'variantId' => ['nullable', 'numeric', 'integer', 'min:1'],
            'modifiers' => ['array', 'max:100'],
            'modifiers.*' => ['array', 'max:100'],
            'modifiers.*.*' => ['required', 'numeric', 'integer', 'min:1', 'distinct'],
        ];
    }
}
