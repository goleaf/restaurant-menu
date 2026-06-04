<section data-component="guest-menu" class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-xs font-medium uppercase text-emerald-700 dark:text-emerald-300">{{ __('Меню') }}</p>
            <h2 class="mt-1 text-lg font-semibold leading-tight text-zinc-950 dark:text-white">
                {{ $guestMenu['menu']['name'] ?? __('Выбор блюд') }}
            </h2>
        </div>

        <div class="shrink-0">
            <label for="guest-menu-language-{{ $branchId }}" class="sr-only">{{ __('Язык меню') }}</label>
            <select
                id="guest-menu-language-{{ $branchId }}"
                wire:model.live="language"
                class="h-9 rounded-lg border border-zinc-300 bg-white px-2 text-sm font-semibold text-zinc-800 shadow-sm focus:border-emerald-500 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
            >
                @foreach ($languageOptions as $languageCode => $languageLabel)
                    <option wire:key="guest-menu-language-option-{{ $languageCode }}" value="{{ $languageCode }}">
                        {{ $languageLabel }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($guestMenu['menu'] === null)
        <x-ui.empty-state
            class="mt-4"
            icon="book-open"
            :heading="__('Меню пока недоступно')"
        />
    @else
        @if ($feedbackMessage)
            <x-ui.alert tone="success" class="mt-4">
                {{ $feedbackMessage }}
            </x-ui.alert>
        @endif

        @error('guest')
            <x-ui.alert tone="danger" class="mt-4">{{ $message }}</x-ui.alert>
        @enderror

        <div class="mt-4 space-y-5">
            @forelse ($guestMenu['categories'] as $category)
                <section wire:key="guest-menu-category-{{ $category['id'] }}" class="space-y-3">
                    <div class="flex items-start gap-2">
                        <div class="min-w-0 flex-1">
                            <h3 class="text-base font-semibold text-zinc-950 dark:text-white">{{ $category['name'] }}</h3>

                            @if ($category['description'])
                                <p class="mt-1 text-sm leading-5 text-zinc-600 dark:text-zinc-300">{{ $category['description'] }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="space-y-3">
                        @forelse ($category['items'] as $item)
                            <article
                                wire:key="guest-menu-item-{{ $item['id'] }}"
                                @class([
                                    'grid gap-3 rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-800 dark:bg-zinc-950/60',
                                    'opacity-65' => ! $item['is_available'],
                                ])
                            >
                                <div class="flex items-start gap-3">
                                    <div class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                                        @if ($item['image_url'])
                                            <img src="{{ $item['image_url'] }}" alt="{{ $item['name'] }}" class="size-full object-cover">
                                        @else
                                            <span class="px-2 text-center text-xs font-medium text-zinc-400">{{ __('Фото') }}</span>
                                        @endif
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="grid gap-1">
                                            <h4 class="min-w-0 text-sm font-semibold leading-5 text-zinc-950 dark:text-white">{{ $item['name'] }}</h4>
                                            <span class="text-sm font-semibold text-zinc-950 dark:text-white">
                                                {{ $item['formatted_price'] }}
                                            </span>
                                        </div>

                                        @if ($item['description'])
                                            <p class="mt-1 text-sm leading-5 text-zinc-600 dark:text-zinc-300">{{ $item['description'] }}</p>
                                        @endif

                                        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                            @if ($item['weight'])
                                                <span>{{ $item['weight'] }} {{ __('г') }}</span>
                                            @endif

                                            @if ($item['volume'])
                                                <span>{{ $item['volume'] }} {{ __('л') }}</span>
                                            @endif

                                            @if ($item['calories'])
                                                <span>{{ $item['calories'] }} {{ __('ккал') }}</span>
                                            @endif
                                        </div>

                                        <div class="mt-3">
                                            @if ($item['is_available'] && $guestCanAddItems)
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <x-ui.status-badge tone="success">
                                                        {{ __('Доступно') }}
                                                    </x-ui.status-badge>

                                                    <x-ui.button
                                                        type="button"
                                                        wire:click="openItem({{ $item['id'] }})"
                                                        variant="dark"
                                                        size="sm"
                                                    >
                                                        {{ __('Добавить') }}
                                                    </x-ui.button>
                                                </div>
                                            @elseif ($item['is_available'])
                                                <x-ui.status-badge tone="muted">
                                                    {{ __('Недоступно') }}
                                                </x-ui.status-badge>
                                            @else
                                                <x-ui.status-badge tone="muted">
                                                    {{ __('Нет в наличии') }}
                                                </x-ui.status-badge>
                                            @endif
                                        </div>

                                        @if (isset($configuredItems[$item['id']]))
                                            <div class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-100">
                                                <div class="flex flex-wrap items-center justify-between gap-2">
                                                    <span class="font-semibold">{{ __('Добавлено') }}</span>
                                                    <span class="font-semibold">{{ $configuredItems[$item['id']]['total_price'] }}</span>
                                                </div>

                                                @if ($configuredItems[$item['id']]['modifier_summary'] !== [])
                                                    <p class="mt-1 text-xs leading-5">
                                                        {{ implode(', ', $configuredItems[$item['id']]['modifier_summary']) }}
                                                    </p>
                                                @endif

                                                @if ($configuredItems[$item['id']]['comment'])
                                                    <p class="mt-1 text-xs leading-5">{{ $configuredItems[$item['id']]['comment'] }}</p>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </article>
                        @empty
                            <x-ui.empty-state
                                icon="cake"
                                :heading="__('В этой категории пока нет блюд')"
                            />
                        @endforelse
                    </div>
                </section>
            @empty
                <x-ui.empty-state
                    icon="book-open"
                    :heading="__('Категории меню пока не настроены')"
                />
            @endforelse
        </div>
    @endif

    @if ($selectedItem !== null)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-zinc-950/50 px-3 py-0 sm:items-center sm:py-6">
            <div class="max-h-[92dvh] w-full max-w-lg overflow-y-auto rounded-t-2xl bg-white p-4 shadow-2xl dark:bg-zinc-950 sm:rounded-2xl">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-medium uppercase text-emerald-700 dark:text-emerald-300">{{ __('Ваш выбор') }}</p>
                        <h3 class="mt-1 text-lg font-semibold leading-tight text-zinc-950 dark:text-white">{{ $selectedItem['name'] }}</h3>
                        <p class="mt-1 text-sm font-semibold text-zinc-700 dark:text-zinc-200">{{ $selectedItemTotal }}</p>
                    </div>

                    <button
                        type="button"
                        wire:click="closeItemSheet"
                        class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg border border-zinc-200 text-zinc-600 transition hover:bg-zinc-50 focus:outline-hidden focus:ring-2 focus:ring-zinc-500 dark:border-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-900"
                        aria-label="{{ __('Закрыть') }}"
                    >
                        <flux:icon name="x-mark" variant="micro" class="size-4" />
                    </button>
                </div>

                <div class="mt-4 space-y-4">
                    @forelse ($selectedItem['modifier_groups'] as $modifierGroup)
                        <fieldset wire:key="guest-menu-selected-group-{{ $modifierGroup['id'] }}" class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
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
                                    @php($isSelected = in_array($modifierOption['id'], $selectedModifierOptions[(string) $modifierGroup['id']] ?? [], true))
                                    <button
                                        type="button"
                                        wire:key="guest-menu-selected-option-{{ $modifierOption['id'] }}"
                                        wire:click="toggleModifierOption({{ $modifierGroup['id'] }}, {{ $modifierOption['id'] }})"
                                        @class([
                                            'flex min-h-12 w-full items-center justify-between gap-3 rounded-lg border px-3 py-2 text-left text-sm transition focus:outline-hidden focus:ring-2 focus:ring-emerald-500/30',
                                            'border-emerald-500 bg-emerald-50 text-emerald-950 dark:border-emerald-500 dark:bg-emerald-950/40 dark:text-emerald-50' => $isSelected,
                                            'border-zinc-200 bg-white text-zinc-800 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800' => ! $isSelected,
                                        ])
                                    >
                                        <span class="font-medium">{{ $modifierOption['name'] }}</span>
                                        <span class="shrink-0 font-semibold">
                                            {{ $modifierOption['formatted_price_delta'] }}
                                        </span>
                                    </button>
                                @empty
                                    <x-ui.empty-state
                                        icon="adjustments-horizontal"
                                        :heading="__('Нет доступных вариантов')"
                                    />
                                @endforelse
                            </div>

                            @error('selectedModifierOptions.'.$modifierGroup['id'])
                                <p class="mt-2 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </fieldset>
                    @empty
                        <x-ui.empty-state
                            icon="adjustments-horizontal"
                            :heading="__('Для этого блюда нет дополнительных настроек')"
                        />
                    @endforelse

                    <label class="grid gap-1 text-sm">
                        <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ __('Комментарий') }}</span>
                        <textarea
                            wire:model="itemComment"
                            rows="3"
                            maxlength="500"
                            class="rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-emerald-500 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100"
                        ></textarea>
                        @error('itemComment')
                            <span class="text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</span>
                        @enderror
                    </label>
                </div>

                <x-ui.mobile-bottom-actions class="mt-5">
                    <x-ui.button
                        type="button"
                        wire:click="saveConfiguredItem"
                        wire:loading.attr="disabled"
                        wire:target="saveConfiguredItem"
                        variant="primary"
                        size="lg"
                        full-width
                    >
                        <span wire:loading.remove wire:target="saveConfiguredItem">{{ __('Добавить') }} · {{ $selectedItemTotal }}</span>
                        <span wire:loading wire:target="saveConfiguredItem">{{ __('Добавляем') }}</span>
                    </x-ui.button>
                </x-ui.mobile-bottom-actions>
            </div>
        </div>
    @endif
</section>
