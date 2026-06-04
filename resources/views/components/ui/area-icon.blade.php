@props([
    'type' => null,
    'icon' => null,
    'label' => null,
    'active' => true,
])

@php
    $typeValue = $type instanceof \BackedEnum ? $type->value : (string) $type;
    $resolvedIcon = $icon ?: match ($typeValue) {
        'group' => 'folder',
        'floor' => 'building-office',
        'hall' => 'squares-2x2',
        'terrace' => 'sun',
        'vip_room' => 'sparkles',
        'bar_area' => 'beaker',
        'banquet_hall' => 'building-storefront',
        'room' => 'home',
        'hotel_area' => 'building-office',
        'pickup_area' => 'shopping-bag',
        'delivery_area' => 'truck',
        default => 'bookmark',
    };

    $toneClasses = match ($typeValue) {
        'terrace' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-100',
        'vip_room', 'banquet_hall' => 'bg-violet-100 text-violet-800 dark:bg-violet-950/60 dark:text-violet-100',
        'bar_area' => 'bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-100',
        'pickup_area', 'delivery_area' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-100',
        default => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200',
    };
@endphp

<span
    {{ $attributes->class(['inline-flex size-9 shrink-0 items-center justify-center rounded-lg', $toneClasses, 'opacity-55' => ! $active]) }}
    @if ($label) title="{{ $label }}" @endif
>
    <flux:icon :name="$resolvedIcon" variant="mini" class="size-5" />
    @if ($label)
        <span class="sr-only">{{ $label }}</span>
    @endif
</span>
