<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use BackedEnum;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

final class ServicePointIcon extends Component
{
    /** @var list<string> */
    private const SUPPORTED_ICONS = [
        'squares-2x2',
        'beaker',
        'sparkles',
        'home',
        'bookmark',
        'sun',
        'building-office-2',
        'shopping-bag',
        'truck',
    ];

    public readonly string $resolvedIcon;

    public readonly string $toneClasses;

    public function __construct(
        BackedEnum|string|null $type = null,
        ?string $icon = null,
        public readonly ?string $label = null,
        public readonly bool $active = true,
    ) {
        $typeValue = $type instanceof BackedEnum ? (string) $type->value : (string) $type;
        $fallbackIcon = match ($typeValue) {
            'table' => 'squares-2x2',
            'bar_seat' => 'beaker',
            'vip_table' => 'sparkles',
            'room' => 'home',
            'booth' => 'bookmark',
            'sunbed' => 'sun',
            'hotel_room' => 'building-office-2',
            'pickup_window' => 'shopping-bag',
            'delivery_point' => 'truck',
            default => 'bookmark',
        };
        $this->resolvedIcon = in_array($icon, self::SUPPORTED_ICONS, true) ? $icon : $fallbackIcon;
        $this->toneClasses = match ($typeValue) {
            'bar_seat' => 'bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-100',
            'vip_table' => 'bg-violet-100 text-violet-800 dark:bg-violet-950/60 dark:text-violet-100',
            'sunbed' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-100',
            'pickup_window', 'delivery_point' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-100',
            default => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200',
        };
    }

    public function render(): View
    {
        return view('components.ui.service-point-icon');
    }
}
