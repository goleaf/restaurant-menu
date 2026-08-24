<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use BackedEnum;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\View\Component;

final class StatusBadge extends Component
{
    public readonly ?string $statusKey;

    public readonly string $contextKey;

    public readonly ?string $resolvedLabel;

    public readonly ?string $resolvedIcon;

    public readonly string $toneClasses;

    public readonly string $sizeClasses;

    public function __construct(
        string $tone = 'muted',
        ?string $icon = null,
        public readonly bool $dot = false,
        string $size = 'sm',
        BackedEnum|string|null $status = null,
        string $context = 'default',
        ?string $label = null,
    ) {
        $statusValue = $status instanceof BackedEnum ? $status->value : $status;
        $this->statusKey = $statusValue !== null
            ? (string) Str::of((string) $statusValue)->replace(['-', ' ', '.'], '_')->lower()
            : null;
        $this->contextKey = (string) Str::of($context)->replace(['-', ' ', '.'], '_')->lower();

        $statusTone = match ($this->statusKey) {
            'active', 'approved', 'available', 'completed', 'confirmed', 'confirmed_by_waiter', 'done', 'free', 'open', 'paid', 'ready', 'served', 'success' => 'success',
            'busy', 'called', 'cooking', 'draft', 'in_progress', 'new', 'pending', 'requested', 'sent', 'waiting', 'waiting_waiter_confirmation' => 'warning',
            'cancelled', 'closed', 'denied', 'disabled', 'expired', 'failed', 'inactive', 'out_of_stock', 'rejected', 'removed', 'unavailable' => 'danger',
            default => $tone,
        };
        $resolvedTone = $this->statusKey !== null ? $statusTone : $tone;
        $labelKey = $label ?? ($this->statusKey !== null ? 'ui.status.'.$this->contextKey.'.'.$this->statusKey : null);
        $resolvedLabel = $labelKey !== null ? __($labelKey) : null;

        $this->resolvedLabel = $labelKey !== null && $resolvedLabel === $labelKey && $this->statusKey !== null
            ? __(Str::headline(str_replace('_', ' ', $this->statusKey)))
            : $resolvedLabel;
        $this->resolvedIcon = $icon ?? ($this->statusKey !== null && ! $dot ? match ($resolvedTone) {
            'success', 'green', 'emerald', 'lime' => 'check-circle',
            'warning', 'amber', 'orange' => 'clock',
            'danger', 'red', 'rose' => 'x-circle',
            'info', 'sky', 'blue' => 'information-circle',
            default => null,
        } : null);
        $this->toneClasses = match ($resolvedTone) {
            'success', 'green', 'emerald', 'lime' => 'border-success-border bg-success-surface text-success',
            'warning', 'amber', 'orange' => 'border-warning-border bg-warning-surface text-warning',
            'danger', 'red', 'rose' => 'border-danger-border bg-danger-surface text-danger',
            'info', 'sky', 'blue', 'violet' => 'border-information-border bg-information-surface text-information',
            default => 'border-border-subtle bg-surface-muted text-text-primary',
        };
        $this->sizeClasses = $size === 'lg' ? 'min-h-8 px-3 py-1 text-sm' : 'min-h-6 px-2 py-0.5 text-xs';
    }

    public function render(): View
    {
        return view('components.ui.status-badge');
    }
}
