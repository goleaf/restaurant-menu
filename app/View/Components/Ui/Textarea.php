<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\View\Component;

final class Textarea extends Component
{
    public readonly string $fieldId;

    public readonly string $errorId;

    public function __construct(
        public readonly string $name,
        public readonly ?string $label = null,
        public readonly ?string $description = null,
        public readonly ?string $placeholder = null,
        public readonly mixed $value = null,
        public readonly int $rows = 4,
        ?string $id = null,
        public readonly ?string $error = null,
    ) {
        $this->fieldId = $id ?? 'field-'.Str::of($name)->replace(['[', ']', '.'], '-')->trim('-');
        $this->errorId = $this->fieldId.'-error';
    }

    public function render(): View
    {
        return view('components.ui.textarea');
    }
}
