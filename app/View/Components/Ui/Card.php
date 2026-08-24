<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

final class Card extends Component
{
    public readonly string $paddingClasses;

    public readonly string $toneClasses;

    public function __construct(
        public readonly ?string $heading = null,
        public readonly ?string $description = null,
        string $padding = 'md',
        string $tone = 'default',
    ) {
        $this->paddingClasses = match ($padding) {
            'sm' => 'p-3',
            'lg' => 'p-5',
            'none' => '',
            default => 'p-4',
        };
        $this->toneClasses = match ($tone) {
            'subtle' => 'border-border-subtle bg-surface-muted',
            'warning' => 'border-warning-border bg-warning-surface',
            'success' => 'border-success-border bg-success-surface',
            'danger' => 'border-danger-border bg-danger-surface',
            default => 'border-border-subtle bg-surface',
        };
    }

    public function render(): View
    {
        return view('components.ui.card');
    }
}
