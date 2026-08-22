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
            'subtle' => 'border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950/60',
            'warning' => 'border-amber-200 bg-amber-50 dark:border-amber-900/70 dark:bg-amber-950/30',
            'success' => 'border-emerald-200 bg-emerald-50 dark:border-emerald-900/70 dark:bg-emerald-950/30',
            default => 'border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900',
        };
    }

    public function render(): View
    {
        return view('components.ui.card');
    }
}
