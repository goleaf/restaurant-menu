<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

final class Button extends Component
{
    public readonly string $baseClasses;

    public readonly string $variantClasses;

    public readonly string $sizeClasses;

    public readonly string $widthClasses;

    public function __construct(
        string $variant = 'secondary',
        string $size = 'md',
        public readonly ?string $icon = null,
        public readonly ?string $iconTrailing = null,
        bool $fullWidth = false,
    ) {
        $this->baseClasses = 'inline-flex items-center justify-center gap-2 rounded-control font-semibold transition focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-offset-2 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-60';
        $this->variantClasses = match ($variant) {
            'primary' => 'bg-brand-700 text-white hover:bg-brand-800 focus-visible:ring-brand-600 dark:focus-visible:ring-offset-zinc-950',
            'dark' => 'bg-zinc-900 text-white hover:bg-zinc-800 focus-visible:ring-zinc-600 dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-200 dark:focus-visible:ring-offset-zinc-950',
            'danger' => 'bg-red-700 text-white hover:bg-red-800 focus-visible:ring-red-600 dark:focus-visible:ring-offset-zinc-950',
            'warning' => 'bg-amber-700 text-white hover:bg-amber-800 focus-visible:ring-amber-600 dark:focus-visible:ring-offset-zinc-950',
            'info' => 'bg-sky-700 text-white hover:bg-sky-800 focus-visible:ring-sky-600 dark:focus-visible:ring-offset-zinc-950',
            'ghost' => 'text-zinc-700 hover:bg-zinc-100 focus-visible:ring-zinc-500/30 dark:text-zinc-200 dark:hover:bg-zinc-800 dark:focus-visible:ring-offset-zinc-950',
            default => 'border border-zinc-300 bg-white text-zinc-800 hover:bg-zinc-50 focus-visible:ring-zinc-500/30 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:hover:bg-zinc-900 dark:focus-visible:ring-offset-zinc-950',
        };
        $this->sizeClasses = match ($size) {
            'sm' => 'min-h-touch px-3 text-sm',
            'lg' => 'min-h-12 px-4 text-base',
            default => 'min-h-touch px-4 text-sm',
        };
        $this->widthClasses = $fullWidth ? 'w-full' : '';
    }

    public function render(): View
    {
        return view('components.ui.button');
    }
}
