@props([
    'title',
    'description' => null,
    'tone' => 'neutral',
    'selected' => false,
])

<article
    data-priority-row
    data-selected="{{ $selected ? 'true' : 'false' }}"
    @if ($selected) aria-current="true" @endif
    {{ $attributes->class([
        'flex min-h-operational-touch min-w-0 flex-col gap-3 rounded-control border p-3 transition-[background-color,border-color,color] duration-state ease-product sm:flex-row sm:items-center sm:justify-between motion-reduce:transition-none',
        'border-strong bg-surface-selected text-text-primary' => $selected,
        'border-danger-border bg-danger-surface text-danger' => ! $selected && $tone === 'danger',
        'border-warning-border bg-warning-surface text-warning' => ! $selected && $tone === 'warning',
        'border-information-border bg-information-surface text-information' => ! $selected && $tone === 'information',
        'border-success-border bg-success-surface text-success' => ! $selected && $tone === 'success',
        'border-border-subtle bg-surface text-text-primary' => ! $selected && $tone === 'neutral',
    ]) }}
>
    <div class="flex min-w-0 items-start gap-3">
        @isset($leading)
            <div class="shrink-0">{{ $leading }}</div>
        @endisset

        <div class="min-w-0">
            <p class="text-pretty text-sm font-semibold leading-5 text-current">{{ __($title) }}</p>

            @if ($description)
                <p class="mt-0.5 text-pretty text-sm leading-5 text-text-muted">{{ __($description) }}</p>
            @endif

            @isset($meta)
                <div class="mt-1 flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 text-xs font-medium leading-5 text-text-muted">
                    {{ $meta }}
                </div>
            @endisset
        </div>
    </div>

    @isset($actions)
        <div class="flex shrink-0 flex-wrap gap-2 sm:justify-end">
            {{ $actions }}
        </div>
    @endisset
</article>
