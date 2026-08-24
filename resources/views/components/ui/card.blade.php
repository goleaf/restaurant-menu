<section {{ $attributes->class(['rounded-lg border', $toneClasses, $paddingClasses]) }}>
    @if ($heading || $description || isset($actions))
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                @if ($heading)
                    <h2 class="text-lg font-semibold leading-tight text-text-primary">{{ __($heading) }}</h2>
                @endif

                @if ($description)
                    <p class="mt-1 text-pretty text-sm leading-5 text-text-muted">{{ __($description) }}</p>
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
