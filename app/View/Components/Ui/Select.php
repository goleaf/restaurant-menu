<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\View\Component;

final class Select extends Component
{
    public readonly string $fieldId;

    public readonly string $errorId;

    /** @var list<array{value: int|string, label: int|string}> */
    public readonly array $resolvedOptions;

    /**
     * @param  array<int|string, array{value?: int|string, label?: int|string}|int|string>  $options
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $label = null,
        public readonly ?string $description = null,
        public readonly ?string $placeholder = null,
        array $options = [],
        public readonly mixed $selected = null,
        ?string $id = null,
        public readonly ?string $error = null,
    ) {
        $this->fieldId = $id ?? 'field-'.Str::of($name)->replace(['[', ']', '.'], '-')->trim('-');
        $this->errorId = $this->fieldId.'-error';
        $this->resolvedOptions = collect($options)
            ->map(fn (array|int|string $label, int|string $value): array => [
                'value' => is_array($label) ? ($label['value'] ?? $value) : $value,
                'label' => is_array($label) ? ($label['label'] ?? $label['value'] ?? $value) : $label,
            ])
            ->values()
            ->all();
    }

    public function render(): View
    {
        return view('components.ui.select');
    }
}
