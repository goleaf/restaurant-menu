@if ($card['visible'] ?? false)
    <section
        data-component="guest-error-page"
        data-error-state="{{ $card['state'] ?? 'unknown' }}"
        {{ $attributes->class([
            'overflow-hidden rounded-lg border bg-white shadow-sm dark:bg-zinc-950',
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
                        class="size-14 rounded-lg border border-white/80 bg-white object-contain p-2 shadow-sm dark:border-zinc-800 dark:bg-zinc-950"
                    >
                @else
                    <div class="flex size-14 shrink-0 items-center justify-center rounded-lg border border-white/80 bg-white text-xl font-semibold text-zinc-950 shadow-sm dark:border-zinc-800 dark:bg-zinc-950 dark:text-white">
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
                        <p class="mt-3 truncate text-sm font-medium text-zinc-600 dark:text-zinc-300">{{ $venueName }}</p>
                    @endif

                    <h1 class="mt-2 text-2xl font-semibold leading-tight text-zinc-950 dark:text-white">
                        {{ $card['title'] ?? __('guest.table.guest_access_unavailable_title') }}
                    </h1>

                    <p class="mt-3 text-base leading-6 text-zinc-700 dark:text-zinc-200">
                        {{ $card['message'] ?? '' }}
                    </p>
                </div>
            </div>
        </div>

        <div class="space-y-4 px-5 py-5">
            @if ($card['support_text'] ?? '')
                <p class="text-sm leading-6 text-zinc-600 dark:text-zinc-300">
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
                    class="flex h-11 w-full items-center justify-center rounded-lg border border-zinc-300 bg-white px-4 text-sm font-semibold text-zinc-800 transition hover:bg-zinc-50 focus:outline-hidden focus:ring-2 focus:ring-zinc-500 focus:ring-offset-2 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:hover:bg-zinc-900 dark:focus:ring-offset-zinc-950"
                >
                    {{ $card['secondary_label'] }}
                </a>
            @endif
        </div>
    </section>
@endif
