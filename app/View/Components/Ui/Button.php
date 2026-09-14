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
        $this->baseClasses = 'inline-flex min-w-0 items-center justify-center gap-2 rounded-control border border-transparent font-semibold leading-tight text-pretty transition-[background-color,border-color,color,box-shadow] duration-state ease-product focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2 focus-visible:ring-offset-canvas active:duration-75 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-55 motion-reduce:transition-none';
        $this->variantClasses = match ($variant) {
            'primary' => 'bg-accent text-accent-foreground hover:bg-brand-800 active:bg-brand-900 dark:hover:bg-brand-200 dark:active:bg-brand-100',
            'dark' => 'bg-text-primary text-text-inverse hover:opacity-90 active:opacity-80',
            'danger' => 'bg-danger text-white hover:brightness-90 active:brightness-75',
            'warning' => 'bg-warning text-white hover:brightness-90 active:brightness-75 dark:text-text-inverse',
            'info' => 'bg-information text-white hover:brightness-90 active:brightness-75',
            'ghost' => 'text-text-muted hover:bg-control-hover hover:text-text-primary active:bg-control-active',
            default => 'border-border-strong bg-surface text-text-primary hover:bg-control-hover active:bg-control-active',
        };
        $this->sizeClasses = match ($size) {
            'sm' => 'min-h-touch px-3 py-2 text-sm',
            'lg' => 'min-h-12 px-4 py-2.5 text-base',
            default => 'min-h-touch px-4 text-sm',
        };
        $this->widthClasses = $fullWidth ? 'w-full' : '';
    }

    public function render(): View
    {
        return view('components.ui.button');
    }
}
