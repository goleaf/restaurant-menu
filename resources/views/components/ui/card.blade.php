@props([
    'heading' => null,
    'description' => null,
    'padding' => 'md',
    'tone' => 'default',
])

@php
    $paddingClasses = match ($padding) {
        'sm' => 'p-3',
        'lg' => 'p-5',
        'none' => '',
        default => 'p-4',
    };

    $toneClasses = match ($tone) {
        'subtle' => 'border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950/60',
        'warning' => 'border-amber-200 bg-amber-50 dark:border-amber-900/70 dark:bg-amber-950/30',
        'success' => 'border-emerald-200 bg-emerald-50 dark:border-emerald-900/70 dark:bg-emerald-950/30',
        default => 'border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900',
    };
@endphp

<section {{ $attributes->class(['rounded-lg border', $toneClasses, $paddingClasses]) }}>
    @if ($heading || $description || isset($actions))
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                @if ($heading)
                    <h2 class="text-lg font-semibold leading-tight text-zinc-950 dark:text-white">{{ $heading }}</h2>
                @endif

                @if ($description)
                    <p class="mt-1 text-sm leading-5 text-zinc-600 dark:text-zinc-300">{{ $description }}</p>
                @endif
            </div>

            @isset($actions)
                <div class="flex shrink-0 flex-wrap gap-2">
                    {{ $actions }}
                </div>
            @endisset
        </div>
    @endif

    {{ $slot }}
</section>
