<section
    wire:poll.visible.1s="refreshOrderFulfilment"
    class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900"
>
    <h2 class="text-base font-semibold text-zinc-950 dark:text-white">{{ __('guest.table.order') }}</h2>

    @if ($fulfilmentFeedbackMessage)
        <p class="mt-3 rounded-md bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
            {{ $fulfilmentFeedbackMessage }}
        </p>
    @endif

    @foreach (['order_dispatch', 'order_cancellation', 'order_service', 'orderCancellationReason'] as $errorField)
        @error($errorField)
            <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-100">{{ $message }}</p>
        @enderror
    @endforeach

    @if (data_get($orderFulfilment, 'draft.order_id'))
        <div class="mt-4 text-sm">
            <p class="font-medium text-zinc-950 dark:text-white">
                {{ __('guest.table.order') }} #{{ data_get($orderFulfilment, 'draft.order_id') }}
            </p>
            <p class="mt-1 text-zinc-500 dark:text-zinc-400">
                {{ __('guest.table.status') }}: {{ __(data_get($orderFulfilment, 'draft.order_status_label')) }}
            </p>

            @if (data_get($orderFulfilment, 'draft.order_status_value') === 'cancelled')
                <p class="mt-2 rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-800 dark:bg-red-950/40 dark:text-red-100">
                    {{ __('ui.livewire.waiter.tabledetail.order_cancelled') }}
                    @if (data_get($orderFulfilment, 'draft.cancellation_reason'))
                        <span class="block pt-1 font-normal">
                            {{ __('guest.table.reason') }}:
                            <x-ui.plain-text :text="data_get($orderFulfilment, 'draft.cancellation_reason')" class="inline" />
                        </span>
                    @endif
                </p>
            @elseif (in_array(data_get($orderFulfilment, 'draft.order_status_value'), ['sent_to_kitchen_bar', 'in_progress', 'ready', 'served'], true))
                <p class="mt-1 text-emerald-700 dark:text-emerald-300">{{ __('ui.waiter.table_detail.kitchen_bar_received_this_order') }}</p>
                <p class="mt-1 text-zinc-500 dark:text-zinc-400">
                    {{ __('ui.departments.dashboard.tickets') }}: {{ data_get($orderFulfilment, 'draft.order_ticket_count', 0) }}
                    · {{ __('guest.statuses.items.ready') }}: {{ data_get($orderFulfilment, 'draft.ready_ticket_item_count', 0) }}
                    · {{ __('guest.statuses.items.served') }}: {{ data_get($orderFulfilment, 'draft.served_ticket_item_count', 0) }}
                    @if (data_get($orderFulfilment, 'draft.order_ticket_departments'))
                        · {{ implode(', ', data_get($orderFulfilment, 'draft.order_ticket_departments', [])) }}
                    @endif
                </p>
            @else
                <p class="mt-1 text-zinc-500 dark:text-zinc-400">{{ __('ui.waiter.table_detail.prepared_for_kitchen_bar_dispatch_but_not_sent_yet') }}</p>
            @endif

            @if (data_get($orderFulfilment, 'draft.order_ticket_items'))
                <div class="mt-4 grid gap-2 lg:grid-cols-2">
                    @foreach (data_get($orderFulfilment, 'draft.order_ticket_items', []) as $ticketItem)
                        <article wire:key="waiter-ticket-item-{{ $ticketItem['id'] }}" class="rounded-md border border-zinc-200 p-3 dark:border-zinc-800">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-medium text-zinc-950 dark:text-white">
                                        {{ $ticketItem['quantity'] }} x
                                        <x-ui.plain-text :text="$ticketItem['item_name']" class="inline" :preserve-lines="false" />
                                    </p>
                                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $ticketItem['department_name'] }}
                                        @if ($ticketItem['guest_name'])
                                            · <x-ui.plain-text :text="$ticketItem['guest_name']" class="inline" :preserve-lines="false" />
                                        @endif
                                    </p>
                                </div>
                                <flux:badge :color="$ticketItem['status_color']">
                                    {{ $ticketItem['is_served'] ? __('guest.statuses.items.served') : __($ticketItem['status_label']) }}
                                </flux:badge>
                            </div>

                            @if ($ticketItem['modifiers'] !== [])
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach ($ticketItem['modifiers'] as $modifier)
                                        <flux:badge wire:key="waiter-ticket-item-{{ $ticketItem['id'] }}-modifier-{{ $loop->index }}" color="zinc">{{ $modifier['label'] }}</flux:badge>
                                    @endforeach
                                </div>
                            @endif

                            @if ($ticketItem['comment'])
                                <p class="mt-2 text-xs text-zinc-600 dark:text-zinc-300">
                                    {{ __('guest.cart.comment') }}: <x-ui.plain-text :text="$ticketItem['comment']" class="inline" />
                                </p>
                            @endif

                            @if ($ticketItem['is_served'])
                                <p class="mt-2 text-xs text-sky-700 dark:text-sky-300">{{ __('ui.waiter.table_detail.served_at') }}: {{ $ticketItem['served_at'] ?? __('ui.departments.dashboard.time_not_set') }}</p>
                            @elseif ($ticketItem['is_ready'])
                                <flux:button size="sm" icon="check" type="button" class="mt-3 w-full" wire:click="markTicketItemServed({{ $ticketItem['id'] }})" wire:loading.attr="disabled" wire:target="markTicketItemServed({{ $ticketItem['id'] }})">
                                    <span wire:loading.remove wire:target="markTicketItemServed({{ $ticketItem['id'] }})">{{ __('ui.waiter.dashboard.mark_served') }}</span>
                                    <span wire:loading wire:target="markTicketItemServed({{ $ticketItem['id'] }})">{{ __('guest.table.saving') }}</span>
                                </flux:button>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif

            @if (data_get($orderFulfilment, 'draft.can_send_to_kitchen'))
                <flux:button icon="arrow-right" variant="primary" type="button" class="mt-3 w-full" wire:click="sendOrderToKitchenBar" wire:loading.attr="disabled" wire:target="sendOrderToKitchenBar">
                    <span wire:loading.remove wire:target="sendOrderToKitchenBar">{{ __('permissions.labels.send_to_kitchen') }}</span>
                    <span wire:loading wire:target="sendOrderToKitchenBar">{{ __('guest.table.sending') }}</span>
                </flux:button>
            @endif

            @if (data_get($orderFulfilment, 'draft.can_cancel'))
                <div class="mt-4 space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                    @if (data_get($orderFulfilment, 'draft.has_ready_or_served_warning'))
                        <p class="rounded-md bg-amber-50 px-3 py-2 text-sm font-medium text-amber-900 dark:bg-amber-950/40 dark:text-amber-100">{{ __('ui.waiter.table_detail.some_positions_are_already_ready_or_served') }}</p>
                    @endif
                    <x-dangerous-action-confirmation
                        name="cancel-current-order"
                        action="cancel_order"
                        confirm-action="cancelOrder"
                        submit-target="cancelOrder"
                        confirm-label="ui.actions.confirm"
                        loading-label="ui.actions.working"
                        reason-model="orderCancellationReason"
                        reason-label="ui.confirmations.reason.label"
                        reason-placeholder="ui.confirmations.reason.placeholder"
                    >
                        <x-slot:trigger>
                            <flux:button icon="x-mark" variant="danger" type="button" class="w-full">{{ __('orders.actions.cancel') }}</flux:button>
                        </x-slot:trigger>
                    </x-dangerous-action-confirmation>
                </div>
            @endif
        </div>
    @endif
</section>
