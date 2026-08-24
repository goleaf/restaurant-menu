<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

final class Alert extends Component
{
    public readonly string $toneClasses;

    public readonly string $resolvedIcon;

    public function __construct(
        public readonly string $tone = 'info',
        public readonly ?string $heading = null,
        ?string $icon = null,
    ) {
        $this->toneClasses = match ($tone) {
            'success' => 'border-success-border bg-success-surface text-success',
            'warning' => 'border-warning-border bg-warning-surface text-warning',
            'danger' => 'border-danger-border bg-danger-surface text-danger',
            default => 'border-information-border bg-information-surface text-information',
        };
        $this->resolvedIcon = $icon ?? match ($tone) {
            'success' => 'check-circle',
            'warning' => 'exclamation-triangle',
            'danger' => 'x-circle',
            default => 'information-circle',
        };
    }

    public function render(): View
    {
        return view('components.ui.alert');
    }
}
