<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Support\MenuImagePresentation;
use Illuminate\Support\Facades\Validator;
use Livewire\Form;

class MenuImagePresentationForm extends Form
{
    public mixed $focal_x = 50;

    public mixed $focal_y = 50;

    public mixed $translations = [];

    public function setPresentation(?array $presentation): void
    {
        $this->fill(MenuImagePresentation::normalize($presentation));
    }

    /** @return array{focal_x: int, focal_y: int, translations: array<string, array{alt: string, caption: string}>} */
    public static function validatedInput(mixed $input): array
    {
        $rules = [
            'presentation' => ['required', 'array:focal_x,focal_y,translations', 'required_array_keys:focal_x,focal_y,translations'],
            'presentation.focal_x' => ['required', 'numeric', 'integer', 'between:0,100'],
            'presentation.focal_y' => ['required', 'numeric', 'integer', 'between:0,100'],
            'presentation.translations' => ['required', 'array:en,lt,ru', 'required_array_keys:en,lt,ru'],
            'presentation.translations.*' => ['required', 'array:alt,caption', 'required_array_keys:alt,caption'],
            'presentation.translations.*.alt' => ['nullable', 'string', 'max:250'],
            'presentation.translations.*.caption' => ['nullable', 'string', 'max:1000'],
        ];
        $attributes = [
            'presentation' => __('uploads.presentation.title'),
            'presentation.focal_x' => __('uploads.presentation.horizontal'),
            'presentation.focal_y' => __('uploads.presentation.vertical'),
            'presentation.translations' => __('uploads.presentation.translations'),
            'presentation.translations.*' => __('uploads.presentation.translations'),
            'presentation.translations.*.alt' => __('uploads.presentation.alt'),
            'presentation.translations.*.caption' => __('uploads.presentation.caption'),
        ];
        $validated = Validator::make(['presentation' => $input], $rules, [], $attributes)->validate();

        return MenuImagePresentation::normalize($validated['presentation']);
    }
}
