<main data-page="department-ticket-print" class="qr-print-page">
    <div class="qr-print-toolbar">
        <div class="flex flex-col gap-1">
            <p class="text-sm font-medium text-zinc-500">{{ __('ui.departments.ticket_print.browser_print') }}</p>
            <h1 class="text-2xl font-semibold text-zinc-950">{{ __('ui.livewire.departments.ticketprint.kitchen_ticket_print') }}</h1>
            <p class="text-sm text-zinc-600">
                {{ __('ui.departments.ticket_print.print_this_ticket_from_the_browser_no_printer_i') }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <flux:button
                icon="arrow-left"
                :href="$print['department']['type'] === 'bar' ? route('restaurant.bar.dashboard') : route('restaurant.kitchen.dashboard')"
                wire:navigate
            >
                {{ __('ui.departments.ticket_print.back') }}
            </flux:button>

            <flux:button icon="printer" variant="primary" type="button" x-on:click="window.print()">
                {{ __('ui.departments.dashboard.print') }}
            </flux:button>
        </div>
    </div>

    <article class="w-full max-w-3xl rounded-lg border border-zinc-300 bg-white p-6 text-zinc-950 shadow-sm">
        <header class="border-b border-zinc-300 pb-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase text-zinc-500">{{ __('ui.departments.dashboard.department') }}</p>
                    <h2 class="mt-1 text-3xl font-semibold">{{ $print['department']['name'] }}</h2>
                    <p class="mt-1 text-base text-zinc-600">{{ $print['department']['type_label'] }}</p>
                </div>

                <div class="rounded-lg border border-zinc-300 px-4 py-3 text-right">
                    <p class="text-sm text-zinc-500">{{ __('ui.departments.ticket_print.order_number') }}</p>
                    <p class="text-3xl font-semibold tabular-nums">{{ $print['ticket']['order_number'] }}</p>
                    <p class="mt-1 text-sm text-zinc-500">{{ $print['ticket']['status_label'] }}</p>
                </div>
            </div>

            <dl class="mt-6 grid gap-3 text-sm sm:grid-cols-2">
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="font-medium text-zinc-500">{{ __('guest.table.branch') }}</dt>
                    <dd class="mt-1 font-semibold">{{ $print['branch']['name'] }}</dd>
                    @if ($print['branch']['city'] || $print['branch']['country'])
                        <dd class="mt-1 text-zinc-600">
                            @if ($print['branch']['city'])
                                {{ $print['branch']['city'] }}
                            @endif
                            @if ($print['branch']['city'] && $print['branch']['country'])
                                ,
                            @endif
                            @if ($print['branch']['country'])
                                {{ $print['branch']['country'] }}
                            @endif
                        </dd>
                    @endif
                </div>

                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="font-medium text-zinc-500">{{ __('guest.table.service_point') }}</dt>
                    <dd class="mt-1 font-semibold">{{ $print['service_point']['label'] }}</dd>
                    <dd class="mt-1 text-zinc-600">{{ __('guest.table.zone') }}: {{ $print['service_point']['zone_name'] ?? __('qr.filters.no_zone') }}</dd>
                </div>

                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="font-medium text-zinc-500">{{ __('ui.departments.ticket_print.ticket_time') }}</dt>
                    <dd class="mt-1 font-semibold">{{ $print['ticket']['sent_at'] ?? __('ui.departments.dashboard.time_not_set') }}</dd>
                    <dd class="mt-1 text-zinc-600">{{ $print['ticket']['timezone'] }}</dd>
                </div>

                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="font-medium text-zinc-500">{{ __('ui.departments.ticket_print.printed_at') }}</dt>
                    <dd class="mt-1 font-semibold">{{ $print['ticket']['printed_at'] }}</dd>
                </div>
            </dl>
        </header>

        <section class="divide-y divide-zinc-300">
            @forelse ($print['items'] as $item)
                <article class="grid gap-4 py-5 sm:grid-cols-[4rem_minmax(0,1fr)]">
                    <div class="flex h-14 w-14 items-center justify-center rounded-lg border border-zinc-300 text-2xl font-semibold tabular-nums">
                        {{ $item['quantity'] }}×
                    </div>

                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-xl font-semibold">{{ $item['item_name'] }}</h3>
                            <span class="rounded-full border border-zinc-300 px-2 py-0.5 text-xs font-medium text-zinc-600">
                                {{ $item['status_label'] }}
                            </span>
                        </div>

                        @if ($item['guest_name'])
                            <p class="mt-1 text-sm text-zinc-600">{{ __('guest.table.guest') }}: {{ $item['guest_name'] }}</p>
                        @endif

                        @if ($item['selected_modifiers'] !== [])
                            <ul class="mt-3 flex flex-wrap gap-2">
                                @foreach ($item['selected_modifiers'] as $modifier)
                                    <li class="rounded-md border border-zinc-300 px-2 py-1 text-sm">
                                        {{ $modifier['label'] }}
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($item['comment'])
                            <p class="mt-3 whitespace-pre-line break-words rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-base font-medium text-amber-950">
                                {{ $item['comment'] }}
                            </p>
                        @endif
                    </div>
                </article>
            @empty
                <div class="py-8 text-center text-sm text-zinc-500">
                    {{ __('ui.departments.ticket_print.no_ticket_items_yet') }}
                </div>
            @endforelse
        </section>
    </article>
</main>
