@pushOnce('page-module-status', 'departments-status')
    <x-page-module-status module="departments" :heading="__('frontend.timers_loading')" :description="__('frontend.timers_loading_help')" />
@endPushOnce

<section
    x-data="kitchenTimers"
    data-page="{{ $dataPage }}"
    wire:poll.visible.3s="refreshDepartment"
    wire:loading.attr="aria-busy"
    wire:target="refreshDepartment,setItemStatus,previousTicketPage,nextTicketPage"
    aria-busy="false"
    class="flex h-full w-full min-w-0 flex-1 flex-col gap-6"
>
    <p role="status" aria-atomic="true" class="sr-only">{{ $updateAnnouncement }}</p>

    <x-ui.page-header
        :title="$pageTitle"
        :description="$pageSubtitle"
        :context="$selectedDepartmentName ?? __('layout.restaurant_workspace')"
    >
        <x-slot:actions>
            <div class="grid min-w-0 gap-3 rounded-control border border-border-subtle bg-surface p-3 xl:grid-cols-[minmax(15rem,22rem)_minmax(12rem,18rem)_auto] xl:items-end">
                <flux:select wire:model.live="selectedDepartmentId" label="{{ __('ui.departments.dashboard.department') }}">
                    @foreach ($departments as $department)
                        <flux:select.option wire:key="{{ $dataPage }}-department-option-{{ $department['id'] }}" value="{{ $department['id'] }}">
                            {{ $department['label'] }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="ticketFilter" label="{{ __('ui.departments.dashboard.filter') }}">
                    @foreach ($ticketFilterOptions as $filterValue => $filterLabel)
                        <flux:select.option wire:key="{{ $dataPage }}-filter-{{ $filterValue }}" value="{{ $filterValue }}">
                            {{ $filterLabel }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <div class="min-h-10 text-sm text-text-muted" aria-live="polite">
                    <p>
                        <span wire:loading.remove wire:target="refreshDepartment">{{ __('ui.departments.dashboard.updated') }}: {{ $refreshedAt }}</span>
                        <span wire:loading wire:target="refreshDepartment">{{ __('ui.departments.dashboard.updating') }}</span>
                    </p>
                    <p>{{ __('ui.departments.dashboard.sort') }}: {{ $sortLabel }}</p>
                </div>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    @error('ticket_item_status')
        <flux:callout variant="danger" icon="x-circle" role="status" class="callout-contrast content-safe">
            <flux:callout.text>{{ $message }}</flux:callout.text>
        </flux:callout>
    @enderror

    @if ($feedbackMessage)
        <flux:callout variant="success" icon="check-circle" role="status" class="callout-contrast content-safe">
            <flux:callout.text>{{ $feedbackMessage }}</flux:callout.text>
        </flux:callout>
    @endif

    <x-ui.metric-strip :items="[
        ['label' => 'ui.departments.dashboard.tickets', 'value' => $ticketCount],
        ['label' => 'statuses.kitchen_ticket_item.new', 'value' => $newItemCount, 'tone' => $newItemCount > 0 ? 'danger' : 'neutral'],
        ['label' => 'statuses.kitchen_ticket_item.accepted', 'value' => $acceptedItemCount, 'tone' => $acceptedItemCount > 0 ? 'information' : 'neutral'],
        ['label' => 'statuses.kitchen_ticket_item.in_progress', 'value' => $inProgressItemCount, 'tone' => $inProgressItemCount > 0 ? 'warning' : 'neutral'],
        ['label' => 'statuses.kitchen_ticket_item.ready', 'value' => $readyItemCount, 'tone' => $readyItemCount > 0 ? 'success' : 'neutral'],
        ['label' => 'ui.departments.dashboard.completed', 'value' => $completedItemCount],
        ['label' => 'statuses.kitchen_ticket_item.cancelled', 'value' => $cancelledItemCount],
    ]" />

    <div data-department-priority-queue class="grid gap-5 2xl:grid-cols-2">
        @forelse ($tickets as $ticket)
            <article wire:key="{{ $dataPage }}-ticket-{{ $ticket['id'] }}" class="overflow-hidden rounded-card border border-border-subtle bg-surface shadow-card">
                <header class="border-b border-border-subtle p-4">
                    <x-ui.priority-row
                        class="min-w-0 [&_span]:break-words"
                        :title="$ticket['service_point_label']"
                        :description="$ticket['work_status']['label']"
                        :tone="$ticket['is_terminal'] ? 'neutral' : ($ticket['delay_state'] === 'delayed' ? 'danger' : ($ticket['delay_state'] === 'attention' ? 'warning' : 'neutral'))"
                    >
                        <x-slot:leading>
                            <span class="flex min-h-operational-touch min-w-operational-touch items-center justify-center rounded-control bg-accent px-3 text-base font-semibold text-accent-foreground">
                                {{ $ticket['service_point_display_number'] !== '' ? $ticket['service_point_display_number'] : __('guest.table.place') }}
                            </span>
                        </x-slot:leading>

                        <x-slot:meta>
                            <span>{{ __('guest.table.zone') }}: {{ $ticket['zone_name'] ?? __('qr.filters.no_zone') }}</span>
                            <span>{{ $itemCountLabel }}: {{ $ticket['item_count'] }}</span>
                            <span>{{ __('ui.departments.dashboard.order_status') }}: {{ $ticket['order_status_label'] }}</span>
                            <span>{{ __('ui.departments.dashboard.sent_at') }}: {{ $ticket['sent_at'] ?? __('ui.departments.dashboard.time_not_set') }}</span>
                        </x-slot:meta>

                        <x-slot:actions>
                            <flux:button class="min-h-operational-touch" icon="printer" size="sm" :href="route('restaurant.departments.tickets.print', $ticket['id'])" wire:navigate>
                                {{ __('ui.departments.dashboard.print') }}
                            </flux:button>
                        </x-slot:actions>
                    </x-ui.priority-row>

                    <div
                        data-kitchen-delay-timer
                        data-elapsed-seconds="{{ $ticket['elapsed_seconds'] }}"
                        data-attention-after-seconds="{{ $ticket['attention_after_seconds'] }}"
                        data-delayed-after-seconds="{{ $ticket['delayed_after_seconds'] }}"
                        data-delay-state="{{ $ticket['delay_state'] }}"
                        data-timer-stopped="{{ $ticket['is_terminal'] ? 'true' : 'false' }}"
                        data-label-on-track="{{ __('ui.departments.dashboard.delay_status.on_track') }}"
                        data-label-attention="{{ __('ui.departments.dashboard.delay_status.attention') }}"
                        data-label-delayed="{{ __('ui.departments.dashboard.delay_status.delayed') }}"
                        data-delay-template="{{ __('ui.departments.dashboard.delay_by', ['time' => ':time']) }}"
                        class="mt-3 flex flex-wrap items-baseline gap-x-3 gap-y-1 rounded-control border px-3 py-2 data-[delay-state=attention]:border-warning-border data-[delay-state=attention]:bg-warning-surface data-[delay-state=attention]:text-warning data-[delay-state=delayed]:border-danger-border data-[delay-state=delayed]:bg-danger-surface data-[delay-state=delayed]:text-danger data-[delay-state=on-track]:border-border-subtle data-[delay-state=on-track]:bg-surface-muted data-[delay-state=on-track]:text-text-muted"
                    >
                        <p class="text-sm font-medium">{{ __('ui.departments.dashboard.elapsed_since_sent') }}</p>
                        <time
                            data-kitchen-delay-value
                            datetime="PT{{ $ticket['elapsed_seconds'] }}S"
                            class="text-lg font-semibold tabular-nums"
                        >{{ $ticket['elapsed_label'] }}</time>
                        <p data-kitchen-delay-status aria-live="off" class="text-sm font-semibold">
                            {{ $ticket['delay_status_label'] }}
                        </p>
                        <p data-kitchen-delay-overrun class="text-xs font-medium" @if ($ticket['delay_description'] === null) hidden @endif>
                            {{ $ticket['delay_description'] }}
                        </p>
                    </div>
                </header>

                <div class="divide-y divide-border-subtle">
                    @foreach ($ticket['items'] as $item)
                        <section
                            id="{{ $dataPage }}-ticket-item-{{ $item['id'] }}"
                            wire:key="{{ $dataPage }}-ticket-item-{{ $item['id'] }}"
                            data-ticket-item-status="{{ $item['status_value'] }}"
                            class="grid gap-5 p-5 lg:grid-cols-[minmax(0,1fr)_18rem]"
                        >
                            <div class="min-w-0">
                                <div class="flex flex-wrap gap-4">
                                    <div class="flex h-14 min-w-14 shrink-0 self-start items-center justify-center rounded-control bg-surface-raised px-3 text-xl font-semibold text-text-primary">
                                        {{ $item['quantity'] }}×
                                    </div>

                                    <div class="min-w-0 flex-1 basis-40">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <x-ui.plain-text :text="$item['item_name']" class="min-w-0 block text-xl font-semibold text-text-primary" :preserve-lines="false" />
                                            <flux:badge :color="$item['status_color']" class="max-w-full whitespace-normal!">{{ __($item['status_key']) }}</flux:badge>
                                        </div>

                                        @if ($item['guest_name'])
                                            <p class="mt-1 text-sm text-text-muted">
                                                {{ __('guest.table.guest') }}:
                                                <x-ui.plain-text :text="$item['guest_name']" class="inline" :preserve-lines="false" />
                                            </p>
                                        @endif

                                        @if ($item['completed_at'])
                                            <p class="mt-1 text-sm text-text-muted">{{ __('ui.departments.dashboard.completed_at') }}: {{ $item['completed_at'] }}</p>
                                        @endif
                                    </div>
                                </div>

                                @if ($item['allergens'] !== [])
                                    <div role="note" class="mt-4 rounded-control border border-danger-border bg-danger-surface px-4 py-3 text-danger">
                                        <p class="text-sm font-semibold">{{ __('ui.departments.dashboard.allergens') }}</p>
                                        <ul class="mt-2 flex flex-wrap gap-2" aria-label="{{ __('ui.departments.dashboard.allergens') }}">
                                            @foreach ($item['allergens'] as $allergen)
                                                <li wire:key="{{ $dataPage }}-ticket-item-{{ $item['id'] }}-allergen-{{ $allergen['value'] }}">
                                                    <flux:badge color="red">{{ $allergen['label'] }}</flux:badge>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                @if ($item['modifiers'] !== [])
                                    <div class="mt-4">
                                        <p class="text-sm font-semibold text-text-muted">{{ __('ui.departments.dashboard.modifiers') }}</p>
                                        <ul class="mt-2 flex flex-wrap gap-2">
                                            @foreach ($item['modifiers'] as $modifier)
                                                <li wire:key="{{ $dataPage }}-ticket-item-{{ $item['id'] }}-modifier-{{ $loop->index }}">
                                                    <flux:badge color="zinc">{{ $modifier['label'] }}</flux:badge>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                @if ($item['comment'])
                                    <div class="mt-4 rounded-control border border-warning-border bg-warning-surface px-4 py-3 text-warning">
                                        <p class="text-sm font-semibold">{{ __('ui.departments.dashboard.comment') }}</p>
                                        <x-ui.plain-text :text="$item['comment']" class="mt-1 block text-base font-medium" />
                                    </div>
                                @endif

                                @if ($item['cancellation_reason'])
                                    <div class="mt-4 rounded-control border border-border-subtle bg-surface-raised px-4 py-3 text-text-primary">
                                        <p class="text-sm font-semibold">{{ __('ui.departments.dashboard.cancellation_reason') }}</p>
                                        <x-ui.plain-text :text="$item['cancellation_reason']" class="mt-1 block text-sm" />
                                    </div>
                                @endif
                            </div>

                            <div class="grid content-start gap-3">
                                @if ($item['can_accept'])
                                    <flux:button variant="primary"
                                        type="button"
                                        wire:click="setItemStatus({{ $item['id'] }}, 'accepted')"
                                        wire:offline.attr="disabled" wire:loading.attr="disabled"
                                        wire:target="setItemStatus"
                                        class="h-auto! min-h-operational-touch min-w-0 whitespace-normal! py-2.5 text-base! touch-manipulation"
                                    >
                                        {{ __('ui.departments.dashboard.accept') }}
                                    </flux:button>
                                @endif

                                @if ($item['can_start'])
                                    <flux:button variant="primary"
                                        type="button"
                                        wire:click="setItemStatus({{ $item['id'] }}, 'in_progress')"
                                        wire:offline.attr="disabled" wire:loading.attr="disabled"
                                        wire:target="setItemStatus"
                                        class="h-auto! min-h-operational-touch min-w-0 whitespace-normal! py-2.5 text-base! touch-manipulation"
                                    >
                                        {{ __('ui.departments.dashboard.start_preparing') }}
                                    </flux:button>
                                @endif

                                @if ($item['can_mark_ready'])
                                    <flux:button variant="primary"
                                        type="button"
                                        wire:click="setItemStatus({{ $item['id'] }}, 'ready')"
                                        wire:offline.attr="disabled" wire:loading.attr="disabled"
                                        wire:target="setItemStatus"
                                        class="h-auto! min-h-operational-touch min-w-0 whitespace-normal! py-2.5 text-base! touch-manipulation"
                                    >
                                        {{ __('ui.departments.dashboard.mark_ready') }}
                                    </flux:button>
                                @endif

                                @if (! $item['can_accept'] && ! $item['can_start'] && ! $item['can_mark_ready'])
                                    <p class="min-h-operational-touch rounded-control border border-border-subtle bg-surface-raised px-4 py-3 text-center text-sm font-semibold text-text-muted">
                                        {{ __($item['status_key']) }}
                                    </p>
                                @endif
                            </div>
                        </section>
                    @endforeach
                </div>
            </article>
        @empty
            <x-ui.empty-state
                icon="clipboard-document-list"
                :heading="$emptyMessage"
                :description="$ticketFilterOptions[$ticketFilter]"
            />
        @endforelse
    </div>

    @if ($hasPreviousTicketPage || $hasNextTicketPage)
        <nav class="flex items-center justify-between gap-3" aria-label="{{ __('ui.departments.dashboard.pagination') }}">
            <flux:button
                type="button"
                wire:click="previousTicketPage"
                wire:offline.attr="disabled" wire:loading.attr="disabled"
                wire:target="previousTicketPage,nextTicketPage"
                :disabled="! $hasPreviousTicketPage"
                class="min-h-operational-touch"
            >
                {{ __('pagination.previous') }}
            </flux:button>

            <p class="text-sm font-medium text-text-muted">{{ __('ui.departments.dashboard.page', ['page' => $ticketPage]) }}</p>

            <flux:button
                type="button"
                wire:click="nextTicketPage"
                wire:offline.attr="disabled" wire:loading.attr="disabled"
                wire:target="previousTicketPage,nextTicketPage"
                :disabled="! $hasNextTicketPage"
                class="min-h-operational-touch"
            >
                {{ __('pagination.next') }}
            </flux:button>
        </nav>
    @endif
</section>
