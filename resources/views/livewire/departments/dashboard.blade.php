<section data-page="{{ $dataPage }}" wire:poll.visible.1s="refreshDepartment" class="flex h-full w-full flex-1 flex-col gap-6">
    <x-ui.page-header
        :title="$pageTitle"
        :description="$pageSubtitle"
        :context="$selectedDepartmentName ?? __('layout.restaurant_workspace')"
    >
        <x-slot:actions>
            <div class="grid gap-3 rounded-control border border-border-subtle bg-surface p-3 sm:grid-cols-[minmax(16rem,24rem)_auto] sm:items-end">
                <flux:select wire:model.live="selectedDepartmentId" label="{{ __('ui.departments.dashboard.department') }}">
                    @foreach ($departments as $department)
                        <flux:select.option wire:key="{{ $dataPage }}-department-option-{{ $department['id'] }}" value="{{ $department['id'] }}">
                            {{ $department['label'] }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <div class="text-sm text-text-muted">
                    <p>{{ __('ui.departments.dashboard.updated') }}: {{ $refreshedAt }}</p>
                    <p>{{ __('ui.departments.dashboard.sort') }}: {{ __('ui.departments.dashboard.oldest_first') }}</p>
                </div>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    @error('ticket_item_status')
        <x-ui.alert tone="danger">{{ $message }}</x-ui.alert>
    @enderror

    @if ($feedbackMessage)
        <x-ui.alert tone="success">{{ $feedbackMessage }}</x-ui.alert>
    @endif

    <x-ui.metric-strip :items="[
        ['label' => 'ui.departments.dashboard.tickets', 'value' => $ticketCount],
        ['label' => 'ui.departments.dashboard.new', 'value' => $newItemCount, 'tone' => $newItemCount > 0 ? 'danger' : 'neutral'],
        ['label' => 'reports.statuses.orders.in_progress', 'value' => $inProgressItemCount, 'tone' => $inProgressItemCount > 0 ? 'warning' : 'neutral'],
        ['label' => 'guest.statuses.items.ready', 'value' => $readyItemCount, 'tone' => $readyItemCount > 0 ? 'success' : 'neutral'],
    ]" />

    <div data-department-priority-queue class="grid gap-5 2xl:grid-cols-2">
        @forelse ($tickets as $ticket)
            <article wire:key="{{ $dataPage }}-ticket-{{ $ticket['id'] }}" class="overflow-hidden rounded-card border border-border-subtle bg-surface">
                <header class="border-b border-border-subtle p-4">
                    <x-ui.priority-row
                        :title="$ticket['service_point_name']"
                        :description="$ticket['work_status']['label']"
                        :tone="$ticket['delay_state'] === 'delayed' ? 'danger' : ($ticket['delay_state'] === 'attention' ? 'warning' : 'neutral')"
                    >
                        <x-slot:leading>
                            <span class="flex min-h-operational-touch min-w-operational-touch items-center justify-center rounded-control bg-accent px-3 text-base font-semibold text-accent-foreground">
                                {{ $ticket['service_point_display_number'] !== '' ? $ticket['service_point_display_number'] : __('guest.table.place') }}
                            </span>
                        </x-slot:leading>

                        <x-slot:meta>
                            <span>{{ __('guest.table.zone') }}: {{ $ticket['zone_name'] ?? __('qr.filters.no_zone') }}</span>
                            <span>{{ $itemCountLabel }}: {{ $ticket['item_count'] }}</span>
                            <span>{{ __('qr.labels.created') }}: {{ $ticket['sent_at'] ?? __('ui.departments.dashboard.time_not_set') }}</span>
                        </x-slot:meta>

                        <x-slot:actions>
                            <flux:button class="min-h-operational-touch" icon="printer" size="sm" :href="route('restaurant.departments.tickets.print', $ticket['id'])" wire:navigate>
                                {{ __('ui.departments.dashboard.print') }}
                            </flux:button>
                        </x-slot:actions>
                    </x-ui.priority-row>

                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_14rem] lg:items-start">
                        <div class="hidden lg:block" aria-hidden="true"></div>

                        <div
                            data-kitchen-delay-timer
                            data-elapsed-seconds="{{ $ticket['elapsed_seconds'] }}"
                            data-attention-after-seconds="{{ $ticket['attention_after_seconds'] }}"
                            data-delayed-after-seconds="{{ $ticket['delayed_after_seconds'] }}"
                            data-delay-state="{{ $ticket['delay_state'] }}"
                            data-label-on-track="{{ __('ui.departments.dashboard.delay_status.on_track') }}"
                            data-label-attention="{{ __('ui.departments.dashboard.delay_status.attention') }}"
                            data-label-delayed="{{ __('ui.departments.dashboard.delay_status.delayed') }}"
                            data-delay-template="{{ __('ui.departments.dashboard.delay_by', ['time' => ':time']) }}"
                            class="mt-3 rounded-control border p-3 text-center data-[delay-state=attention]:border-warning-border data-[delay-state=attention]:bg-warning-surface data-[delay-state=attention]:text-warning data-[delay-state=delayed]:border-danger-border data-[delay-state=delayed]:bg-danger-surface data-[delay-state=delayed]:text-danger data-[delay-state=on-track]:border-success-border data-[delay-state=on-track]:bg-success-surface data-[delay-state=on-track]:text-success lg:mt-3"
                        >
                            <p class="text-sm font-medium">{{ __('ui.departments.dashboard.preparation_time') }}</p>
                            <time
                                data-kitchen-delay-value
                                datetime="PT{{ $ticket['elapsed_seconds'] }}S"
                                class="mt-1 block text-4xl font-semibold tabular-nums"
                            >{{ $ticket['elapsed_label'] }}</time>
                            <p data-kitchen-delay-status role="status" aria-atomic="true" class="mt-2 text-sm font-semibold">
                                {{ $ticket['delay_status_label'] }}
                            </p>
                            <p data-kitchen-delay-overrun class="mt-1 text-xs font-medium" @if ($ticket['delay_description'] === null) hidden @endif>
                                {{ $ticket['delay_description'] }}
                            </p>
                        </div>
                    </div>
                </header>

                <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @foreach ($ticket['items'] as $item)
                        <section wire:key="{{ $dataPage }}-ticket-item-{{ $item['id'] }}" data-ticket-item-status="{{ $item['status_value'] }}" class="grid gap-4 p-5 lg:grid-cols-[minmax(0,1fr)_18rem]">
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
