<section data-page="{{ $dataPage }}" wire:poll.visible.1s="refreshDepartment" class="flex h-full w-full flex-1 flex-col gap-6">
    <header class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
        <div class="min-w-0">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('layout.restaurant_workspace') }}</p>
            <h1 class="mt-1 text-3xl font-semibold text-zinc-950 dark:text-white">{{ $pageTitle }}</h1>
            <p class="mt-2 max-w-3xl text-sm text-zinc-600 dark:text-zinc-300">
                {{ $pageSubtitle }}
            </p>
        </div>

        <div class="grid gap-3 rounded-lg border border-zinc-200 bg-white p-4 shadow-sm sm:grid-cols-[minmax(16rem,24rem)_auto] sm:items-end dark:border-zinc-800 dark:bg-zinc-900">
            <flux:select wire:model.live="selectedDepartmentId" label="{{ __('ui.departments.dashboard.department') }}">
                @foreach ($departments as $department)
                    <flux:select.option wire:key="{{ $dataPage }}-department-option-{{ $department['id'] }}" value="{{ $department['id'] }}">
                        {{ $department['label'] }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                <p>{{ __('ui.departments.dashboard.updated') }}: {{ $refreshedAt }}</p>
                <p>{{ __('ui.departments.dashboard.sort') }}: {{ __('ui.departments.dashboard.oldest_first') }}</p>
            </div>
        </div>
    </header>

    @error('ticket_item_status')
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200">
            {{ $message }}
        </div>
    @enderror

    @if ($feedbackMessage)
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200">
            {{ $feedbackMessage }}
        </div>
    @endif

    <section class="grid gap-3 md:grid-cols-4">
        <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.departments.dashboard.tickets') }}</p>
            <p class="mt-2 text-3xl font-semibold text-zinc-950 dark:text-white">{{ $ticketCount }}</p>
        </div>

        <div class="rounded-lg border border-rose-200 bg-rose-50 p-4 shadow-sm dark:border-rose-900/60 dark:bg-rose-950/30">
            <p class="text-sm text-rose-700 dark:text-rose-200">{{ __('ui.departments.dashboard.new') }}</p>
            <p class="mt-2 text-3xl font-semibold text-rose-950 dark:text-rose-100">{{ $newItemCount }}</p>
        </div>

        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 shadow-sm dark:border-amber-900/60 dark:bg-amber-950/30">
            <p class="text-sm text-amber-700 dark:text-amber-200">{{ __('reports.statuses.orders.in_progress') }}</p>
            <p class="mt-2 text-3xl font-semibold text-amber-950 dark:text-amber-100">{{ $inProgressItemCount }}</p>
        </div>

        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 shadow-sm dark:border-emerald-900/60 dark:bg-emerald-950/30">
            <p class="text-sm text-emerald-700 dark:text-emerald-200">{{ __('guest.statuses.items.ready') }}</p>
            <p class="mt-2 text-3xl font-semibold text-emerald-950 dark:text-emerald-100">{{ $readyItemCount }}</p>
        </div>
    </section>

    <div class="grid gap-5 2xl:grid-cols-2">
        @forelse ($presentedTickets as $ticket)
            <article wire:key="{{ $dataPage }}-ticket-{{ $ticket['id'] }}" class="overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <header class="border-b border-zinc-200 p-5 dark:border-zinc-800">
                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_14rem] lg:items-start">
                        <div class="flex min-w-0 gap-4">
                            <div class="flex h-16 min-w-20 max-w-28 items-center justify-center rounded-lg bg-zinc-950 px-3 text-center text-xl font-semibold leading-tight text-white dark:bg-white dark:text-zinc-950">
                                <span class="truncate">{{ $ticket['service_point_display_number'] !== '' ? $ticket['service_point_display_number'] : __('guest.table.place') }}</span>
                            </div>

                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-ui.plain-text :text="$ticket['service_point_name']" class="block text-2xl font-semibold text-zinc-950 dark:text-white" :preserve-lines="false" />
                                    <flux:badge :color="$ticket['work_status']['color']">{{ __($ticket['work_status']['label']) }}</flux:badge>
                                </div>

                                <div class="mt-2 flex flex-wrap gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                                    <span>{{ __('guest.table.zone') }}: {{ $ticket['zone_name'] ?? __('qr.filters.no_zone') }}</span>
                                    <span aria-hidden="true">·</span>
                                    <span>{{ $itemCountLabel }}: {{ $ticket['item_count'] }}</span>
                                </div>

                                <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('qr.labels.created') }}: {{ $ticket['sent_at'] ?? __('ui.departments.dashboard.time_not_set') }}
                                </p>

                                <div class="mt-3 flex flex-wrap gap-2">
                                    <flux:button icon="printer" size="sm" :href="route('restaurant.departments.tickets.print', $ticket['id'])" wire:navigate>
                                        {{ __('ui.departments.dashboard.print') }}
                                    </flux:button>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border p-4 text-center {{ $ticket['timer_classes'] }}">
                            <p class="text-sm font-medium">{{ __('ui.departments.dashboard.timer') }}</p>
                            <p class="mt-1 text-4xl font-semibold tabular-nums">{{ $ticket['elapsed_label'] }}</p>
                        </div>
                    </div>
                </header>

                <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @foreach ($ticket['items'] as $item)
                        <section wire:key="{{ $dataPage }}-ticket-item-{{ $item['id'] }}" class="grid gap-4 p-5 lg:grid-cols-[minmax(0,1fr)_18rem]">
                            <div class="min-w-0">
                                <div class="flex gap-4">
                                    <div class="flex h-14 min-w-14 items-center justify-center rounded-lg bg-zinc-100 px-3 text-xl font-semibold text-zinc-950 dark:bg-zinc-800 dark:text-white">
                                        {{ $item['quantity'] }}×
                                    </div>

                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <x-ui.plain-text :text="$item['item_name']" class="block text-xl font-semibold text-zinc-950 dark:text-white" :preserve-lines="false" />
                                            <flux:badge :color="$item['status_color']">{{ __($item['status_label']) }}</flux:badge>
                                        </div>

                                        @if ($item['guest_name'])
                                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                                {{ __('guest.table.guest') }}:
                                                <x-ui.plain-text :text="$item['guest_name']" class="inline" :preserve-lines="false" />
                                            </p>
                                        @endif
                                    </div>
                                </div>

                                @if ($item['modifiers'] !== [])
                                    <div class="mt-4 flex flex-wrap gap-2">
                                        @foreach ($item['modifiers'] as $modifier)
                                            <flux:badge wire:key="{{ $dataPage }}-ticket-item-{{ $item['id'] }}-modifier-{{ $loop->index }}" color="zinc">
                                                {{ $modifier['label'] }}
                                            </flux:badge>
                                        @endforeach
                                    </div>
                                @endif

                                @if ($item['comment'])
                                    <p class="mt-4 rounded-lg bg-amber-50 px-4 py-3 text-base font-medium text-amber-950 dark:bg-amber-950/40 dark:text-amber-100">
                                        <x-ui.plain-text :text="$item['comment']" class="inline" />
                                    </p>
                                @endif
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                                <button
                                    type="button"
                                    wire:click="setItemStatus({{ $item['id'] }}, 'in_progress')"
                                    wire:loading.attr="disabled"
                                    wire:target="setItemStatus"
                                    @disabled(! $item['can_start'])
                                    @class([
                                        'min-h-16 rounded-lg border px-4 py-3 text-base font-semibold transition',
                                        'border-amber-300 bg-amber-100 text-amber-950 hover:border-amber-500 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100 dark:hover:border-amber-600' => $item['can_start'],
                                        'cursor-not-allowed border-zinc-200 bg-zinc-100 text-zinc-400 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-600' => ! $item['can_start'],
                                    ])
                                >
                                    {{ __('ui.departments.dashboard.nacat') }}
                                </button>

                                <button
                                    type="button"
                                    wire:click="setItemStatus({{ $item['id'] }}, 'ready')"
                                    wire:loading.attr="disabled"
                                    wire:target="setItemStatus"
                                    @disabled(! $item['can_mark_ready'])
                                    @class([
                                        'min-h-16 rounded-lg border px-4 py-3 text-base font-semibold transition',
                                        'border-emerald-300 bg-emerald-100 text-emerald-950 hover:border-emerald-500 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100 dark:hover:border-emerald-600' => $item['can_mark_ready'],
                                        'cursor-not-allowed border-zinc-200 bg-zinc-100 text-zinc-400 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-600' => ! $item['can_mark_ready'],
                                    ])
                                >
                                    {{ __('ui.departments.dashboard.gotovo') }}
                                </button>
                            </div>
                        </section>
                    @endforeach
                </div>
            </article>
        @empty
            <section class="rounded-lg border border-dashed border-zinc-300 bg-white p-8 text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400">
                {{ $emptyMessage }}
            </section>
        @endforelse
    </div>
</section>
