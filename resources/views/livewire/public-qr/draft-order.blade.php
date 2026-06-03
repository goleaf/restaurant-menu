<section
    data-component="guest-draft-order"
    wire:poll.1s="refreshDraft"
    class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
>
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-xs font-medium uppercase text-emerald-700 dark:text-emerald-300">{{ __('Общий заказ') }}</p>
            <h2 class="mt-1 text-lg font-semibold leading-tight text-zinc-950 dark:text-white">{{ __('Корзина') }}</h2>
        </div>

        <span class="rounded-md bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
            {{ trans_choice(':count позиция|:count позиции|:count позиций', $itemCount, ['count' => $itemCount]) }}
        </span>
    </div>

    <div class="mt-4 space-y-4">
        @forelse ($items as $item)
            <article wire:key="draft-order-item-{{ $item['id'] }}" class="rounded-lg border border-zinc-100 bg-zinc-50 p-3 dark:border-zinc-800 dark:bg-zinc-950/60">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $item['item_name'] }}</h3>

                            @if ($item['is_current_guest'])
                                <span class="rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-100">
                                    {{ __('Ваше') }}
                                </span>
                            @endif
                        </div>

                        <p class="mt-1 text-xs font-medium text-zinc-500 dark:text-zinc-400">
                            {{ $item['guest_name'] }}
                        </p>
                    </div>

                    <div class="shrink-0 text-right">
                        <p class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $item['total_price'] }} {{ $currency }}</p>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">×{{ $item['quantity'] }}</p>
                    </div>
                </div>

                @if ($item['modifiers'] !== [])
                    <p class="mt-2 text-xs leading-5 text-zinc-600 dark:text-zinc-300">
                        {{ implode(', ', $item['modifiers']) }}
                    </p>
                @endif

                @if ($item['comment'])
                    <p class="mt-2 rounded-md bg-white px-2 py-1.5 text-xs leading-5 text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">
                        {{ $item['comment'] }}
                    </p>
                @endif
            </article>
        @empty
            <p class="rounded-lg bg-zinc-50 px-3 py-2 text-sm text-zinc-600 dark:bg-zinc-950/60 dark:text-zinc-300">
                {{ __('Пока пусто') }}
            </p>
        @endforelse

        <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-950/60">
            <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('По гостям') }}</p>

            <div class="mt-2 space-y-2">
                @forelse ($guestTotals as $guestTotal)
                    <div wire:key="draft-order-guest-total-{{ $guestTotal['guest_id'] }}" class="flex items-center justify-between gap-3 text-sm">
                        <span class="min-w-0 truncate font-medium text-zinc-700 dark:text-zinc-200">
                            {{ $guestTotal['guest_name'] }}

                            @if ($guestTotal['is_current_guest'])
                                <span class="text-xs font-semibold text-emerald-700 dark:text-emerald-300">{{ __('Вы') }}</span>
                            @endif
                        </span>

                        <span class="shrink-0 font-semibold text-zinc-950 dark:text-white">{{ $guestTotal['total'] }} {{ $currency }}</span>
                    </div>
                @empty
                    <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Гости появятся после входа за стол.') }}</p>
                @endforelse
            </div>
        </div>

        <div class="flex items-center justify-between border-t border-zinc-200 pt-3 dark:border-zinc-800">
            <span class="text-sm font-medium text-zinc-600 dark:text-zinc-300">{{ __('Общая сумма') }}</span>
            <span class="text-xl font-semibold text-zinc-950 dark:text-white">
                {{ $totalAmount }} {{ $currency }}
            </span>
        </div>
    </div>
</section>
