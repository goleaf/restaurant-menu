<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use App\Support\Media\LocalImageConstraints;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\Component;
use Illuminate\View\ComponentAttributeBag;

final class ImageUploadInput extends Component
{
    public readonly string $acceptedMimeTypes;

    public readonly string $helpText;

    public function __construct(
        public readonly string $ariaLabel,
        public readonly ?string $errorName = null,
    ) {
        $this->acceptedMimeTypes = LocalImageConstraints::acceptedMimeTypes();
        $this->helpText = LocalImageConstraints::helpText();
    }

    /** @return Closure(array{attributes: ComponentAttributeBag, errors?: ViewErrorBag}): View */
    public function render(): Closure
    {
        return function (array $data): View {
            /** @var ComponentAttributeBag $attributes */
            $attributes = $data['attributes'];
            $fieldName = $this->errorName ?? $attributes->whereStartsWith('wire:model')->first() ?? $attributes->get('name');
            $inputId = $attributes->get('id') ?? 'image-upload-'.substr(hash('sha256', $fieldName ?? $this->ariaLabel), 0, 16);
            $helpId = $inputId.'-help';
            $errorId = $fieldName ? $inputId.'-error' : null;
            $descriptions = preg_split('/\s+/', trim((string) $attributes->get('aria-describedby', '')), flags: PREG_SPLIT_NO_EMPTY) ?: [];
            $descriptions[] = $helpId;

            if ($errorId !== null) {
                $descriptions[] = $errorId;
            }

            $errors = $data['errors'] ?? view()->shared('errors');
            $invalid = $fieldName && $errors instanceof ViewErrorBag
                && ($errors->has($fieldName) || $errors->has($fieldName.'.*'));
            $controlAttributes = $attributes->except(['aria-describedby'])->merge([
                'id' => $inputId,
                'aria-describedby' => implode(' ', array_unique($descriptions)),
            ]);

            if ($invalid) {
                $controlAttributes = $controlAttributes->except(['aria-invalid'])->merge(['aria-invalid' => 'true']);
            }

            return view('components.ui.image-upload-input', compact('controlAttributes', 'fieldName', 'helpId', 'errorId'));
        };
    }
}
