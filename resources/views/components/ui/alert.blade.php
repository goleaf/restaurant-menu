@props([
    'tone' => 'info',
    'heading' => null,
    'icon' => null,
])

@php
    $toneClasses = match ($tone) {
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900/70 dark:bg-emerald-950/30 dark:text-emerald-100',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/70 dark:bg-amber-950/30 dark:text-amber-100',
        'danger' => 'border-red-200 bg-red-50 text-red-900 dark:border-red-900/70 dark:bg-red-950/30 dark:text-red-100',
        default => 'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-900/70 dark:bg-sky-950/30 dark:text-sky-100',
    };

    $resolvedIcon = $icon ?? match ($tone) {
        'success' => 'check-circle',
        'warning' => 'exclamation-triangle',
        'danger' => 'x-circle',
        default => 'information-circle',
    };
@endphp

<div {{ $attributes->class(['rounded-lg border p-3 text-sm leading-5', $toneClasses]) }} role="status">
    <div class="flex gap-2">
        <flux:icon :name="$resolvedIcon" variant="mini" class="mt-0.5 size-4 shrink-0" />

        <div class="min-w-0">
            @if ($heading)
                <p class="font-semibold">{{ __($heading) }}</p>
            @endif

            <div @class(['font-medium' => ! $heading])>
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
