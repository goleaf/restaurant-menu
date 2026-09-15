<section
    wire:poll.visible.1s="refreshOrderFulfilment"
    class="rounded-lg border border-border-subtle bg-surface p-4"
>
    <h2 class="text-base font-semibold text-text-primary">{{ __('guest.table.order') }}</h2>

    @if ($fulfilmentFeedbackMessage)
        <p class="mt-3 rounded-md bg-success-surface px-3 py-2 text-sm font-medium text-success">
            {{ $fulfilmentFeedbackMessage }}
        </p>
    @endif

    @foreach (['order_dispatch', 'order_cancellation', 'order_item_cancellation', 'order_service', 'orderCancellationReason', 'orderItemCancellationReason'] as $errorField)
        @error($errorField)
            <p class="mt-3 rounded-md bg-danger-surface px-3 py-2 text-sm font-medium text-danger">{{ $message }}</p>
        @enderror
    @endforeach

    @if (data_get($orderFulfilment, 'draft.order_id'))
        <div class="mt-4 text-sm">
            <p class="font-medium text-text-primary">
                {{ __('guest.table.order') }} #{{ data_get($orderFulfilment, 'draft.order_id') }}
            </p>
            <p class="mt-1 text-text-muted">
                {{ __('guest.table.status') }}: {{ __(data_get($orderFulfilment, 'draft.order_status_label')) }}
            </p>

            @if (data_get($orderFulfilment, 'draft.order_status_value') === 'cancelled')
                <p class="mt-2 rounded-md bg-danger-surface px-3 py-2 text-sm font-medium text-danger">
                    {{ __('ui.livewire.waiter.tabledetail.order_cancelled') }}
                    @if (data_get($orderFulfilment, 'draft.cancellation_reason'))
                        <span class="block pt-1 font-normal">
                            {{ __('guest.table.reason') }}:
                            <x-ui.plain-text :text="data_get($orderFulfilment, 'draft.cancellation_reason')" class="inline" />
                        </span>
                    @endif
                </p>
            @elseif (in_array(data_get($orderFulfilment, 'draft.order_status_value'), ['payment_requested', 'paid', 'closed'], true))
                <p class="mt-1 text-success">{{ __('ui.waiter.table_detail.order_service_complete') }}</p>
            @elseif (in_array(data_get($orderFulfilment, 'draft.order_status_value'), ['sent_to_kitchen_bar', 'in_progress', 'ready', 'served'], true))
                <p class="mt-1 text-success">{{ __('ui.waiter.table_detail.kitchen_bar_received_this_order') }}</p>
                <p class="mt-1 text-text-muted">
                    {{ __('ui.departments.dashboard.tickets') }}: {{ data_get($orderFulfilment, 'draft.order_ticket_count', 0) }}
                    · {{ __('guest.statuses.items.ready') }}: {{ data_get($orderFulfilment, 'draft.ready_ticket_item_count', 0) }}
                    · {{ __('guest.statuses.items.served') }}: {{ data_get($orderFulfilment, 'draft.served_ticket_item_count', 0) }}
                    @if (data_get($orderFulfilment, 'draft.order_ticket_departments'))
                        · {{ implode(',', data_get($orderFulfilment, 'draft.order_ticket_departments', [])) }}
                    @endif
                </p>
            @else
                <p class="mt-1 text-text-muted">{{ __('ui.waiter.table_detail.prepared_for_kitchen_bar_dispatch_but_not_sent_yet') }}</p>
            @endif

            @if (data_get($orderFulfilment, 'draft.can_send_to_kitchen'))
                <flux:button icon="arrow-right" variant="primary" type="button" class="mt-3 w-full" wire:click="sendOrderToKitchenBar" wire:offline.attr="disabled" wire:loading.attr="disabled" wire:target="sendOrderToKitchenBar">
                    <span wire:loading.remove wire:target="sendOrderToKitchenBar">{{ __('permissions.labels.send_to_kitchen') }}</span>
                    <span wire:loading wire:target="sendOrderToKitchenBar">{{ __('guest.table.sending') }}</span>
                </flux:button>
            @endif

            @if (data_get($orderFulfilment, 'draft.can_cancel'))
                <div class="mt-4 space-y-3 border-t border-border-subtle pt-4">
                    @if (data_get($orderFulfilment, 'draft.has_ready_or_served_warning'))
                        <p class="rounded-md bg-warning-surface px-3 py-2 text-sm font-medium text-warning">{{ __('ui.waiter.table_detail.some_positions_are_already_ready_or_served') }}</p>
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
                            <flux:button icon="x-mark" variant="primary" color="red" type="button" class="bg-danger! hover:bg-danger/90! dark:text-text-inverse! w-full">{{ __('orders.actions.cancel') }}</flux:button>
                        </x-slot:trigger>
                    </x-dangerous-action-confirmation>
                </div>
            @endif
        </div>
    @endif

    @if (data_get($orderFulfilment, 'orders'))
        <div class="mt-5 space-y-3 border-t border-border-subtle pt-4">
            <h3 class="text-sm font-semibold text-text-primary">{{ __('ui.waiter.table_detail.confirmed_orders') }}</h3>

            @foreach (data_get($orderFulfilment, 'orders', []) as $order)
                <article wire:key="waiter-order-{{ $order['id'] }}" class="rounded-lg border border-border-subtle p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="font-medium text-text-primary">{{ __('guest.table.order') }} #{{ $order['id'] }}</p>
                            <p class="mt-1 text-xs text-text-muted">{{ __('guest.table.status') }}: {{ __($order['status_label']) }}</p>
                        </div>
                        <p class="text-sm font-semibold text-text-primary">{{ $order['total'] }}</p>
                    </div>

                    <div class="mt-3 grid gap-2 lg:grid-cols-2">
                        <p class="text-xs font-medium uppercase text-text-muted lg:col-span-2">{{ __('ui.waiter.table_detail.kitchen_bar_positions') }}</p>
                        @foreach ($order['items'] as $orderItem)
                            <section
                                wire:key="waiter-order-item-{{ $orderItem['id'] }}"
                                @class([
                                    'rounded-md border p-3',
                                    'border-danger-border bg-danger-surface' => $orderItem['is_cancelled'],
                                    'border-border-subtle' => ! $orderItem['is_cancelled'],
                                ])
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p @class(['font-medium text-text-primary', 'line-through decoration-red-500' => $orderItem['is_cancelled']])>
                                            {{ $orderItem['quantity'] }} x
                                            <x-ui.plain-text :text="$orderItem['item_name']" class="inline" :preserve-lines="false" />
                                        </p>
                                        @if ($orderItem['variant_name'])
                                            <p class="mt-1 text-xs font-medium text-success">{{ $orderItem['variant_name'] }}</p>
                                        @endif
                                        <p class="mt-1 text-xs text-text-muted">
                                            @if ($orderItem['department_name'])
                                                {{ $orderItem['department_name'] }}
                                            @endif
                                            @if ($orderItem['guest_name'])
                                                · <x-ui.plain-text :text="$orderItem['guest_name']" class="inline" :preserve-lines="false" />
                                            @endif
                                            · {{ $orderItem['total'] }}
                                        </p>
                                    </div>
                                    <flux:badge :color="$orderItem['status_color']">{{ __($orderItem['status_label']) }}</flux:badge>
                                </div>

                                @if ($orderItem['modifiers'] !== [])
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach ($orderItem['modifiers'] as $modifier)
                                            <flux:badge wire:key="waiter-order-item-{{ $orderItem['id'] }}-modifier-{{ $loop->index }}" color="zinc">{{ $modifier['label'] }}</flux:badge>
                                        @endforeach
                                    </div>
                                @endif

                                @if ($orderItem['comment'])
                                    <p class="mt-2 text-xs text-text-muted">
                                        {{ __('guest.cart.comment') }}: <x-ui.plain-text :text="$orderItem['comment']" class="inline" />
                                    </p>
                                @endif

                                @if ($orderItem['is_cancelled'])
                                    <div class="mt-2 space-y-1 text-xs text-danger">
                                        @if ($orderItem['cancellation_reason'])
                                            <p>{{ __('orders.items.cancellation_reason') }}: <x-ui.plain-text :text="$orderItem['cancellation_reason']" class="inline" /></p>
                                        @endif
                                        @if ($orderItem['cancelled_by'])
                                            <p>{{ __('orders.items.cancelled_by') }}: <x-ui.plain-text :text="$orderItem['cancelled_by']" class="inline" :preserve-lines="false" /></p>
                                        @endif
                                    </div>
                                @elseif ($orderItem['is_served'])
                                    <p class="mt-2 text-xs text-information">{{ __('ui.waiter.table_detail.served_at') }}: {{ $orderItem['served_at'] ?? __('ui.departments.dashboard.time_not_set') }}</p>
                                @elseif ($orderItem['is_ready'] && $orderItem['ticket_item_id'])
                                    <flux:button size="sm" icon="check" type="button" class="mt-3 w-full" wire:click="markTicketItemServed({{ $orderItem['ticket_item_id'] }})" wire:offline.attr="disabled" wire:loading.attr="disabled" wire:target="markTicketItemServed({{ $orderItem['ticket_item_id'] }})">
                                        <span wire:loading.remove wire:target="markTicketItemServed({{ $orderItem['ticket_item_id'] }})">{{ __('ui.waiter.dashboard.mark_served') }}</span>
                                        <span wire:loading wire:target="markTicketItemServed({{ $orderItem['ticket_item_id'] }})">{{ __('guest.table.saving') }}</span>
                                    </flux:button>
                                @endif

                                @if ($orderItem['can_cancel'])
                                    <x-dangerous-action-confirmation
                                        name="cancel-order-item-{{ $orderItem['id'] }}"
                                        title="orders.items.confirmation.title"
                                        consequence="orders.items.confirmation.description"
                                        confirm-action="cancelOrderItem({{ $orderItem['id'] }})"
                                        submit-target="cancelOrderItem({{ $orderItem['id'] }})"
                                        confirm-label="ui.actions.confirm"
                                        loading-label="ui.actions.working"
                                        reason-model="orderItemCancellationReason"
                                        reason-label="ui.confirmations.reason.label"
                                        reason-placeholder="ui.confirmations.reason.placeholder"
                                        :reason-required="true"
                                    >
                                        <x-slot:trigger>
                                            <flux:button icon="x-mark" variant="primary" color="red" size="sm" type="button" class="bg-danger! hover:bg-danger/90! dark:text-text-inverse! mt-3 w-full">{{ __('orders.items.actions.cancel') }}</flux:button>
                                        </x-slot:trigger>
                                    </x-dangerous-action-confirmation>
                                @endif
                            </section>
                        @endforeach
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</section>
