<section
    data-page="waiter-table-detail"
    wire:poll.visible.1s="refreshTable"
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

                                    @if (data_get($table, 'draft.can_edit'))
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <flux:button
                                                size="sm"
                                                icon="pencil"
                                                type="button"
                                                wire:click="editDraftItem({{ $item['id'] }})"
                                            >
                                                {{ __('Edit') }}
                                            </flux:button>

                                            <flux:button
                                                size="sm"
                                                icon="trash"
                                                variant="danger"
                                                type="button"
                                                wire:click="deleteDraftItem({{ $item['id'] }})"
                                                wire:loading.attr="disabled"
                                                wire:target="deleteDraftItem({{ $item['id'] }})"
                                            >
                                                {{ __('Delete') }}
                                            </flux:button>
                                        </div>
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

                @if (data_get($table, 'confirmed_order_count', 0) > 0)
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Confirmed orders') }}</dt>
                        <dd class="mt-1 font-medium text-zinc-950 dark:text-white">
                            {{ data_get($table, 'confirmed_order_count') }} · {{ data_get($table, 'confirmed_orders_total') }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Current draft total') }}</dt>
                        <dd class="mt-1 font-medium text-zinc-950 dark:text-white">{{ data_get($table, 'current_draft_total') }}</dd>
                    </div>
                @endif

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

            @if ($paymentFeedbackMessage)
                <p class="mt-5 rounded-md bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
                    {{ $paymentFeedbackMessage }}
                </p>
            @endif

            @error('table_session')
                <p class="mt-5 rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-100">{{ $message }}</p>
            @enderror

            @if (data_get($table, 'session.can_close'))
                <div id="close-table" class="mt-5 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                    <h3 class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('Close table session') }}</h3>

                    @if (data_get($table, 'session.close_requires_warning'))
                        <p class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
                            {{ __('Manual close blocks guests from ordering and frees this place. Old orders stay saved.') }}
                        </p>
                    @else
                        <p class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('Close the paid session and make this place available for the next guests.') }}
                        </p>
                    @endif

                    <flux:button
                        icon="check"
                        type="button"
                        class="mt-3 w-full"
                        wire:click="closeTableSession"
                        wire:loading.attr="disabled"
                        wire:target="closeTableSession"
                    >
                        <span wire:loading.remove wire:target="closeTableSession">{{ __('Close table') }}</span>
                        <span wire:loading wire:target="closeTableSession">{{ __('Closing') }}</span>
                    </flux:button>
                </div>
            @endif

            @if (data_get($table, 'payment.can_view'))
                <div class="mt-5 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                    <h3 class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('Payments') }}</h3>

                    @error('manual_payment')
                        <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-100">{{ $message }}</p>
                    @enderror

                    <dl class="mt-4 grid gap-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Confirmed orders') }}</dt>
                            <dd class="font-semibold text-zinc-950 dark:text-white">{{ data_get($table, 'payment.confirmed_total') }}</dd>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Paid') }}</dt>
                            <dd class="font-semibold text-zinc-950 dark:text-white">{{ data_get($table, 'payment.paid_total') }}</dd>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Remaining') }}</dt>
                            <dd class="font-semibold text-zinc-950 dark:text-white">{{ data_get($table, 'payment.remaining_total') }}</dd>
                        </div>
                    </dl>

                    @if (data_get($table, 'payment.has_open_draft'))
                        <p class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
                            {{ __('Finish the current draft before marking payment.') }}
                        </p>
                    @elseif (! data_get($table, 'payment.has_payable_total'))
                        <p class="mt-3 rounded-md bg-zinc-50 px-3 py-2 text-sm text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                            {{ __('No confirmed orders to pay yet.') }}
                        </p>
                    @endif

                    @if (data_get($table, 'payment.can_manage'))
                        <div class="mt-4 space-y-3">
                            <flux:select wire:model="paymentMethod" :label="__('Payment method')">
                                @foreach (data_get($table, 'payment.payment_methods', []) as $paymentMethodOption)
                                    <flux:select.option wire:key="payment-method-{{ $paymentMethodOption['value'] }}" value="{{ $paymentMethodOption['value'] }}">
                                        {{ __($paymentMethodOption['label']) }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>

                            <label class="grid gap-1 text-sm">
                                <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ __('Note') }}</span>
                                <textarea
                                    wire:model="paymentNote"
                                    rows="2"
                                    maxlength="500"
                                    class="rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-emerald-500 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100"
                                ></textarea>
                            </label>

                            @if (data_get($table, 'payment.can_record_table_payment'))
                                <flux:button
                                    icon="banknotes"
                                    variant="primary"
                                    type="button"
                                    class="w-full"
                                    wire:click="recordTablePayment"
                                    wire:loading.attr="disabled"
                                    wire:target="recordTablePayment"
                                >
                                    <span wire:loading.remove wire:target="recordTablePayment">{{ __('Mark whole table paid') }} · {{ data_get($table, 'payment.remaining_total') }}</span>
                                    <span wire:loading wire:target="recordTablePayment">{{ __('Saving') }}</span>
                                </flux:button>
                            @endif

                        </div>
                    @endif

                    <div class="mt-4 space-y-2">
                        <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('Guests') }}</p>

                        @forelse (data_get($table, 'payment.guest_balances', []) as $guestBalance)
                            <article wire:key="payment-guest-{{ $guestBalance['guest_id'] }}" class="rounded-md border border-zinc-200 p-3 dark:border-zinc-800">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-medium text-zinc-950 dark:text-white">{{ $guestBalance['guest_name'] }}</p>
                                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ __('Due') }}: {{ $guestBalance['due'] }}
                                            · {{ __('Paid') }}: {{ $guestBalance['paid'] }}
                                        </p>
                                    </div>

                                    <flux:badge :color="$guestBalance['is_paid'] ? 'lime' : 'zinc'">
                                        {{ $guestBalance['is_paid'] ? __('Paid') : $guestBalance['remaining'] }}
                                    </flux:badge>
                                </div>

                                @if ($guestBalance['covered_by_table_payment'])
                                    <p class="mt-2 text-xs text-lime-700 dark:text-lime-300">
                                        {{ __('Covered by whole-table payment.') }}
                                    </p>
                                @endif

                                @if ($guestBalance['can_record_payment'])
                                    <flux:button
                                        size="sm"
                                        icon="banknotes"
                                        type="button"
                                        class="mt-3 w-full"
                                        wire:click="recordGuestPayment({{ $guestBalance['guest_id'] }})"
                                        wire:loading.attr="disabled"
                                        wire:target="recordGuestPayment({{ $guestBalance['guest_id'] }})"
                                    >
                                        <span wire:loading.remove wire:target="recordGuestPayment({{ $guestBalance['guest_id'] }})">{{ __('Mark guest paid') }} · {{ $guestBalance['remaining'] }}</span>
                                        <span wire:loading wire:target="recordGuestPayment({{ $guestBalance['guest_id'] }})">{{ __('Saving') }}</span>
                                    </flux:button>
                                @endif
                            </article>
                        @empty
                            <p class="rounded-md bg-zinc-50 px-3 py-2 text-sm text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                                {{ __('No guests with confirmed totals yet.') }}
                            </p>
                        @endforelse
                    </div>

                    @if (data_get($table, 'payment.payments'))
                        <div class="mt-4 space-y-2">
                            <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('Payment history') }}</p>

                            @foreach (data_get($table, 'payment.payments', []) as $payment)
                                <div wire:key="manual-payment-{{ $payment['id'] }}" class="rounded-md bg-zinc-50 px-3 py-2 text-sm dark:bg-zinc-800">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="font-medium text-zinc-950 dark:text-white">
                                                {{ $payment['amount'] }} · {{ __($payment['method_label']) }}
                                            </p>
                                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                                {{ __($payment['scope_label']) }}
                                                @if ($payment['guest_name'])
                                                    · {{ $payment['guest_name'] }}
                                                @endif
                                            </p>
                                        </div>

                                        <p class="shrink-0 text-xs text-zinc-500 dark:text-zinc-400">{{ $payment['paid_at'] }}</p>
                                    </div>

                                    @if ($payment['recorded_by_name'] || $payment['note'])
                                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                            @if ($payment['recorded_by_name'])
                                                {{ __('By') }}: {{ $payment['recorded_by_name'] }}
                                            @endif
                                            @if ($payment['note'])
                                                · {{ $payment['note'] }}
                                            @endif
                                        </p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            <div class="mt-5 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                <h3 class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('Waiter review') }}</h3>

                @if ($reviewFeedbackMessage)
                    <p class="mt-3 rounded-md bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
                        {{ $reviewFeedbackMessage }}
                    </p>
                @endif

                @error('draft_review')
                    <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-100">{{ $message }}</p>
                @enderror

                @error('draft_edit')
                    <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-100">{{ $message }}</p>
                @enderror

                @error('order_dispatch')
                    <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-100">{{ $message }}</p>
                @enderror

                @error('order_service')
                    <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-100">{{ $message }}</p>
                @enderror

                @error('rejectionReason')
                    <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-100">{{ $message }}</p>
                @enderror

                @if (data_get($table, 'manual_order.can_add'))
                    <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                        <h3 class="text-sm font-semibold text-zinc-950 dark:text-white">
                            {{ data_get($table, 'draft.can_edit') ? __('Edit draft') : __('Manual waiter order') }}
                        </h3>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('Add dishes for a guest who orders through the waiter. The order still needs waiter confirmation.') }}
                        </p>

                        <div class="mt-3 space-y-3">
                            <flux:select wire:model="addingGuestId" :label="__('Guest')">
                                <flux:select.option value="">{{ __('Choose guest') }}</flux:select.option>
                                @foreach (data_get($table, 'guest_sections', []) as $guestSection)
                                    <flux:select.option wire:key="waiter-add-guest-{{ $guestSection['guest_id'] }}" value="{{ $guestSection['guest_id'] }}">
                                        {{ $guestSection['guest_name'] }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>

                            @error('addingGuestId')
                                <p class="text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror

                            <flux:input
                                wire:model="manualGuestName"
                                :label="__('New guest name')"
                                maxlength="80"
                                placeholder="{{ __('Type a name if the guest is not in the list') }}"
                            />

                            @error('manualGuestName')
                                <p class="text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror

                            <flux:select wire:model.live="addingMenuItemId" :label="__('Dish')">
                                <flux:select.option value="">{{ __('Choose dish') }}</flux:select.option>
                                @foreach ($addableMenuItems as $menuItemOption)
                                    <flux:select.option wire:key="waiter-add-menu-item-{{ $menuItemOption['value'] }}" value="{{ $menuItemOption['value'] }}">
                                        {{ $menuItemOption['label'] }} · {{ $menuItemOption['price'] }} {{ data_get($table, 'branch.currency', 'EUR') }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>

                            @error('addingMenuItemId')
                                <p class="text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror

                            @if ($addableMenuItems === [])
                                <p class="rounded-md bg-zinc-50 px-3 py-2 text-sm text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                                    {{ __('No available dishes in the active menu.') }}
                                </p>
                            @endif

                            <flux:input
                                wire:model.live="addingQuantity"
                                :label="__('Quantity')"
                                type="number"
                                min="1"
                                max="99"
                            />

                            @error('addingQuantity')
                                <p class="text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror

                            @if ($addingModifierGroups !== [])
                                <div class="space-y-3">
                                    @foreach ($addingModifierGroups as $modifierGroup)
                                        <fieldset wire:key="waiter-add-modifier-group-{{ $modifierGroup['id'] }}" class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                                            <legend class="px-1 text-sm font-semibold text-zinc-950 dark:text-white">{{ $modifierGroup['name'] }}</legend>

                                            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                                <span>{{ $modifierGroup['is_required'] ? __('Required') : __('Optional') }}</span>
                                                <span>{{ __('Choose') }} {{ $modifierGroup['min_select'] }}-{{ $modifierGroup['max_select'] }}</span>
                                            </div>

                                            <div class="mt-3 grid gap-2">
                                                @foreach ($modifierGroup['options'] as $modifierOption)
                                                    @php($isSelected = in_array($modifierOption['id'], $addingModifierOptions[(string) $modifierGroup['id']] ?? [], true))
                                                    <button
                                                        type="button"
                                                        wire:key="waiter-add-modifier-option-{{ $modifierOption['id'] }}"
                                                        wire:click="toggleAddingModifierOption({{ $modifierGroup['id'] }}, {{ $modifierOption['id'] }})"
                                                        @class([
                                                            'flex min-h-11 w-full items-center justify-between gap-3 rounded-lg border px-3 py-2 text-left text-sm transition focus:outline-hidden focus:ring-2 focus:ring-emerald-500/30',
                                                            'border-emerald-500 bg-emerald-50 text-emerald-950 dark:border-emerald-500 dark:bg-emerald-950/40 dark:text-emerald-50' => $isSelected,
                                                            'border-zinc-200 bg-white text-zinc-800 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100 dark:hover:bg-zinc-800' => ! $isSelected,
                                                        ])
                                                    >
                                                        <span class="font-medium">{{ $modifierOption['name'] }}</span>
                                                        <span class="shrink-0 font-semibold">{{ ((float) $modifierOption['price_delta']) >= 0 ? '+' : '' }}{{ $modifierOption['price_delta'] }} {{ data_get($table, 'branch.currency', 'EUR') }}</span>
                                                    </button>
                                                @endforeach
                                            </div>

                                            @error('selectedModifierOptions.'.$modifierGroup['id'])
                                                <p class="mt-2 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                                            @enderror
                                        </fieldset>
                                    @endforeach
                                </div>
                            @endif

                            <label class="grid gap-1 text-sm">
                                <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ __('Comment') }}</span>
                                <textarea
                                    wire:model="addingComment"
                                    rows="3"
                                    maxlength="500"
                                    class="rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-emerald-500 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100"
                                ></textarea>
                            </label>

                            @error('addingComment')
                                <p class="text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror

                            <flux:button
                                icon="plus"
                                variant="primary"
                                type="button"
                                class="w-full"
                                wire:click="addDraftItem"
                                wire:loading.attr="disabled"
                                wire:target="addDraftItem"
                            >
                                <span wire:loading.remove wire:target="addDraftItem">
                                    {{ __('Add position') }}
                                    @if ($addingItemTotal !== '0.00')
                                        · {{ $addingItemTotal }} {{ data_get($table, 'branch.currency', 'EUR') }}
                                    @endif
                                </span>
                                <span wire:loading wire:target="addDraftItem">{{ __('Adding') }}</span>
                            </flux:button>
                        </div>
                    </div>
                @endif

                @if (data_get($table, 'draft.can_confirm'))
                    <div class="mt-3 space-y-3">
                        <flux:button
                            icon="check"
                            variant="primary"
                            type="button"
                            class="w-full"
                            wire:click="confirmDraft"
                            wire:loading.attr="disabled"
                            wire:target="confirmDraft"
                        >
                            <span wire:loading.remove wire:target="confirmDraft">{{ __('Confirm order') }}</span>
                            <span wire:loading wire:target="confirmDraft">{{ __('Confirming') }}</span>
                        </flux:button>

                        <p class="text-xs leading-5 text-zinc-500 dark:text-zinc-400">
                            {{ __('Confirmation creates a real order, but does not send it to kitchen or bar yet.') }}
                        </p>
                    </div>
                @endif

                @if (data_get($table, 'draft.can_reject'))
                    <div class="mt-4 space-y-3">
                        <label class="grid gap-1 text-sm">
                            <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ __('Rejection reason') }}</span>
                            <textarea
                                wire:model="rejectionReason"
                                rows="4"
                                maxlength="500"
                                class="rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-red-500 focus:outline-hidden focus:ring-2 focus:ring-red-500/20 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100"
                                placeholder="{{ __('Tell guests what needs to change.') }}"
                            ></textarea>
                        </label>

                        <flux:button
                            icon="x-mark"
                            variant="danger"
                            type="button"
                            class="w-full"
                            wire:click="rejectDraft"
                            wire:loading.attr="disabled"
                            wire:target="rejectDraft"
                        >
                            <span wire:loading.remove wire:target="rejectDraft">{{ __('Reject draft') }}</span>
                            <span wire:loading wire:target="rejectDraft">{{ __('Rejecting') }}</span>
                        </flux:button>
                    </div>
                @endif

                @if (data_get($table, 'draft.rejection_reason'))
                    <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                        <p class="text-xs font-medium uppercase text-red-700 dark:text-red-300">{{ __('Rejected reason') }}</p>
                        <p class="mt-1 text-sm leading-5 text-zinc-700 dark:text-zinc-200">{{ data_get($table, 'draft.rejection_reason') }}</p>

                        @if (data_get($table, 'draft.rejected_by_user_name'))
                            <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ __('Rejected by') }}: {{ data_get($table, 'draft.rejected_by_user_name') }}
                            </p>
                        @endif
                    </div>
                @endif

                @if (data_get($table, 'draft.can_return_to_draft'))
                    <div class="mt-4">
                        <flux:button
                            icon="arrow-uturn-left"
                            type="button"
                            class="w-full"
                            wire:click="returnRejectedDraftToDraft"
                            wire:loading.attr="disabled"
                            wire:target="returnRejectedDraftToDraft"
                        >
                            <span wire:loading.remove wire:target="returnRejectedDraftToDraft">{{ __('Return to draft') }}</span>
                            <span wire:loading wire:target="returnRejectedDraftToDraft">{{ __('Returning') }}</span>
                        </flux:button>
                    </div>
                @endif

                @if (data_get($table, 'draft.order_id'))
                    <div class="mt-4 border-t border-zinc-200 pt-4 text-sm dark:border-zinc-800">
                        <p class="font-medium text-zinc-950 dark:text-white">
                            {{ __('Order') }} #{{ data_get($table, 'draft.order_id') }}
                        </p>
                        <p class="mt-1 text-zinc-500 dark:text-zinc-400">
                            {{ __('Status') }}: {{ __(data_get($table, 'draft.order_status_label')) }}
                        </p>

                        @if (in_array(data_get($table, 'draft.order_status_value'), ['sent_to_kitchen_bar', 'in_progress', 'ready', 'served'], true))
                            <p class="mt-1 text-emerald-700 dark:text-emerald-300">
                                {{ __('Kitchen/bar received this order.') }}
                            </p>

                            <p class="mt-1 text-zinc-500 dark:text-zinc-400">
                                {{ __('Tickets') }}: {{ data_get($table, 'draft.order_ticket_count', 0) }}
                                · {{ __('Ready') }}: {{ data_get($table, 'draft.ready_ticket_item_count', 0) }}
                                · {{ __('Served') }}: {{ data_get($table, 'draft.served_ticket_item_count', 0) }}
                                @if (data_get($table, 'draft.order_ticket_departments'))
                                    · {{ implode(', ', data_get($table, 'draft.order_ticket_departments', [])) }}
                                @endif
                            </p>
                        @else
                            <p class="mt-1 text-zinc-500 dark:text-zinc-400">
                                {{ __('Prepared for kitchen/bar dispatch, but not sent yet.') }}
                            </p>
                        @endif

                        @if (data_get($table, 'draft.order_ticket_items'))
                            <div class="mt-4 space-y-2">
                                <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('Kitchen/bar positions') }}</p>

                                @foreach (data_get($table, 'draft.order_ticket_items', []) as $ticketItem)
                                    <article wire:key="waiter-ticket-item-{{ $ticketItem['id'] }}" class="rounded-md border border-zinc-200 p-3 dark:border-zinc-800">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="font-medium text-zinc-950 dark:text-white">
                                                    {{ $ticketItem['quantity'] }} x {{ $ticketItem['item_name'] }}
                                                </p>
                                                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                                    {{ $ticketItem['department_name'] }}
                                                    @if ($ticketItem['guest_name'])
                                                        · {{ $ticketItem['guest_name'] }}
                                                    @endif
                                                </p>
                                            </div>

                                            <flux:badge :color="$ticketItem['status_color']">
                                                {{ $ticketItem['is_served'] ? __('Served') : __($ticketItem['status_label']) }}
                                            </flux:badge>
                                        </div>

                                        @if ($ticketItem['modifiers'] !== [])
                                            <div class="mt-2 flex flex-wrap gap-2">
                                                @foreach ($ticketItem['modifiers'] as $modifier)
                                                    <flux:badge wire:key="waiter-ticket-item-{{ $ticketItem['id'] }}-modifier-{{ $loop->index }}" color="zinc">
                                                        {{ $modifier['label'] }}
                                                    </flux:badge>
                                                @endforeach
                                            </div>
                                        @endif

                                        @if ($ticketItem['comment'])
                                            <p class="mt-2 text-xs text-zinc-600 dark:text-zinc-300">
                                                {{ __('Comment') }}: {{ $ticketItem['comment'] }}
                                            </p>
                                        @endif

                                        @if ($ticketItem['is_served'])
                                            <p class="mt-2 text-xs text-sky-700 dark:text-sky-300">
                                                {{ __('Served at') }}: {{ $ticketItem['served_at'] ?? __('time not set') }}
                                            </p>
                                        @elseif ($ticketItem['is_ready'])
                                            <flux:button
                                                size="sm"
                                                icon="check"
                                                type="button"
                                                class="mt-3 w-full"
                                                wire:click="markTicketItemServed({{ $ticketItem['id'] }})"
                                                wire:loading.attr="disabled"
                                                wire:target="markTicketItemServed({{ $ticketItem['id'] }})"
                                            >
                                                <span wire:loading.remove wire:target="markTicketItemServed({{ $ticketItem['id'] }})">{{ __('Mark served') }}</span>
                                                <span wire:loading wire:target="markTicketItemServed({{ $ticketItem['id'] }})">{{ __('Saving') }}</span>
                                            </flux:button>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                        @endif

                        @if (data_get($table, 'draft.can_send_to_kitchen'))
                            <flux:button
                                icon="arrow-right"
                                variant="primary"
                                type="button"
                                class="mt-3 w-full"
                                wire:click="sendOrderToKitchenBar"
                                wire:loading.attr="disabled"
                                wire:target="sendOrderToKitchenBar"
                            >
                                <span wire:loading.remove wire:target="sendOrderToKitchenBar">{{ __('Send to kitchen/bar') }}</span>
                                <span wire:loading wire:target="sendOrderToKitchenBar">{{ __('Sending') }}</span>
                            </flux:button>
                        @endif
                    </div>
                @endif
            </div>
        </aside>
    </section>

    @if ($editingItemId !== null)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-zinc-950/50 px-3 py-0 sm:items-center sm:py-6">
            <div class="max-h-[92dvh] w-full max-w-lg overflow-y-auto rounded-t-2xl bg-white p-4 shadow-2xl dark:bg-zinc-950 sm:rounded-2xl">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-medium uppercase text-emerald-700 dark:text-emerald-300">{{ __('Waiter edit') }}</p>
                        <h3 class="mt-1 text-lg font-semibold leading-tight text-zinc-950 dark:text-white">{{ $editingItemName }}</h3>
                        <p class="mt-1 text-sm font-semibold text-zinc-700 dark:text-zinc-200">{{ $editingItemTotal }} {{ data_get($table, 'branch.currency', 'EUR') }}</p>
                    </div>

                    <button
                        type="button"
                        wire:click="closeEditDraftItem"
                        class="inline-flex size-10 shrink-0 items-center justify-center rounded-full border border-zinc-200 text-xl leading-none text-zinc-600 transition hover:bg-zinc-50 focus:outline-hidden focus:ring-2 focus:ring-zinc-500 dark:border-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-900"
                        aria-label="{{ __('Close') }}"
                    >
                        x
                    </button>
                </div>

                <div class="mt-4 space-y-4">
                    <flux:input
                        wire:model.live="editingQuantity"
                        :label="__('Quantity')"
                        type="number"
                        min="1"
                        max="99"
                    />

                    @error('editingQuantity')
                        <p class="text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror

                    @forelse ($editingModifierGroups as $modifierGroup)
                        <fieldset wire:key="waiter-edit-modifier-group-{{ $modifierGroup['id'] }}" class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                            <legend class="px-1 text-sm font-semibold text-zinc-950 dark:text-white">{{ $modifierGroup['name'] }}</legend>

                            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                <span>{{ $modifierGroup['is_required'] ? __('Required') : __('Optional') }}</span>
                                <span>{{ __('Choose') }} {{ $modifierGroup['min_select'] }}-{{ $modifierGroup['max_select'] }}</span>
                            </div>

                            <div class="mt-3 grid gap-2">
                                @foreach ($modifierGroup['options'] as $modifierOption)
                                    @php($isSelected = in_array($modifierOption['id'], $editingModifierOptions[(string) $modifierGroup['id']] ?? [], true))
                                    <button
                                        type="button"
                                        wire:key="waiter-edit-modifier-option-{{ $modifierOption['id'] }}"
                                        wire:click="toggleEditingModifierOption({{ $modifierGroup['id'] }}, {{ $modifierOption['id'] }})"
                                        @class([
                                            'flex min-h-12 w-full items-center justify-between gap-3 rounded-lg border px-3 py-2 text-left text-sm transition focus:outline-hidden focus:ring-2 focus:ring-emerald-500/30',
                                            'border-emerald-500 bg-emerald-50 text-emerald-950 dark:border-emerald-500 dark:bg-emerald-950/40 dark:text-emerald-50' => $isSelected,
                                            'border-zinc-200 bg-white text-zinc-800 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800' => ! $isSelected,
                                        ])
                                    >
                                        <span class="font-medium">{{ $modifierOption['name'] }}</span>
                                        <span class="shrink-0 font-semibold">
                                            {{ ((float) $modifierOption['price_delta']) >= 0 ? '+' : '' }}{{ $modifierOption['price_delta'] }} {{ data_get($table, 'branch.currency', 'EUR') }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>

                            @error('selectedModifierOptions.'.$modifierGroup['id'])
                                <p class="mt-2 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </fieldset>
                    @empty
                        <p class="rounded-lg bg-zinc-50 px-3 py-3 text-sm text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">
                            {{ __('No modifiers are available for this position.') }}
                        </p>
                    @endforelse

                    <label class="grid gap-1 text-sm">
                        <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ __('Comment') }}</span>
                        <textarea
                            wire:model="editingComment"
                            rows="3"
                            maxlength="500"
                            class="rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-emerald-500 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100"
                        ></textarea>
                    </label>

                    @error('editingComment')
                        <p class="text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sticky bottom-0 -mx-4 mt-5 grid gap-2 border-t border-zinc-200 bg-white px-4 pt-3 dark:border-zinc-800 dark:bg-zinc-950">
                    <flux:button
                        icon="check"
                        variant="primary"
                        type="button"
                        class="w-full"
                        wire:click="updateDraftItem"
                        wire:loading.attr="disabled"
                        wire:target="updateDraftItem"
                    >
                        <span wire:loading.remove wire:target="updateDraftItem">{{ __('Save') }} · {{ $editingItemTotal }} {{ data_get($table, 'branch.currency', 'EUR') }}</span>
                        <span wire:loading wire:target="updateDraftItem">{{ __('Saving') }}</span>
                    </flux:button>

                    <flux:button
                        icon="trash"
                        variant="danger"
                        type="button"
                        class="w-full"
                        wire:click="deleteDraftItem({{ $editingItemId }})"
                        wire:loading.attr="disabled"
                        wire:target="deleteDraftItem({{ $editingItemId }})"
                    >
                        {{ __('Delete position') }}
                    </flux:button>
                </div>
            </div>
        </div>
    @endif
</section>
