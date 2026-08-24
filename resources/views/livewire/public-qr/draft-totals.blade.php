<section
    data-component="guest-draft-totals"
    wire:poll.visible.{{ $pollingIntervalSeconds }}s="refreshTotals"
    class="overflow-hidden rounded-card border border-border-subtle bg-surface"
>
    <div class="p-4">
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-xs font-medium uppercase text-emerald-700 dark:text-emerald-300">{{ __('guest.cart.table_total') }}</p>
            <h2 class="mt-1 text-lg font-semibold leading-tight text-zinc-950 dark:text-white">{{ __('guest.table.ready_and_total') }}</h2>
        </div>

        <div class="flex shrink-0 flex-col items-end gap-2">
            <x-ui.status-badge tone="muted">
                {{ trans_choice('guest.cart.item_count', $itemCount, ['count' => $itemCount]) }}
            </x-ui.status-badge>

            @if ($canToggleReadyStatus)
                <x-ui.status-badge :tone="$currentGuestReady ? 'success' : 'warning'">
                    {{ $currentGuestReady ? __('guest.table.ready') : __('guest.table.not_ready') }}
                </x-ui.status-badge>
            @endif
        </div>
    </div>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-950/60">
        <span class="text-sm font-medium text-zinc-700 dark:text-zinc-200">
            {{ __('guest.table.ready_count') }}: {{ $readyGuestCount }}/{{ $activeGuestCount }}
        </span>
        <x-ui.status-badge :tone="$allGuestsReady ? 'success' : 'warning'">
            {{ $allGuestsReady ? __('guest.table.all_ready') : __('guest.table.not_all_ready') }}
        </x-ui.status-badge>
    </div>

    @if ($feedbackMessage)
        <x-ui.alert tone="success" class="mt-4">
            {{ $feedbackMessage }}
        </x-ui.alert>
    @endif

    @error('ready_status')
        <x-ui.alert tone="danger" class="mt-4">{{ $message }}</x-ui.alert>
    @enderror

    @error('send_draft')
        <x-ui.alert tone="danger" class="mt-4">{{ $message }}</x-ui.alert>
    @enderror

    @error('bill_request')
        <x-ui.alert tone="danger" class="mt-4">{{ $message }}</x-ui.alert>
    @enderror

    @if (! $branchCanAcceptOrders)
        <x-ui.alert tone="warning" class="mt-4" :heading="__('guest.table.closed_title')">
            <x-ui.plain-text :text="$branchOpeningStatusMessage ?: __('guest.table.closed_description')" />
        </x-ui.alert>
    @endif

    <div class="mt-4 space-y-3">
        <section class="space-y-2">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('guest.cart.guest_total') }}</h3>
                <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('guest.table.sorted_by_name') }}</span>
            </div>

            @forelse ($guestTotals as $guestTotal)
                <div wire:key="draft-total-guest-{{ $guestTotal['guest_id'] }}" class="flex items-center justify-between gap-3 rounded-lg border border-zinc-100 bg-zinc-50 px-3 py-3 dark:border-zinc-800 dark:bg-zinc-950/60">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-white text-sm font-semibold text-emerald-800 shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-emerald-100 dark:ring-zinc-800">
                            {{ str($guestTotal['guest_name'])->substr(0, 1)->upper() }}
                        </div>

                        <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.plain-text :text="$guestTotal['guest_name']" class="block text-sm font-semibold text-zinc-950 dark:text-white" :preserve-lines="false" />

                            @if ($guestTotal['is_current_guest'])
                                <x-ui.status-badge tone="success">
                                    {{ __('guest.table.you') }}
                                </x-ui.status-badge>
                            @endif
                        </div>

                        @if ($guestTotal['has_confirmed_total'])
                            <p class="mt-1 text-xs leading-5 text-zinc-500 dark:text-zinc-400">
                                {{ __('guest.cart.confirmed_total') }}: {{ $guestTotal['confirmed_total'] }} {{ $currency }}
                                @if ($guestTotal['has_draft_total'])
                                    <span>{{ __('guest.cart.separator') }} {{ __('guest.cart.current_draft') }}: {{ $guestTotal['draft_total'] }} {{ $currency }}</span>
                                @endif
                            </p>
                        @endif
                        </div>
                    </div>

                    <div class="shrink-0 text-right">
                        <p class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $guestTotal['total'] }} {{ $currency }}</p>
                        <x-ui.status-badge :tone="$guestTotal['is_ready'] ? 'success' : 'muted'" class="mt-1">
                            {{ $guestTotal['is_ready'] ? __('guest.table.ready') : __('guest.table.not_ready') }}
                        </x-ui.status-badge>
                    </div>
                </div>
            @empty
                <x-ui.empty-state
                    icon="shopping-cart"
                    :heading="__('guest.cart.empty')"
                />
            @endforelse
        </section>

        <div class="flex items-center justify-between border-t border-zinc-200 pt-3 dark:border-zinc-800">
            <span class="text-sm font-medium text-zinc-600 dark:text-zinc-300">
                {{ $hasConfirmedOrders ? __('guest.cart.current_draft') : __('guest.cart.table_total') }}
            </span>
            <span class="text-xl font-semibold text-zinc-950 dark:text-white">
                {{ $currentDraftTotalAmount }} {{ $currency }}
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

        @if ($canToggleReadyStatus || $billRequested || $canRequestBill || $canSendDraftToWaiter)
        <x-ui.mobile-bottom-actions data-guest-cart-actions :summary="__('guest.cart.table_total').': '.$tableTotalAmount.' '.$currency">
            @if ($canToggleReadyStatus)
                <x-ui.button
                    type="button"
                    wire:click="toggleReadyStatus"
                    wire:loading.attr="disabled"
                    wire:target="toggleReadyStatus"
                    :variant="$currentGuestReady ? 'secondary' : 'primary'"
                    size="lg"
                    full-width
                >
                    <span wire:loading.remove wire:target="toggleReadyStatus">
                        {{ $currentGuestReady ? __('guest.table.cancel_ready') : __('guest.table.mark_ready') }}
                    </span>
                    <span wire:loading wire:target="toggleReadyStatus">{{ __('guest.table.saving') }}</span>
                </x-ui.button>
            @endif

            @if ($billRequested)
                <x-ui.alert tone="info">
                    {{ __('guest.table.bill_requested') }}
                    <span class="mt-1 block font-normal">{{ __('guest.cart.table_total') }}: {{ $tableTotalAmount }} {{ $currency }}</span>
                </x-ui.alert>
            @elseif ($canRequestBill)
                <x-ui.button
                    type="button"
                    wire:click="requestBill"
                    wire:loading.attr="disabled"
                    wire:target="requestBill"
                    variant="info"
                    size="lg"
                    full-width
                >
                    <span wire:loading.remove wire:target="requestBill">{{ __('guest.table.request_bill') }} · {{ $tableTotalAmount }} {{ $currency }}</span>
                    <span wire:loading wire:target="requestBill">{{ __('guest.table.sending') }}</span>
                </x-ui.button>
            @endif

            @if ($canSendDraftToWaiter)
                @if ($sendNeedsReadyConfirmation)
                    <x-ui.alert tone="warning" :heading="__('guest.table.not_all_ready_title')">
                        <p>
                            {{ __('guest.table.not_all_ready_description') }}
                        </p>

                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            <x-ui.button
                                type="button"
                                wire:click="sendDraftToWaiter(true)"
                                wire:loading.attr="disabled"
                                wire:target="sendDraftToWaiter"
                                variant="warning"
                            >
                                <span wire:loading.remove wire:target="sendDraftToWaiter">{{ __('guest.table.send_anyway') }}</span>
                                <span wire:loading wire:target="sendDraftToWaiter">{{ __('guest.table.sending') }}</span>
                            </x-ui.button>

                            <x-ui.button
                                type="button"
                                wire:click="cancelSendDraftConfirmation"
                                variant="secondary"
                            >
                                {{ __('guest.table.wait_for_guests') }}
                            </x-ui.button>
                        </div>
                    </x-ui.alert>
                @else
                    <x-ui.button
                        type="button"
                        wire:click="sendDraftToWaiter"
                        wire:loading.attr="disabled"
                        wire:target="sendDraftToWaiter"
                        variant="primary"
                        size="lg"
                        full-width
                    >
                        <span wire:loading.remove wire:target="sendDraftToWaiter">{{ __('guest.table.send_to_waiter') }}</span>
                        <span wire:loading wire:target="sendDraftToWaiter">{{ __('guest.table.sending') }}</span>
                    </x-ui.button>
                @endif
            @endif
        </x-ui.mobile-bottom-actions>
        @endif
    </div>
    </div>
</section>
