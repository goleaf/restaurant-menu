<span
    @if ($statusKey) data-status="{{ $statusKey }}" data-status-context="{{ $contextKey }}" @endif
    {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-md border font-semibold', $toneClasses, $sizeClasses]) }}
>
    @if ($resolvedIcon)
        <flux:icon :name="$resolvedIcon" variant="micro" class="size-3.5" />
    @elseif ($dot)
        <span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>
    @endif

    {{ $slot->isEmpty() ? $resolvedLabel : $slot }}
</span>
