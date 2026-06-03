<section
    data-page="waiter-table-detail"
    wire:poll.1s="refreshTable"
    class="flex h-full w-full flex-1 flex-col gap-6"
>
    <header class="flex flex-col gap-3">
        <div>
            <flux:button icon="arrow-left" :href="route('restaurant.waiter.dashboard')" wire:navigate>
                {{ __('Waiter dashboard') }}
            </flux:button>
        </div>

        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div class="min-w-0">
                <p class="truncate text-sm font-medium text-zinc-500 dark:text-zinc-400">
                    {{ data_get($table, 'branch.organization_name') }} / {{ data_get($table, 'branch.brand_name') }} / {{ data_get($table, 'branch.name') }}
                </p>
                <h1 class="mt-1 truncate text-2xl font-semibold text-zinc-950 dark:text-white">
                    {{ data_get($table, 'service_point.name') }}
                </h1>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                    {{ __('Zone') }}: {{ data_get($table, 'zone.name') ?? __('No zone') }}
                    · {{ __('Number') }}: {{ data_get($table, 'service_point.display_number') ?: __('Not set') }}
                </p>
            </div>

            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Updated') }}: {{ $refreshedAt }}
            </div>
        </div>
    </header>

    <section class="grid gap-3 md:grid-cols-4">
        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Session status') }}</p>
            <p class="mt-2 text-base font-semibold text-zinc-950 dark:text-white">{{ __(data_get($table, 'session.status_label')) }}</p>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Draft status') }}</p>
            <p class="mt-2 text-base font-semibold text-zinc-950 dark:text-white">{{ __(data_get($table, 'draft.status_label')) }}</p>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Guests') }}</p>
            <p class="mt-2 text-base font-semibold text-zinc-950 dark:text-white">{{ data_get($table, 'guest_count', 0) }}</p>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Table total') }}</p>
            <p class="mt-2 text-base font-semibold text-zinc-950 dark:text-white">{{ data_get($table, 'total', '0.00') }}</p>
        </div>
    </section>

    <section class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                <h2 class="text-base font-semibold text-zinc-950 dark:text-white">{{ __('Guests and positions') }}</h2>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Guests are sorted alphabetically.') }}</p>
            </div>

            <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @forelse (data_get($table, 'guest_sections', []) as $guestSection)
                    <section wire:key="waiter-table-guest-{{ $guestSection['guest_id'] }}" class="px-4 py-4">
                        <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-base font-semibold text-zinc-950 dark:text-white">{{ $guestSection['guest_name'] }}</h3>
                                    <flux:badge :color="$guestSection['is_ready'] ? 'green' : 'zinc'">
                                        {{ $guestSection['is_ready'] ? __('Ready') : __('Not ready') }}
                                    </flux:badge>
                                    <flux:badge>{{ __($guestSection['status_label']) }}</flux:badge>
                                </div>
                            </div>

                            <p class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $guestSection['total'] }}</p>
                        </div>

                        <div class="mt-3 space-y-3">
                            @forelse ($guestSection['items'] as $item)
                                <article wire:key="waiter-table-item-{{ $item['id'] }}" class="rounded-md bg-zinc-50 p-3 dark:bg-zinc-800">
                                    <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                                        <div class="min-w-0">
                                            <p class="font-medium text-zinc-950 dark:text-white">
                                                {{ $item['quantity'] }} x {{ $item['item_name'] }}
                                            </p>
                                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                                {{ __('Unit') }}: {{ $item['unit_total_price'] }}
                                                · {{ __('Line') }}: {{ $item['total_price'] }}
                                            </p>
                                        </div>

                                        <p class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $item['total_price'] }}</p>
                                    </div>

                                    @if ($item['modifiers'] !== [])
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @foreach ($item['modifiers'] as $modifier)
                                                <flux:badge wire:key="waiter-table-item-{{ $item['id'] }}-modifier-{{ $loop->index }}">
                                                    {{ $modifier['label'] }}
                                                    @if ($modifier['price_delta'])
                                                        · {{ $modifier['price_delta'] }}
                                                    @endif
                                                </flux:badge>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if ($item['comment'])
                                        <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">
                                            {{ __('Comment') }}: {{ $item['comment'] }}
                                        </p>
                                    @endif
                                </article>
                            @empty
                                <p class="rounded-md bg-zinc-50 px-3 py-2 text-sm text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                                    {{ __('No positions yet.') }}
                                </p>
                            @endforelse
                        </div>
                    </section>
                @empty
                    <div class="px-4 py-8 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('No guests yet.') }}
                    </div>
                @endforelse
            </div>
        </div>

        <aside class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <h2 class="text-base font-semibold text-zinc-950 dark:text-white">{{ __('Table summary') }}</h2>

            <dl class="mt-4 space-y-3 text-sm">
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Branch') }}</dt>
                    <dd class="mt-1 font-medium text-zinc-950 dark:text-white">{{ data_get($table, 'branch.name') }}</dd>
                </div>

                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Zone') }}</dt>
                    <dd class="mt-1 font-medium text-zinc-950 dark:text-white">{{ data_get($table, 'zone.name') ?? __('No zone') }}</dd>
                </div>

                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Service point') }}</dt>
                    <dd class="mt-1 font-medium text-zinc-950 dark:text-white">{{ data_get($table, 'service_point.name') }}</dd>
                </div>

                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Service point status') }}</dt>
                    <dd class="mt-1">
                        <flux:badge :color="data_get($table, 'service_point.status_color', 'zinc')">
                            {{ __(data_get($table, 'service_point.status_label')) }}
                        </flux:badge>
                    </dd>
                </div>

                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Opened') }}</dt>
                    <dd class="mt-1 font-medium text-zinc-950 dark:text-white">{{ data_get($table, 'session.started_at') ?? __('time not set') }}</dd>
                </div>

                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Opened by') }}</dt>
                    <dd class="mt-1 font-medium text-zinc-950 dark:text-white">{{ data_get($table, 'session.opened_by') ?? __('Not set') }}</dd>
                </div>

                @if (data_get($table, 'draft.sent_by_guest_name'))
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Sent by') }}</dt>
                        <dd class="mt-1 font-medium text-zinc-950 dark:text-white">{{ data_get($table, 'draft.sent_by_guest_name') }}</dd>
                    </div>
                @endif

                @if (data_get($table, 'draft.sent_at'))
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Sent at') }}</dt>
                        <dd class="mt-1 font-medium text-zinc-950 dark:text-white">{{ data_get($table, 'draft.sent_at') }}</dd>
                    </div>
                @endif
            </dl>
        </aside>
    </section>
</section>
