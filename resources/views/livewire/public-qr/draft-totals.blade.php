<section
    data-component="guest-draft-totals"
    wire:poll.visible.{{ $pollingIntervalSeconds }}s="refreshTotals"
    class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
>
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-xs font-medium uppercase text-emerald-700 dark:text-emerald-300">{{ __('Итоги') }}</p>
            <h2 class="mt-1 text-lg font-semibold leading-tight text-zinc-950 dark:text-white">{{ __('Готовность и сумма') }}</h2>
        </div>

        <div class="flex shrink-0 flex-col items-end gap-2">
            <span class="rounded-md bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                {{ trans_choice(':count позиция|:count позиции|:count позиций', $itemCount, ['count' => $itemCount]) }}
            </span>

            @if ($canToggleReadyStatus)
                <button
                    type="button"
                    wire:click="toggleReadyStatus"
                    wire:loading.attr="disabled"
                    wire:target="toggleReadyStatus"
                    @class([
                        'inline-flex min-h-10 items-center justify-center rounded-lg px-3 text-sm font-semibold transition focus:outline-hidden focus:ring-2 focus:ring-emerald-500/30',
                        'bg-emerald-700 text-white hover:bg-emerald-800' => ! $currentGuestReady,
                        'border border-emerald-200 bg-white text-emerald-800 hover:bg-emerald-50 dark:border-emerald-900/70 dark:bg-zinc-900 dark:text-emerald-200 dark:hover:bg-emerald-950/30' => $currentGuestReady,
                    ])
                >
                    <span wire:loading.remove wire:target="toggleReadyStatus">
                        {{ $currentGuestReady ? __('Снять готовность') : __('Я готов') }}
                    </span>
                    <span wire:loading wire:target="toggleReadyStatus">{{ __('Сохраняем') }}</span>
                </button>
            @endif
        </div>
    </div>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-950/60">
        <span class="text-sm font-medium text-zinc-700 dark:text-zinc-200">
            {{ __('Готовы') }}: {{ $readyGuestCount }}/{{ $activeGuestCount }}
        </span>
        <span @class([
            'rounded-md px-2 py-0.5 text-xs font-semibold',
            'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-100' => $allGuestsReady,
            'bg-amber-50 text-amber-800 dark:bg-amber-950/50 dark:text-amber-100' => ! $allGuestsReady,
        ])>
            {{ $allGuestsReady ? __('Все готовы') : __('Не все готовы') }}
        </span>
    </div>

    @if ($feedbackMessage)
        <p class="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
            {{ $feedbackMessage }}
        </p>
    @endif

    @error('ready_status')
        <p class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-100">{{ $message }}</p>
    @enderror

    @error('send_draft')
        <p class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-100">{{ $message }}</p>
    @enderror

    @error('bill_request')
        <p class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/40 dark:text-red-100">{{ $message }}</p>
    @enderror

    <div class="mt-4 space-y-3">
        <section class="space-y-2">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('Суммы гостей') }}</h3>
                <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('По алфавиту') }}</span>
            </div>

            @forelse ($guestTotals as $guestTotal)
                <div wire:key="draft-total-guest-{{ $guestTotal['guest_id'] }}" class="flex items-center justify-between gap-3 rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-950/60">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate text-sm font-semibold text-zinc-950 dark:text-white">{{ $guestTotal['guest_name'] }}</p>

                            @if ($guestTotal['is_current_guest'])
                                <span class="rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-100">
                                    {{ __('Вы') }}
                                </span>
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

                    <p class="shrink-0 text-sm font-semibold text-zinc-950 dark:text-white">{{ $guestTotal['total'] }} {{ $currency }}</p>
                </div>
            @empty
                <p class="rounded-lg bg-zinc-50 px-3 py-2 text-sm text-zinc-600 dark:bg-zinc-950 dark:text-zinc-300">
                    {{ __('Суммы появятся после добавления позиций.') }}
                </p>
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

        <div class="space-y-2 border-t border-zinc-200 pt-3 dark:border-zinc-800">
            @if ($billRequested)
                <div class="rounded-lg bg-sky-50 px-3 py-3 text-sm font-medium text-sky-800 dark:bg-sky-950/40 dark:text-sky-100">
                    {{ __('Счёт запрошен. Официант скоро подойдёт.') }}
                    <span class="mt-1 block font-normal">{{ __('Итого за стол') }}: {{ $tableTotalAmount }} {{ $currency }}</span>
                </div>
            @elseif ($canRequestBill)
                <button
                    type="button"
                    wire:click="requestBill"
                    wire:loading.attr="disabled"
                    wire:target="requestBill"
                    class="flex min-h-12 w-full items-center justify-center rounded-lg bg-sky-700 px-4 text-base font-semibold text-white transition hover:bg-sky-800 focus:outline-hidden focus:ring-2 focus:ring-sky-600 focus:ring-offset-2 dark:focus:ring-offset-zinc-950"
                >
                    <span wire:loading.remove wire:target="requestBill">{{ __('Попросить счёт') }} · {{ $tableTotalAmount }} {{ $currency }}</span>
                    <span wire:loading wire:target="requestBill">{{ __('Отправляем') }}</span>
                </button>
            @endif
        </div>

        @if ($canSendDraftToWaiter)
            <div class="space-y-2 border-t border-zinc-200 pt-3 dark:border-zinc-800">
                @if ($sendNeedsReadyConfirmation)
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-900/70 dark:bg-amber-950/30">
                        <p class="text-sm font-semibold text-amber-900 dark:text-amber-100">
                            {{ __('Не все гости отметили готовность.') }}
                        </p>
                        <p class="mt-1 text-sm text-amber-800 dark:text-amber-100">
                            {{ __('Можно отправить сейчас, но официант всё равно должен подтвердить заказ перед кухней или баром.') }}
                        </p>

                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            <button
                                type="button"
                                wire:click="sendDraftToWaiter(true)"
                                wire:loading.attr="disabled"
                                wire:target="sendDraftToWaiter"
                                class="inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-700 px-4 text-sm font-semibold text-white transition hover:bg-amber-800 focus:outline-hidden focus:ring-2 focus:ring-amber-600 focus:ring-offset-2 dark:focus:ring-offset-zinc-950"
                            >
                                <span wire:loading.remove wire:target="sendDraftToWaiter">{{ __('Отправить всё равно') }}</span>
                                <span wire:loading wire:target="sendDraftToWaiter">{{ __('Отправляем') }}</span>
                            </button>

                            <button
                                type="button"
                                wire:click="cancelSendDraftConfirmation"
                                class="inline-flex min-h-11 items-center justify-center rounded-lg border border-amber-300 bg-white px-4 text-sm font-semibold text-amber-800 transition hover:bg-amber-50 focus:outline-hidden focus:ring-2 focus:ring-amber-500/30 dark:border-amber-900/70 dark:bg-zinc-900 dark:text-amber-200 dark:hover:bg-amber-950/30"
                            >
                                {{ __('Подождать гостей') }}
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
                        <span wire:loading.remove wire:target="sendDraftToWaiter">{{ __('Отправить официанту') }}</span>
                        <span wire:loading wire:target="sendDraftToWaiter">{{ __('Отправляем') }}</span>
                    </button>
                @endif
            </div>
        @endif
    </div>
</section>
