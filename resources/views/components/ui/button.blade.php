@props([
    'variant' => 'secondary',
    'size' => 'md',
    'icon' => null,
    'iconTrailing' => null,
    'fullWidth' => false,
])

@php
    $baseClasses = 'inline-flex items-center justify-center gap-2 rounded-lg font-semibold transition focus:outline-hidden focus:ring-2 focus:ring-offset-2 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-60';

    $variantClasses = match ($variant) {
        'primary' => 'bg-emerald-700 text-white hover:bg-emerald-800 focus:ring-emerald-600 dark:focus:ring-offset-zinc-950',
        'dark' => 'bg-zinc-900 text-white hover:bg-zinc-800 focus:ring-zinc-600 dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-200 dark:focus:ring-offset-zinc-950',
        'danger' => 'bg-red-700 text-white hover:bg-red-800 focus:ring-red-600 dark:focus:ring-offset-zinc-950',
        'warning' => 'bg-amber-700 text-white hover:bg-amber-800 focus:ring-amber-600 dark:focus:ring-offset-zinc-950',
        'info' => 'bg-sky-700 text-white hover:bg-sky-800 focus:ring-sky-600 dark:focus:ring-offset-zinc-950',
        'ghost' => 'text-zinc-700 hover:bg-zinc-100 focus:ring-zinc-500/30 dark:text-zinc-200 dark:hover:bg-zinc-800 dark:focus:ring-offset-zinc-950',
        default => 'border border-zinc-300 bg-white text-zinc-800 hover:bg-zinc-50 focus:ring-zinc-500/30 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:hover:bg-zinc-900 dark:focus:ring-offset-zinc-950',
    };

    $sizeClasses = match ($size) {
        'sm' => 'min-h-9 px-3 text-sm',
        'lg' => 'min-h-12 px-4 text-base',
        default => 'min-h-11 px-4 text-sm',
    };

    $widthClasses = $fullWidth ? 'w-full' : '';
@endphp

@if ($attributes->has('href'))
    <a {{ $attributes->class([$baseClasses, $variantClasses, $sizeClasses, $widthClasses]) }}>
        @if ($icon)
            <flux:icon :name="$icon" variant="micro" class="size-4" />
        @endif

        {{ $slot }}

        @if ($iconTrailing)
            <flux:icon :name="$iconTrailing" variant="micro" class="size-4" />
        @endif
    </a>
@else
    <button {{ $attributes->merge(['type' => 'button'])->class([$baseClasses, $variantClasses, $sizeClasses, $widthClasses]) }}>
        @if ($icon)
            <flux:icon :name="$icon" variant="micro" class="size-4" />
        @endif

        {{ $slot }}

        @if ($iconTrailing)
            <flux:icon :name="$iconTrailing" variant="micro" class="size-4" />
        @endif
    </button>
@endif
