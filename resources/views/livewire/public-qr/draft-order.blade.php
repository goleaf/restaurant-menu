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

    @if (! $canEditDraft)
        <p class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
            {{ __('Черновик отправлен официанту. Изменения сейчас недоступны.') }}
        </p>
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

    <div class="mt-4 space-y-4">
        <section class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('Мои позиции') }}</h3>
                <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ count($myItems) }}</span>
            </div>

            @forelse ($myItems as $item)
                <article wire:key="draft-order-my-item-{{ $item['id'] }}" class="rounded-lg border border-emerald-100 bg-emerald-50/60 p-3 dark:border-emerald-900/50 dark:bg-emerald-950/20">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h4 class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $item['item_name'] }}</h4>
                                <span class="rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-100">
                                    {{ __('Ваше') }}
                                </span>
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

                    @if ($item['can_edit'])
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button
                                type="button"
                                wire:click="editItem({{ $item['id'] }})"
                                class="inline-flex min-h-9 items-center justify-center rounded-lg border border-zinc-300 bg-white px-3 text-sm font-semibold text-zinc-800 transition hover:bg-zinc-50 focus:outline-hidden focus:ring-2 focus:ring-zinc-500/30 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800"
                            >
                                {{ __('Изменить') }}
                            </button>

                            <button
                                type="button"
                                wire:click="deleteItem({{ $item['id'] }})"
                                wire:loading.attr="disabled"
                                wire:target="deleteItem({{ $item['id'] }})"
                                class="inline-flex min-h-9 items-center justify-center rounded-lg border border-red-200 bg-white px-3 text-sm font-semibold text-red-700 transition hover:bg-red-50 focus:outline-hidden focus:ring-2 focus:ring-red-500/30 dark:border-red-900/70 dark:bg-zinc-900 dark:text-red-300 dark:hover:bg-red-950/30"
                            >
                                {{ __('Удалить') }}
                            </button>
                        </div>
                    @endif
                </article>
            @empty
                <p class="rounded-lg bg-zinc-50 px-3 py-2 text-sm text-zinc-600 dark:bg-zinc-950/60 dark:text-zinc-300">
                    {{ __('Вы пока ничего не добавили') }}
                </p>
            @endforelse
        </section>

        <section class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('Позиции других гостей') }}</h3>
                <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ count($otherItems) }}</span>
            </div>

            @forelse ($otherItems as $item)
                <article wire:key="draft-order-other-item-{{ $item['id'] }}" class="rounded-lg border border-zinc-100 bg-zinc-50 p-3 dark:border-zinc-800 dark:bg-zinc-950/60">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h4 class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $item['item_name'] }}</h4>

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
                    {{ __('Другие гости пока ничего не добавили') }}
                </p>
            @endforelse
        </section>

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

    @if ($editingItemId !== null)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-zinc-950/50 px-3 py-0 sm:items-center sm:py-6">
            <div class="max-h-[92dvh] w-full max-w-lg overflow-y-auto rounded-t-2xl bg-white p-4 shadow-2xl dark:bg-zinc-950 sm:rounded-2xl">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-medium uppercase text-emerald-700 dark:text-emerald-300">{{ __('Моя позиция') }}</p>
                        <h3 class="mt-1 text-lg font-semibold leading-tight text-zinc-950 dark:text-white">{{ $editingItemName }}</h3>
                        <p class="mt-1 text-sm font-semibold text-zinc-700 dark:text-zinc-200">{{ $editingItemTotal }} {{ $currency }}</p>
                    </div>

                    <button
                        type="button"
                        wire:click="closeEditItem"
                        class="inline-flex size-10 shrink-0 items-center justify-center rounded-full border border-zinc-200 text-xl leading-none text-zinc-600 transition hover:bg-zinc-50 focus:outline-hidden focus:ring-2 focus:ring-zinc-500 dark:border-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-900"
                        aria-label="{{ __('Закрыть') }}"
                    >
                        ×
                    </button>
                </div>

                <div class="mt-4 space-y-4">
                    <label class="grid gap-1 text-sm">
                        <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ __('Количество') }}</span>
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
                                    <span>{{ __('Обязательно') }}</span>
                                @else
                                    <span>{{ __('По желанию') }}</span>
                                @endif

                                <span>{{ __('Можно выбрать') }} {{ $modifierGroup['min_select'] }}–{{ $modifierGroup['max_select'] }}</span>
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
                                        {{ __('Нет доступных вариантов') }}
                                    </p>
                                @endforelse
                            </div>

                            @error('selectedModifierOptions.'.$modifierGroup['id'])
                                <p class="mt-2 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </fieldset>
                    @empty
                        <p class="rounded-lg bg-zinc-50 px-3 py-3 text-sm text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">
                            {{ __('Для этой позиции нет дополнительных настроек') }}
                        </p>
                    @endforelse

                    <label class="grid gap-1 text-sm">
                        <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ __('Комментарий') }}</span>
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

                <div class="sticky bottom-0 -mx-4 mt-5 grid gap-2 border-t border-zinc-200 bg-white px-4 pt-3 dark:border-zinc-800 dark:bg-zinc-950">
                    <button
                        type="button"
                        wire:click="updateItem"
                        wire:loading.attr="disabled"
                        wire:target="updateItem"
                        class="flex min-h-12 w-full items-center justify-center rounded-lg bg-emerald-700 px-4 text-base font-semibold text-white transition hover:bg-emerald-800 focus:outline-hidden focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2 dark:focus:ring-offset-zinc-950"
                    >
                        <span wire:loading.remove wire:target="updateItem">{{ __('Сохранить') }} · {{ $editingItemTotal }} {{ $currency }}</span>
                        <span wire:loading wire:target="updateItem">{{ __('Сохраняем') }}</span>
                    </button>

                    <button
                        type="button"
                        wire:click="deleteItem({{ $editingItemId }})"
                        wire:loading.attr="disabled"
                        wire:target="deleteItem({{ $editingItemId }})"
                        class="flex min-h-11 w-full items-center justify-center rounded-lg border border-red-200 bg-white px-4 text-sm font-semibold text-red-700 transition hover:bg-red-50 focus:outline-hidden focus:ring-2 focus:ring-red-500/30 dark:border-red-900/70 dark:bg-zinc-950 dark:text-red-300 dark:hover:bg-red-950/30"
                    >
                        {{ __('Удалить позицию') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</section>
