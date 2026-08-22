<span
    {{ $attributes->class(['inline-flex size-9 shrink-0 items-center justify-center rounded-lg', $toneClasses, 'opacity-55' => ! $active]) }}
    @if ($label) title="{{ $label }}" @endif
>
    <flux:icon :name="$resolvedIcon" variant="mini" class="size-5" />
    @if ($label)
        <span class="sr-only">{{ $label }}</span>
    @endif
</span>
