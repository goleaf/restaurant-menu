<section
    data-component="guest-draft-order"
    wire:poll.visible.{{ $pollingIntervalSeconds }}s="refreshDraft"
    class="overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
>
    <div class="border-b border-zinc-100 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-xs font-medium uppercase text-emerald-700 dark:text-emerald-300">{{ __('guest.cart.table_cart') }}</p>
            <h2 class="mt-1 text-xl font-semibold leading-tight text-zinc-950 dark:text-white">{{ __('guest.cart.title') }}</h2>
        </div>

        <div class="flex shrink-0 flex-col items-end gap-2">
            <x-ui.status-badge tone="muted" size="lg">
                {{ trans_choice('guest.cart.item_count', $itemCount, ['count' => $itemCount]) }}
            </x-ui.status-badge>

            @if ($showControls && $canToggleReadyStatus)
                <x-ui.button
                    type="button"
                    wire:click="toggleReadyStatus"
                    wire:loading.attr="disabled"
                    wire:target="toggleReadyStatus"
                    :variant="$currentGuestReady ? 'secondary' : 'primary'"
                    size="sm"
                >
                    <span wire:loading.remove wire:target="toggleReadyStatus">
                        {{ $currentGuestReady ? __('guest.table.cancel_ready') : __('guest.table.mark_ready') }}
                    </span>
                    <span wire:loading wire:target="toggleReadyStatus">{{ __('guest.table.saving') }}</span>
                </x-ui.button>
            @endif
        </div>
    </div>
    </div>

    <div class="p-4">
    @if ($showControls)
        <div class="mt-4 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-950/60">
            <span class="text-sm font-medium text-zinc-700 dark:text-zinc-200">
                {{ __('guest.table.ready_count') }}: {{ $readyGuestCount }}/{{ $activeGuestCount }}
            </span>
            <x-ui.status-badge :tone="$allGuestsReady ? 'success' : 'warning'">
                {{ $allGuestsReady ? __('guest.table.all_ready') : __('guest.table.not_all_ready') }}
            </x-ui.status-badge>
        </div>
    @endif

    @if ($showStatuses)
        @if ($draftStatusValue === 'rejected')
            <p class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-800 dark:bg-red-950/40 dark:text-red-100">
                {{ __('guest.table.draft_rejected_message') }}
                @if ($rejectionReason)
                    <span class="block pt-1 font-normal">
                        {{ __('guest.table.reason') }}:
                        <x-ui.plain-text :text="$rejectionReason" class="inline" />
                    </span>
                @endif
            </p>
        @elseif ($serviceStatusValue !== '')
            <p @class([
                'mt-4 rounded-lg px-3 py-2 text-sm font-medium',
                'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100' => $serviceStatusTone === 'emerald',
                'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-100' => $serviceStatusTone === 'amber',
                'bg-sky-50 text-sky-800 dark:bg-sky-950/40 dark:text-sky-100' => $serviceStatusTone === 'sky',
                'bg-zinc-50 text-zinc-700 dark:bg-zinc-950/40 dark:text-zinc-100' => $serviceStatusTone === 'zinc',
            ])>
                {{ __('guest.table.order_status') }}: {{ $serviceStatusLabel }}

                @if ($serviceStatusValue === 'accepted' && $orderStatusValue === 'sent_to_kitchen_bar')
                    <span class="block pt-1 font-normal">{{ __('guest.statuses.service.accepted_description') }}</span>
                @endif
            </p>
        @elseif ($draftStatusValue === 'converted_to_order')
            <p class="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
                {{ __('guest.statuses.draft.converted_description') }}
            </p>
        @elseif (! $canEditDraft)
            <p class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
                {{ __('guest.cart.draft_sent_locked') }}
            </p>
        @endif
    @endif

    @if ($feedbackMessage)
        <p class="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
            {{ $feedbackMessage }}
        </p>
    @endif

    @error('draft_item')
        <p class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-100">{{ $message }}</p>
    @enderror

    @error('draft_order')
        <p class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-100">{{ $message }}</p>
    @enderror

    @error('ready_status')
        <p class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-100">{{ $message }}</p>
    @enderror

    @error('send_draft')
        <p class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-100">{{ $message }}</p>
    @enderror

    @error('bill_request')
        <p class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-100">{{ $message }}</p>
    @enderror

    @if (! $branchCanAcceptOrders)
        <x-ui.alert tone="warning" class="mt-4" :heading="__('guest.table.closed_title')">
            <x-ui.plain-text :text="$branchOpeningStatusMessage ?: __('guest.table.closed_description')" />
        </x-ui.alert>
    @endif

    <div class="mt-4 space-y-4">
        <section class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('guest.cart.other_guests') }}</h3>
                <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('guest.table.sorted_by_name') }}</span>
            </div>

            @forelse ($guestSections as $guestSection)
                <article
                    wire:key="draft-order-guest-section-{{ $guestSection['guest_id'] }}"
                    @class([
                        'rounded-lg border p-3 shadow-sm',
                        'border-emerald-100 bg-emerald-50/60 dark:border-emerald-900/50 dark:bg-emerald-950/20' => $guestSection['is_current_guest'],
                        'border-zinc-100 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950/60' => ! $guestSection['is_current_guest'],
                    ])
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-start gap-3">
                            <div class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-white text-base font-semibold text-emerald-900 shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-emerald-100 dark:ring-zinc-800">
                                {{ str($guestSection['guest_name'])->substr(0, 1)->upper() }}
                            </div>

                            <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-ui.plain-text :text="$guestSection['guest_name']" class="block text-base font-semibold text-zinc-950 dark:text-white" :preserve-lines="false" />

                                @if ($guestSection['is_current_guest'])
                                    <x-ui.status-badge tone="success">
                                        {{ __('guest.table.you') }}
                                    </x-ui.status-badge>
                                @endif

                                <x-ui.status-badge :tone="$guestSection['is_ready'] ? 'success' : 'muted'">
                                    {{ $guestSection['is_ready'] ? __('guest.table.ready') : __('guest.table.not_ready') }}
                                </x-ui.status-badge>
                            </div>

                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ trans_choice('guest.cart.item_count', count($guestSection['items']), ['count' => count($guestSection['items'])]) }}
                            </p>
                            </div>
                        </div>

                        <div class="shrink-0 text-right">
                            <p class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $guestSection['total'] }} {{ $currency }}</p>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('guest.cart.guest_total') }}</p>

                            @if ($guestSection['has_confirmed_total'])
                                <p class="mt-1 text-xs leading-5 text-zinc-500 dark:text-zinc-400">
                                    {{ __('guest.cart.confirmed_total') }}: {{ $guestSection['confirmed_total'] }} {{ $currency }}
                                    @if ($guestSection['has_draft_total'])
                                        <span class="block">{{ __('guest.cart.current_draft') }}: {{ $guestSection['draft_total'] }} {{ $currency }}</span>
                                    @endif
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="mt-3 space-y-2">
                        @forelse ($guestSection['items'] as $item)
                            <div wire:key="draft-order-item-{{ $item['id'] }}" class="rounded-lg border border-white/70 bg-white p-3 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <x-ui.plain-text :text="$item['item_name']" class="block text-sm font-semibold leading-5 text-zinc-950 dark:text-white" :preserve-lines="false" />

                                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ __('guest.cart.price') }}: {{ $item['total_price'] }} {{ $currency }}

                                            @if ($item['quantity'] > 1)
                                                <span>{{ __('guest.cart.separator') }} ×{{ $item['quantity'] }} {{ __('guest.cart.each') }} {{ $item['unit_total_price'] }} {{ $currency }}</span>
                                            @else
                                                <span>{{ __('guest.cart.separator') }} ×{{ $item['quantity'] }}</span>
                                            @endif
                                        </p>
                                    </div>

                                    <span class="shrink-0 text-sm font-semibold text-zinc-950 dark:text-white">
                                        {{ $item['total_price'] }} {{ $currency }}
                                    </span>
                                </div>

                                @if ($item['modifiers'] !== [])
                                    <p class="mt-2 text-xs leading-5 text-zinc-600 dark:text-zinc-300">
                                        {{ __('guest.cart.modifiers') }}: {{ implode(', ', $item['modifiers']) }}
                                    </p>
                                @endif

                                @if ($item['comment'])
                                    <p class="mt-2 rounded-md bg-zinc-50 px-2 py-1.5 text-xs leading-5 text-zinc-600 dark:bg-zinc-950/60 dark:text-zinc-300">
                                        {{ __('guest.cart.comment') }}:
                                        <x-ui.plain-text :text="$item['comment']" class="inline" />
                                    </p>
                                @endif

                                @if ($item['can_edit'])
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <x-ui.button
                                            type="button"
                                            wire:click="editItem({{ $item['id'] }})"
                                            variant="secondary"
                                            size="sm"
                                        >
                                            {{ __('guest.cart.edit_item') }}
                                        </x-ui.button>

                                        <x-ui.button
                                            type="button"
                                            wire:click="deleteItem({{ $item['id'] }})"
                                            wire:loading.attr="disabled"
                                            wire:target="deleteItem({{ $item['id'] }})"
                                            variant="danger"
                                            size="sm"
                                        >
                                            {{ __('guest.cart.remove_item') }}
                                        </x-ui.button>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="rounded-lg bg-white px-3 py-2 text-sm text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">
                                {{ __('guest.cart.empty') }}
                            </p>
                        @endforelse
                    </div>
                </article>
            @empty
                <p class="rounded-lg bg-zinc-50 px-3 py-2 text-sm text-zinc-600 dark:bg-zinc-950/60 dark:text-zinc-300">
                    {{ __('guest.table.no_guests') }}
                </p>
            @endforelse
        </section>

        @if ($showTotals)
            <div class="flex items-center justify-between border-t border-zinc-200 pt-3 dark:border-zinc-800">
                <span class="text-sm font-medium text-zinc-600 dark:text-zinc-300">
                    {{ $hasConfirmedOrders ? __('guest.cart.current_draft') : __('guest.cart.table_total') }}
                </span>
                <span class="text-xl font-semibold text-zinc-950 dark:text-white">
                    {{ $totalAmount }} {{ $currency }}
                </span>
            </div>

            @if ($hasConfirmedOrders)
                <div class="space-y-2 rounded-lg bg-emerald-50 px-3 py-3 text-sm dark:bg-emerald-950/30">
                    <div class="flex items-center justify-between gap-3">
                        <span class="font-medium text-emerald-900 dark:text-emerald-100">{{ __('guest.cart.confirmed_total') }}</span>
                        <span class="font-semibold text-emerald-950 dark:text-emerald-50">{{ $confirmedOrdersTotalAmount }} {{ $currency }}</span>
                    </div>

                    <div class="flex items-center justify-between gap-3 border-t border-emerald-100 pt-2 dark:border-emerald-900/60">
                        <span class="font-medium text-emerald-900 dark:text-emerald-100">{{ __('guest.cart.table_total') }}</span>
                        <span class="text-lg font-semibold text-emerald-950 dark:text-emerald-50">{{ $tableTotalAmount }} {{ $currency }}</span>
                    </div>
                </div>
            @endif
        @endif

        @if ($showControls)
            <div class="space-y-2 border-t border-zinc-200 pt-3 dark:border-zinc-800">
                @if ($billRequested)
                    <div class="rounded-lg bg-sky-50 px-3 py-3 text-sm font-medium text-sky-800 dark:bg-sky-950/40 dark:text-sky-100">
                        {{ __('guest.table.bill_requested') }}
                        <span class="mt-1 block font-normal">{{ __('guest.cart.table_total') }}: {{ $tableTotalAmount }} {{ $currency }}</span>
                    </div>
                @elseif ($canRequestBill)
                    <button
                        type="button"
                        wire:click="requestBill"
                        wire:loading.attr="disabled"
                        wire:target="requestBill"
                        class="flex min-h-12 w-full items-center justify-center rounded-lg bg-sky-700 px-4 text-base font-semibold text-white transition hover:bg-sky-800 focus:outline-hidden focus:ring-2 focus:ring-sky-600 focus:ring-offset-2 dark:focus:ring-offset-zinc-950"
                    >
                        <span wire:loading.remove wire:target="requestBill">{{ __('guest.table.request_bill') }} · {{ $tableTotalAmount }} {{ $currency }}</span>
                        <span wire:loading wire:target="requestBill">{{ __('guest.table.sending') }}</span>
                    </button>
                @endif
            </div>

            @if ($canSendDraftToWaiter)
                <div class="space-y-2 border-t border-zinc-200 pt-3 dark:border-zinc-800">
                    @if ($sendNeedsReadyConfirmation)
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-900/70 dark:bg-amber-950/30">
                            <p class="text-sm font-semibold text-amber-900 dark:text-amber-100">
                                {{ __('guest.table.not_all_ready_title') }}
                            </p>
                            <p class="mt-1 text-sm text-amber-800 dark:text-amber-100">
                                {{ __('guest.table.not_all_ready_description') }}
                            </p>

                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                <button
                                    type="button"
                                    wire:click="sendDraftToWaiter(true)"
                                    wire:loading.attr="disabled"
                                    wire:target="sendDraftToWaiter"
                                    class="inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-700 px-4 text-sm font-semibold text-white transition hover:bg-amber-800 focus:outline-hidden focus:ring-2 focus:ring-amber-600 focus:ring-offset-2 dark:focus:ring-offset-zinc-950"
                                >
                                    <span wire:loading.remove wire:target="sendDraftToWaiter">{{ __('guest.table.send_anyway') }}</span>
                                    <span wire:loading wire:target="sendDraftToWaiter">{{ __('guest.table.sending') }}</span>
                                </button>

                                <button
                                    type="button"
                                    wire:click="cancelSendDraftConfirmation"
                                    class="inline-flex min-h-11 items-center justify-center rounded-lg border border-amber-300 bg-white px-4 text-sm font-semibold text-amber-800 transition hover:bg-amber-50 focus:outline-hidden focus:ring-2 focus:ring-amber-500/30 dark:border-amber-900/70 dark:bg-zinc-900 dark:text-amber-200 dark:hover:bg-amber-950/30"
                                >
                                    {{ __('guest.table.wait_for_guests') }}
                                </button>
                            </div>
                        </div>
                    @else
                        <button
                            type="button"
                            wire:click="sendDraftToWaiter"
                            wire:loading.attr="disabled"
                            wire:target="sendDraftToWaiter"
                            class="flex min-h-12 w-full items-center justify-center rounded-lg bg-emerald-700 px-4 text-base font-semibold text-white transition hover:bg-emerald-800 focus:outline-hidden focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2 dark:focus:ring-offset-zinc-950"
                        >
                            <span wire:loading.remove wire:target="sendDraftToWaiter">{{ __('guest.table.send_to_waiter') }}</span>
                            <span wire:loading wire:target="sendDraftToWaiter">{{ __('guest.table.sending') }}</span>
                        </button>
                    @endif
                </div>
            @endif
        @endif
    </div>
    </div>

    @if ($editingItemId !== null)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-zinc-950/50 px-3 py-0 sm:items-center sm:py-6">
            <div class="max-h-[92dvh] w-full max-w-lg overflow-y-auto rounded-t-2xl bg-white p-4 shadow-2xl dark:bg-zinc-950 sm:rounded-2xl">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-medium uppercase text-emerald-700 dark:text-emerald-300">{{ __('guest.cart.my_items') }}</p>
                        <h3 class="mt-1 text-lg font-semibold leading-tight text-zinc-950 dark:text-white">{{ $editingItemName }}</h3>
                        <p class="mt-1 text-sm font-semibold text-zinc-700 dark:text-zinc-200">{{ $editingItemTotal }} {{ $currency }}</p>
                    </div>

                    <button
                        type="button"
                        wire:click="closeEditItem"
                        class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg border border-zinc-200 text-zinc-600 transition hover:bg-zinc-50 focus:outline-hidden focus:ring-2 focus:ring-zinc-500 dark:border-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-900"
                        aria-label="{{ __('guest.table.close') }}"
                    >
                        <flux:icon name="x-mark" variant="micro" class="size-4" />
                    </button>
                </div>

                <div class="mt-4 space-y-4">
                    <label class="grid gap-1 text-sm">
                        <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ __('guest.cart.quantity') }}</span>
                        <input
                            type="number"
                            min="1"
                            max="99"
                            wire:model.live="editingQuantity"
                            class="h-11 rounded-lg border border-zinc-200 bg-white px-3 text-sm text-zinc-900 shadow-sm focus:border-emerald-500 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100"
                        >
                        @error('editingQuantity')
                            <span class="text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</span>
                        @enderror
                    </label>

                    @forelse ($editingModifierGroups as $modifierGroup)
                        <fieldset wire:key="draft-order-edit-group-{{ $modifierGroup['id'] }}" class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                            <legend class="px-1 text-sm font-semibold text-zinc-950 dark:text-white">
                                {{ $modifierGroup['name'] }}
                            </legend>

                            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                @if ($modifierGroup['is_required'])
                                    <span>{{ __('guest.cart.required') }}</span>
                                @else
                                    <span>{{ __('guest.cart.optional') }}</span>
                                @endif

                                <span>{{ __('guest.cart.can_choose') }} {{ $modifierGroup['min_select'] }}–{{ $modifierGroup['max_select'] }}</span>
                            </div>

                            <div class="mt-3 grid gap-2">
                                @forelse ($modifierGroup['options'] as $modifierOption)
                                    @php($isSelected = in_array($modifierOption['id'], $editingModifierOptions[(string) $modifierGroup['id']] ?? [], true))
                                    <button
                                        type="button"
                                        wire:key="draft-order-edit-option-{{ $modifierOption['id'] }}"
                                        wire:click="toggleEditingModifierOption({{ $modifierGroup['id'] }}, {{ $modifierOption['id'] }})"
                                        @class([
                                            'flex min-h-12 w-full items-center justify-between gap-3 rounded-lg border px-3 py-2 text-left text-sm transition focus:outline-hidden focus:ring-2 focus:ring-emerald-500/30',
                                            'border-emerald-500 bg-emerald-50 text-emerald-950 dark:border-emerald-500 dark:bg-emerald-950/40 dark:text-emerald-50' => $isSelected,
                                            'border-zinc-200 bg-white text-zinc-800 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800' => ! $isSelected,
                                        ])
                                    >
                                        <span class="font-medium">{{ $modifierOption['name'] }}</span>
                                        <span class="shrink-0 font-semibold">
                                            {{ ((float) $modifierOption['price_delta']) >= 0 ? '+' : '' }}{{ $modifierOption['price_delta'] }} {{ $currency }}
                                        </span>
                                    </button>
                                @empty
                                    <p class="rounded-lg bg-zinc-50 px-3 py-2 text-sm text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">
                                        {{ __('guest.cart.no_options') }}
                                    </p>
                                @endforelse
                            </div>

                            @error('selectedModifierOptions.'.$modifierGroup['id'])
                                <p class="mt-2 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </fieldset>
                    @empty
                        <p class="rounded-lg bg-zinc-50 px-3 py-3 text-sm text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">
                            {{ __('guest.cart.no_item_options') }}
                        </p>
                    @endforelse

                    <label class="grid gap-1 text-sm">
                        <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ __('guest.cart.comment') }}</span>
                        <textarea
                            wire:model="editingComment"
                            rows="3"
                            maxlength="500"
                            class="rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-emerald-500 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100"
                        ></textarea>
                        @error('editingComment')
                            <span class="text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</span>
                        @enderror
                    </label>
                </div>

                <x-ui.mobile-bottom-actions class="mt-5" :summary="$editingItemTotal.' '.$currency">
                    <x-ui.button
                        type="button"
                        wire:click="updateItem"
                        wire:loading.attr="disabled"
                        wire:target="updateItem"
                        variant="primary"
                        size="lg"
                        full-width
                    >
                        <span wire:loading.remove wire:target="updateItem">{{ __('guest.cart.save_item') }} · {{ $editingItemTotal }} {{ $currency }}</span>
                        <span wire:loading wire:target="updateItem">{{ __('guest.table.saving') }}</span>
                    </x-ui.button>

                    <x-ui.button
                        type="button"
                        wire:click="deleteItem({{ $editingItemId }})"
                        wire:loading.attr="disabled"
                        wire:target="deleteItem({{ $editingItemId }})"
                        variant="danger"
                        full-width
                    >
                        {{ __('guest.cart.remove_item') }}
                    </x-ui.button>
                </x-ui.mobile-bottom-actions>
            </div>
        </div>
    @endif
</section>
