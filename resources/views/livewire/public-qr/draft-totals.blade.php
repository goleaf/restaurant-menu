<section
    data-component="guest-draft-totals"
    wire:poll.visible.{{ $pollingIntervalSeconds }}s="refreshTotals"
    class="overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
>
    <div class="p-4">
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-xs font-medium uppercase text-emerald-700 dark:text-emerald-300">{{ __('Итоги') }}</p>
            <h2 class="mt-1 text-lg font-semibold leading-tight text-zinc-950 dark:text-white">{{ __('Готовность и сумма') }}</h2>
        </div>

        <div class="flex shrink-0 flex-col items-end gap-2">
            <x-ui.status-badge tone="muted">
                {{ trans_choice(':count позиция|:count позиции|:count позиций', $itemCount, ['count' => $itemCount]) }}
            </x-ui.status-badge>

            @if ($canToggleReadyStatus)
                <x-ui.status-badge :tone="$currentGuestReady ? 'success' : 'warning'">
                    {{ $currentGuestReady ? __('Вы готовы') : __('Ждём готовность') }}
                </x-ui.status-badge>
            @endif
        </div>
    </div>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-950/60">
        <span class="text-sm font-medium text-zinc-700 dark:text-zinc-200">
            {{ __('Готовы') }}: {{ $readyGuestCount }}/{{ $activeGuestCount }}
        </span>
        <x-ui.status-badge :tone="$allGuestsReady ? 'success' : 'warning'">
            {{ $allGuestsReady ? __('Все готовы') : __('Не все готовы') }}
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
        <x-ui.alert tone="warning" class="mt-4" :heading="__('Сейчас закрыто')">
            {{ $branchOpeningStatusMessage ?: __('Заказы принимаем в часы работы ресторана.') }}
        </x-ui.alert>
    @endif

    <div class="mt-4 space-y-3">
        <section class="space-y-2">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('Суммы гостей') }}</h3>
                <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('По алфавиту') }}</span>
            </div>

            @forelse ($guestTotals as $guestTotal)
                <div wire:key="draft-total-guest-{{ $guestTotal['guest_id'] }}" class="flex items-center justify-between gap-3 rounded-lg border border-zinc-100 bg-zinc-50 px-3 py-3 dark:border-zinc-800 dark:bg-zinc-950/60">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-white text-sm font-semibold text-emerald-800 shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-emerald-100 dark:ring-zinc-800">
                            {{ str($guestTotal['guest_name'])->substr(0, 1)->upper() }}
                        </div>

                        <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate text-sm font-semibold text-zinc-950 dark:text-white">{{ $guestTotal['guest_name'] }}</p>

                            @if ($guestTotal['is_current_guest'])
                                <x-ui.status-badge tone="success">
                                    {{ __('Вы') }}
                                </x-ui.status-badge>
                            @endif
                        </div>

                        @if ($guestTotal['has_confirmed_total'])
                            <p class="mt-1 text-xs leading-5 text-zinc-500 dark:text-zinc-400">
                                {{ __('Принято') }}: {{ $guestTotal['confirmed_total'] }} {{ $currency }}
                                @if ($guestTotal['has_draft_total'])
                                    <span>{{ __('· Сейчас') }}: {{ $guestTotal['draft_total'] }} {{ $currency }}</span>
                                @endif
                            </p>
                        @endif
                        </div>
                    </div>

                    <div class="shrink-0 text-right">
                        <p class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $guestTotal['total'] }} {{ $currency }}</p>
                        <x-ui.status-badge :tone="$guestTotal['is_ready'] ? 'success' : 'muted'" class="mt-1">
                            {{ $guestTotal['is_ready'] ? __('Готов') : __('Не готов') }}
                        </x-ui.status-badge>
                    </div>
                </div>
            @empty
                <x-ui.empty-state
                    icon="shopping-cart"
                    :heading="__('Суммы появятся после добавления позиций.')"
                />
            @endforelse
        </section>

        <div class="flex items-center justify-between border-t border-zinc-200 pt-3 dark:border-zinc-800">
            <span class="text-sm font-medium text-zinc-600 dark:text-zinc-300">
                {{ $hasConfirmedOrders ? __('Текущий черновик') : __('Общая сумма') }}
            </span>
            <span class="text-xl font-semibold text-zinc-950 dark:text-white">
                {{ $currentDraftTotalAmount }} {{ $currency }}
            </span>
        </div>

        @if ($hasConfirmedOrders)
            <div class="space-y-2 rounded-lg bg-emerald-50 px-3 py-3 text-sm dark:bg-emerald-950/30">
                <div class="flex items-center justify-between gap-3">
                    <span class="font-medium text-emerald-900 dark:text-emerald-100">{{ __('Уже подтверждено') }}</span>
                    <span class="font-semibold text-emerald-950 dark:text-emerald-50">{{ $confirmedOrdersTotalAmount }} {{ $currency }}</span>
                </div>

                <div class="flex items-center justify-between gap-3 border-t border-emerald-100 pt-2 dark:border-emerald-900/60">
                    <span class="font-medium text-emerald-900 dark:text-emerald-100">{{ __('Итого за стол') }}</span>
                    <span class="text-lg font-semibold text-emerald-950 dark:text-emerald-50">{{ $tableTotalAmount }} {{ $currency }}</span>
                </div>
            </div>
        @endif

        @if ($canToggleReadyStatus || $billRequested || $canRequestBill || $canSendDraftToWaiter)
        <x-ui.mobile-bottom-actions :summary="__('Итого за стол').': '.$tableTotalAmount.' '.$currency">
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
                        {{ $currentGuestReady ? __('Снять готовность') : __('Я готов') }}
                    </span>
                    <span wire:loading wire:target="toggleReadyStatus">{{ __('Сохраняем') }}</span>
                </x-ui.button>
            @endif

            @if ($billRequested)
                <x-ui.alert tone="info">
                    {{ __('Счёт запрошен. Официант скоро подойдёт.') }}
                    <span class="mt-1 block font-normal">{{ __('Итого за стол') }}: {{ $tableTotalAmount }} {{ $currency }}</span>
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
                    <span wire:loading.remove wire:target="requestBill">{{ __('Попросить счёт') }} · {{ $tableTotalAmount }} {{ $currency }}</span>
                    <span wire:loading wire:target="requestBill">{{ __('Отправляем') }}</span>
                </x-ui.button>
            @endif

            @if ($canSendDraftToWaiter)
                @if ($sendNeedsReadyConfirmation)
                    <x-ui.alert tone="warning" :heading="__('Не все гости отметили готовность.')">
                        <p>
                            {{ __('Можно отправить сейчас, но официант всё равно должен подтвердить заказ перед кухней или баром.') }}
                        </p>

                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            <x-ui.button
                                type="button"
                                wire:click="sendDraftToWaiter(true)"
                                wire:loading.attr="disabled"
                                wire:target="sendDraftToWaiter"
                                variant="warning"
                            >
                                <span wire:loading.remove wire:target="sendDraftToWaiter">{{ __('Отправить всё равно') }}</span>
                                <span wire:loading wire:target="sendDraftToWaiter">{{ __('Отправляем') }}</span>
                            </x-ui.button>

                            <x-ui.button
                                type="button"
                                wire:click="cancelSendDraftConfirmation"
                                variant="secondary"
                            >
                                {{ __('Подождать гостей') }}
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
                        <span wire:loading.remove wire:target="sendDraftToWaiter">{{ __('Отправить официанту') }}</span>
                        <span wire:loading wire:target="sendDraftToWaiter">{{ __('Отправляем') }}</span>
                    </x-ui.button>
                @endif
            @endif
        </x-ui.mobile-bottom-actions>
        @endif
    </div>
    </div>
</section>
