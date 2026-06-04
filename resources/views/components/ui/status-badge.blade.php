@props([
    'tone' => 'muted',
    'icon' => null,
    'dot' => false,
    'size' => 'sm',
])

@php
    $toneClasses = match ($tone) {
        'success', 'green' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-100',
        'emerald' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-100',
        'warning', 'amber' => 'bg-amber-100 text-amber-900 dark:bg-amber-950/60 dark:text-amber-100',
        'danger', 'red', 'rose' => 'bg-red-100 text-red-800 dark:bg-red-950/60 dark:text-red-100',
        'info', 'sky', 'blue' => 'bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-100',
        'orange' => 'bg-orange-100 text-orange-800 dark:bg-orange-950/60 dark:text-orange-100',
        'violet' => 'bg-violet-100 text-violet-800 dark:bg-violet-950/60 dark:text-violet-100',
        'lime' => 'bg-lime-100 text-lime-800 dark:bg-lime-950/60 dark:text-lime-100',
        default => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200',
    };

    $sizeClasses = match ($size) {
        'lg' => 'min-h-8 px-3 py-1 text-sm',
        default => 'min-h-6 px-2 py-0.5 text-xs',
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-md font-semibold', $toneClasses, $sizeClasses]) }}>
    @if ($icon)
        <flux:icon :name="$icon" variant="micro" class="size-3.5" />
    @elseif ($dot)
        <span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>
    @endif

    {{ $slot }}
</span>
