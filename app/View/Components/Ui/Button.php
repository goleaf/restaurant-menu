<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

final class Button extends Component
{
    public readonly string $fluxVariant;

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
        $this->fluxVariant = match ($variant) {
            'primary', 'dark', 'warning', 'info' => 'primary',
            'danger', 'ghost' => $variant,
            default => 'outline',
        };
        $this->variantClasses = match ($variant) {
            'primary', 'danger' => '',
            'dark' => '[--color-accent:var(--color-text-primary)] [--color-accent-foreground:var(--color-text-inverse)]',
            'warning' => '[--color-accent:var(--color-warning)] [--color-accent-foreground:var(--color-text-inverse)]',
            'info' => '[--color-accent:var(--color-information)] [--color-accent-foreground:var(--color-text-inverse)]',
            'ghost' => 'text-text-muted! hover:bg-control-hover! hover:text-text-primary!',
            default => 'border-border-strong! bg-surface! text-text-primary! hover:bg-control-hover!',
        };
        $this->sizeClasses = match ($size) {
            'sm' => 'min-h-touch px-3! py-2 text-sm',
            'lg' => 'min-h-12 px-4 py-2.5 text-base!',
            default => 'min-h-touch px-4 py-2 text-sm',
        };
        $this->widthClasses = $fullWidth ? 'w-full' : '';
    }

    public function render(): View
    {
        return view('components.ui.button');
    }
}
