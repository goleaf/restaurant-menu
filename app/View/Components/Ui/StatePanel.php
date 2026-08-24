<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

final class StatePanel extends Component
{
    public readonly string $resolvedKind;

    public readonly string $toneClasses;

    public readonly string $icon;

    public readonly string $role;

    public readonly bool $busy;

    public function __construct(
        public readonly string $title,
        public readonly ?string $description = null,
        string $kind = 'empty',
        ?string $icon = null,
    ) {
        $this->resolvedKind = in_array($kind, [
            'empty',
            'filtered-empty',
            'loading',
            'slow',
            'offline',
            'stale',
            'validation',
            'unauthorized',
            'error',
            'fatal',
        ], true) ? $kind : 'empty';
        $this->toneClasses = match ($this->resolvedKind) {
            'offline', 'slow', 'stale' => 'border-warning-border bg-warning-surface text-warning',
            'validation', 'error', 'fatal' => 'border-danger-border bg-danger-surface text-danger',
            'unauthorized' => 'border-information-border bg-information-surface text-information',
            default => 'border-border-subtle bg-surface-muted text-text-primary',
        };
        $this->icon = $icon ?? match ($this->resolvedKind) {
            'filtered-empty' => 'funnel',
            'loading' => 'arrow-path',
            'slow' => 'clock',
            'offline' => 'signal-slash',
            'stale' => 'arrow-path-rounded-square',
            'validation' => 'exclamation-circle',
            'unauthorized' => 'lock-closed',
            'error', 'fatal' => 'x-circle',
            default => 'inbox',
        };
        $this->role = in_array($this->resolvedKind, ['validation', 'error', 'fatal'], true) ? 'alert' : 'status';
        $this->busy = $this->resolvedKind === 'loading';
    }

    public function render(): View
    {
        return view('components.ui.state-panel');
    }
}
