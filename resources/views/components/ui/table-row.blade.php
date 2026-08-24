@if ($href)
    <a href="{{ $href }}" {{ $attributes->class([$rowClasses, 'transition-colors duration-state ease-product hover:bg-surface-muted focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-inset motion-reduce:transition-none']) }}>
        <div class="flex min-w-0 items-center gap-3">
            @isset($leading)
                <div class="shrink-0">{{ $leading }}</div>
            @endisset

            <div class="min-w-0">
                <p class="truncate font-semibold text-text-primary">{{ __($title) }}</p>

                @if ($subtitle)
                    <p class="mt-1 truncate text-text-muted">{{ __($subtitle) }}</p>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-3 md:justify-end">
            @if ($meta)
                <span class="text-text-muted">{{ __($meta) }}</span>
            @endif

            @isset($actions)
                <div class="flex flex-wrap gap-2">{{ $actions }}</div>
            @endisset
        </div>
    </a>
@else
    <div {{ $attributes->class($rowClasses) }}>
        <div class="flex min-w-0 items-center gap-3">
            @isset($leading)
                <div class="shrink-0">{{ $leading }}</div>
            @endisset

            <div class="min-w-0">
                <p class="truncate font-semibold text-text-primary">{{ __($title) }}</p>

                @if ($subtitle)
                    <p class="mt-1 truncate text-text-muted">{{ __($subtitle) }}</p>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-3 md:justify-end">
            @if ($meta)
                <span class="text-text-muted">{{ __($meta) }}</span>
            @endif

            @isset($actions)
                <div class="flex flex-wrap gap-2">{{ $actions }}</div>
            @endisset
        </div>
    </div>
@endif
