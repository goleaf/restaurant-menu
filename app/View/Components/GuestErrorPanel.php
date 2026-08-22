<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

final class GuestErrorPanel extends Component
{
    /** @var array{border: string, background: string, badge: string, dot: string, button: string} */
    public readonly array $palette;

    /**
     * @param  array<string, mixed>  $card
     */
    public function __construct(
        public readonly string $brandInitial = '',
        public readonly array $card = [],
        public readonly ?string $logoUrl = null,
        public readonly string $venueName = '',
    ) {
        $this->palette = match ($card['tone'] ?? 'zinc') {
            'amber' => [
                'border' => 'border-amber-200 dark:border-amber-900',
                'background' => 'bg-amber-50 dark:bg-amber-950/30',
                'badge' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-100',
                'dot' => 'bg-amber-500',
                'button' => 'bg-amber-700 hover:bg-amber-800 focus:ring-amber-600',
            ],
            'rose' => [
                'border' => 'border-rose-200 dark:border-rose-900',
                'background' => 'bg-rose-50 dark:bg-rose-950/30',
                'badge' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/60 dark:text-rose-100',
                'dot' => 'bg-rose-500',
                'button' => 'bg-rose-700 hover:bg-rose-800 focus:ring-rose-600',
            ],
            default => [
                'border' => 'border-zinc-200 dark:border-zinc-800',
                'background' => 'bg-zinc-50 dark:bg-zinc-900',
                'badge' => 'bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-100',
                'dot' => 'bg-zinc-500',
                'button' => 'bg-zinc-900 hover:bg-zinc-800 focus:ring-zinc-600 dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-200',
            ],
        };
    }

    public function render(): View
    {
        return view('components.guest-error-panel');
    }
}
