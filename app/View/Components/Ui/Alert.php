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
            'success' => 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900/70 dark:bg-emerald-950/30 dark:text-emerald-100',
            'warning' => 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/70 dark:bg-amber-950/30 dark:text-amber-100',
            'danger' => 'border-red-200 bg-red-50 text-red-900 dark:border-red-900/70 dark:bg-red-950/30 dark:text-red-100',
            default => 'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-900/70 dark:bg-sky-950/30 dark:text-sky-100',
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
