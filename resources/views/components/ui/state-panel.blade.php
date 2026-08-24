<section
    data-state="{{ $resolvedKind }}"
    role="{{ $role }}"
    @if ($role === 'status') aria-live="polite" @endif
    @if ($busy) aria-busy="true" @endif
    {{ $attributes->class(['rounded-card border p-4', $toneClasses]) }}
>
    <div class="flex items-start gap-3">
        <span class="flex size-10 shrink-0 items-center justify-center rounded-control bg-surface text-current">
            <flux:icon :name="$icon" variant="mini" @class(['size-5', 'motion-safe:animate-spin' => $busy]) />
        </span>

        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-current">{{ __($title) }}</p>

            @if ($description)
                <p class="mt-1 max-w-2xl text-pretty text-sm leading-5 text-text-muted">{{ __($description) }}</p>
            @endif

            @if ($busy)
                <div class="mt-3 grid max-w-md gap-2" aria-hidden="true">
                    <span class="h-2.5 w-full rounded-control bg-border-subtle motion-safe:animate-pulse"></span>
                    <span class="h-2.5 w-2/3 rounded-control bg-border-subtle motion-safe:animate-pulse"></span>
                </div>
            @endif

            @isset($actions)
                <div class="mt-4 flex flex-wrap gap-2">
                    {{ $actions }}
                </div>
            @endisset
        </div>
    </div>
</section>
