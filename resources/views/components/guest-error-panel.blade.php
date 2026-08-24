@if ($card['visible'] ?? false)
    <section
        data-component="guest-error-page"
        data-error-state="{{ $card['state'] ?? 'unknown' }}"
        {{ $attributes->class([
            'overflow-hidden rounded-card border bg-surface',
            $palette['border'],
        ]) }}
    >
        <div class="{{ $palette['background'] }} px-5 py-5">
            <div class="flex items-start gap-3">
                @if ($logoUrl)
                    <img
                        src="{{ $logoUrl }}"
                        alt="{{ $venueName }}"
                        width="56"
                        height="56"
                        class="size-14 rounded-control border border-border-subtle bg-surface object-contain p-2"
                    >
                @else
                    <div class="flex size-14 shrink-0 items-center justify-center rounded-control border border-border-subtle bg-surface text-xl font-semibold text-text-primary">
                        {{ $brandInitial !== '' ? $brandInitial : '?' }}
                    </div>
                @endif

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="{{ $palette['badge'] }} inline-flex min-h-7 items-center rounded-md px-2.5 py-1 text-xs font-semibold">
                            {{ $card['kicker'] ?? __('guest.table.guest_access') }}
                        </span>

                        <span class="{{ $palette['dot'] }} size-2 rounded-full"></span>
                    </div>

                    @if ($venueName)
                        <p class="mt-3 text-sm font-medium text-text-muted">{{ $venueName }}</p>
                    @endif

                    <h1 class="mt-2 text-balance text-2xl font-semibold leading-tight text-text-primary">
                        {{ $card['title'] ?? __('guest.table.guest_access_unavailable_title') }}
                    </h1>

                    <p class="mt-3 text-base leading-6 text-text-muted">
                        {{ $card['message'] ?? '' }}
                    </p>
                </div>
            </div>
        </div>

        <div class="space-y-4 px-5 py-5">
            @if ($card['support_text'] ?? '')
                <p class="text-sm leading-6 text-text-muted">
                    {{ $card['support_text'] }}
                </p>
            @endif

            @if ($card['primary_label'] && $card['primary_url'])
                <a
                    href="{{ $card['primary_url'] }}"
                    class="{{ $palette['button'] }} flex h-12 w-full items-center justify-center rounded-lg px-4 text-base font-semibold text-white transition focus:outline-hidden focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-zinc-950"
                >
                    {{ $card['primary_label'] }}
                </a>
            @endif

            @if ($card['secondary_label'] && $card['secondary_url'])
                <a
                    href="{{ $card['secondary_url'] }}"
                    class="flex min-h-touch w-full items-center justify-center rounded-control border border-border-strong bg-surface px-4 text-sm font-semibold text-text-primary transition-colors duration-state ease-product hover:bg-surface-muted focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2 motion-reduce:transition-none"
                >
                    {{ $card['secondary_label'] }}
                </a>
            @endif
        </div>
    </section>
@endif
