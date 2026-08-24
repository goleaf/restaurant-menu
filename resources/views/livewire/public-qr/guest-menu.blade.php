<section data-component="guest-menu" class="overflow-hidden rounded-card border border-border-subtle bg-surface">
    <div class="border-b border-border-subtle bg-surface p-4">
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-accent">{{ __('menu.guest.title') }}</p>
            <h2 class="mt-1 text-lg font-semibold leading-tight text-text-primary">
                {{ $availableMenuCount > 1 ? __('menu.guest.choose_menu') : ($guestMenu['menu']['name'] ?? __('menu.guest.choose_items')) }}
            </h2>
            @if ($availableMenuCount > 1)
                <p class="mt-1 text-sm leading-5 text-text-muted">
                    {{ __('menu.guest.available_count', ['count' => $availableMenuCount]) }}
                </p>
            @endif
        </div>

        <div class="shrink-0">
            <label for="guest-menu-language-{{ $branchId }}" class="sr-only">{{ __('menu.guest.language') }}</label>
            <select
                id="guest-menu-language-{{ $branchId }}"
                wire:model.live="language"
                class="min-h-touch rounded-control border border-border-strong bg-surface px-2 text-sm font-semibold text-text-primary focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2"
            >
                @foreach ($languageOptions as $languageCode => $languageLabel)
                    <option wire:key="guest-menu-language-option-{{ $languageCode }}" value="{{ $languageCode }}">
                        {{ $languageLabel }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <div wire:offline class="border-b border-border-subtle p-4">
        <x-ui.state-panel kind="offline" title="ui.connectivity.offline" />
    </div>
    </div>

    @if ($availableMenuCount === 0)
        <div class="p-4">
            <x-ui.empty-state
                icon="book-open"
                :heading="$guestMenu['availability']['label'] ?? __('menu.guest.unavailable')"
                :description="$guestMenu['availability']['detail'] ?? null"
            />

            @if ($unavailableMenus !== [])
                <div class="mt-4 rounded-lg border border-dashed border-amber-200 bg-amber-50 p-3 dark:border-amber-900/60 dark:bg-amber-950/20">
                    <p class="text-sm font-semibold text-amber-950 dark:text-amber-100">{{ __('menu.guest.available_later') }}</p>

                    <div class="mt-3 grid gap-2">
                        @foreach ($unavailableMenus as $unavailableMenu)
                            <div wire:key="guest-menu-unavailable-empty-{{ $unavailableMenu['id'] }}" class="flex flex-col gap-1 text-sm text-amber-950 dark:text-amber-100">
                                <span class="font-medium">{{ $unavailableMenu['name'] }}</span>
                                <span class="text-xs text-amber-800 dark:text-amber-200">{{ $unavailableMenu['availability']['detail'] ?? __('menu.guest.schedule_unknown') }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @else
        <div class="p-4">
        @if (! $branchCanAcceptOrders)
            <x-ui.alert tone="warning" class="mt-4" :heading="__('menu.guest.closed_title')">
                <x-ui.plain-text :text="$branchOpeningStatusMessage ?: __('menu.guest.closed_description')" />
                <span class="mt-1 block">{{ __('menu.guest.browse_only') }}</span>
            </x-ui.alert>
        @endif

        @if ($feedbackMessage)
            <x-ui.alert tone="success" class="mt-4">
                {{ $feedbackMessage }}
            </x-ui.alert>
        @endif

        @error('guest')
            <x-ui.alert tone="danger" class="mt-4">{{ $message }}</x-ui.alert>
        @enderror

        @if ($guestMenu['has_allergen_information'] ?? false)
            <x-ui.alert tone="warning" class="mt-4" :heading="__('menu.allergens.notice_title')">
                {{ __('menu.allergens.safety_notice') }}
            </x-ui.alert>
        @endif

        <nav data-guest-category-nav aria-label="{{ __('menu.guest.categories') }}" class="mt-4 overflow-x-auto overscroll-x-contain pb-1">
            <ul class="flex w-max min-w-full gap-2">
                @foreach ($availableMenus as $menu)
                    @foreach ($menu['categories'] as $category)
                        <li wire:key="guest-menu-category-link-{{ $menu['id'] }}-{{ $category['id'] }}">
                            <a
                                href="#guest-menu-category-{{ $menu['id'] }}-{{ $category['id'] }}"
                                class="inline-flex min-h-touch items-center rounded-control border border-border-subtle bg-surface-muted px-3 text-sm font-semibold text-text-secondary focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2"
                            >
                                {{ $category['name'] }}
                            </a>
                        </li>
                    @endforeach
                @endforeach
            </ul>
        </nav>

        <div class="mt-4 space-y-7">
            @forelse ($availableMenus as $menu)
                <section wire:key="guest-menu-menu-{{ $menu['id'] }}" class="space-y-4">
                    @if ($availableMenuCount > 1)
                        <div class="border-b border-zinc-100 pb-3 dark:border-zinc-800">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h3 class="text-lg font-semibold leading-tight text-zinc-950 dark:text-white">{{ $menu['name'] }}</h3>
                                <x-ui.status-badge tone="success">
                                    {{ $menu['availability']['label'] ?? __('menu.guest.available_now') }}
                                </x-ui.status-badge>
                            </div>

                            @if ($menu['availability']['detail'] ?? null)
                                <p class="mt-1 text-sm leading-5 text-zinc-600 dark:text-zinc-300">{{ $menu['availability']['detail'] }}</p>
                            @endif
                        </div>
                    @endif

                    <div class="space-y-5">
                    @forelse ($menu['categories'] as $category)
                <section id="guest-menu-category-{{ $menu['id'] }}-{{ $category['id'] }}" wire:key="guest-menu-menu-{{ $menu['id'] }}-category-{{ $category['id'] }}" class="scroll-mt-4 space-y-3">
                    <div class="flex items-start gap-2 border-b border-border-subtle pb-2">
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-semibold leading-tight text-text-primary">{{ $category['name'] }}</h3>

                            <x-ui.plain-text :text="$category['description']" class="mt-1 block text-sm leading-5 text-text-muted" />
                        </div>
                    </div>

                    <div class="space-y-3">
                        @forelse ($category['items'] as $item)
                            <article
                                data-guest-menu-item
                                wire:key="guest-menu-item-{{ $item['id'] }}"
                                @class([
                                    'overflow-hidden rounded-control border border-border-subtle bg-surface',
                                    'opacity-65' => ! $item['is_available'],
                                ])
                            >
                                <div class="grid grid-cols-[5.5rem_1fr] gap-3 p-3">
                                    <div class="flex aspect-square w-full shrink-0 items-center justify-center overflow-hidden rounded-control border border-border-subtle bg-surface-muted">
                                        @if ($item['image_url'])
                                            <img src="{{ $item['image_url'] }}" alt="{{ $item['name'] }}" width="176" height="176" loading="lazy" decoding="async" class="size-full object-cover">
                                        @else
                                            <span class="px-2 text-center text-xs font-semibold text-text-muted">{{ __('menu.item_detail.gallery') }}</span>
                                        @endif
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-3">
                                            <h4 class="min-w-0 text-base font-semibold leading-5 text-text-primary">{{ $item['name'] }}</h4>
                                            <span class="shrink-0 rounded-control bg-surface-muted px-2 py-1 text-sm font-semibold text-text-primary">
                                                {{ $item['formatted_price'] }}
                                            </span>
                                        </div>

                                        <x-ui.plain-text :text="$item['description']" class="mt-1 block text-sm leading-5 text-text-muted" />

                                        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-text-muted">
                                            @if ($item['weight'])
                                                <span>{{ $item['weight'] }} {{ __('menu.guest.unit_grams') }}</span>
                                            @endif

                                            @if ($item['volume'])
                                                <span>{{ $item['volume'] }} {{ __('menu.guest.unit_liters') }}</span>
                                            @endif

                                            @if ($item['calories'])
                                                <span>{{ $item['calories'] }} {{ __('menu.guest.unit_kcal') }}</span>
                                            @endif
                                        </div>

                                        <x-menu.item-labels
                                            class="mt-3"
                                            :allergens="$item['allergens']"
                                            :dietary-labels="$item['dietary_labels']"
                                        />

                                        <div class="mt-3">
                                            @if ($item['is_available'] && $guestCanAddItems && $branchCanAcceptOrders)
                                                <div class="grid gap-2">
                                                    <div>
                                                    <x-ui.status-badge tone="success">
                                                        {{ __('menu.guest.available') }}
                                                    </x-ui.status-badge>
                                                    </div>

                                                    <x-ui.button
                                                        type="button"
                                                        wire:click="openItem({{ $item['id'] }})"
                                                        variant="dark"
                                                        size="lg"
                                                        full-width
                                                        icon="plus"
                                                    >
                                                        {{ __('menu.guest.add') }}
                                                    </x-ui.button>
                                                </div>
                                            @elseif ($item['is_available'] && ! $branchCanAcceptOrders)
                                                <x-ui.status-badge tone="warning">
                                                    {{ __('menu.guest.closed_title') }}
                                                </x-ui.status-badge>
                                            @elseif ($item['is_available'])
                                                <x-ui.status-badge tone="muted">
                                                    {{ __('menu.guest.unavailable') }}
                                                </x-ui.status-badge>
                                            @else
                                                <x-ui.status-badge tone="muted">
                                                    {{ __('menu.guest.out_of_stock') }}
                                                </x-ui.status-badge>
                                            @endif
                                        </div>

                                        @if (isset($configuredItems[$item['id']]))
                                            <div class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-100">
                                                <div class="flex flex-wrap items-center justify-between gap-2">
                                                    <span class="font-semibold">{{ __('menu.guest.added') }}</span>
                                                    <span class="font-semibold">{{ $configuredItems[$item['id']]['total_price'] }}</span>
                                                </div>

                                                @if ($configuredItems[$item['id']]['modifier_summary'] !== [])
                                                    <p class="mt-1 text-xs leading-5">
                                                        {{ implode(', ', $configuredItems[$item['id']]['modifier_summary']) }}
                                                    </p>
                                                @endif

                                                <x-ui.plain-text :text="$configuredItems[$item['id']]['comment']" class="mt-1 block text-xs leading-5" />
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </article>
                        @empty
                            <x-ui.empty-state
                                icon="cake"
                                :heading="__('menu.guest.no_items_found')"
                            />
                        @endforelse
                    </div>
                </section>
            @empty
                <x-ui.empty-state
                    icon="book-open"
                    :heading="__('menu.guest.no_categories_found')"
                />
            @endforelse
                    </div>
                </section>
            @empty
                <x-ui.empty-state
                    icon="book-open"
                    :heading="__('menu.guest.unavailable')"
                />
            @endforelse

            @if ($unavailableMenus !== [])
                <div class="rounded-lg border border-dashed border-amber-200 bg-amber-50 p-3 dark:border-amber-900/60 dark:bg-amber-950/20">
                    <p class="text-sm font-semibold text-amber-950 dark:text-amber-100">{{ __('menu.guest.available_later') }}</p>

                    <div class="mt-3 grid gap-2">
                        @foreach ($unavailableMenus as $unavailableMenu)
                            <div wire:key="guest-menu-unavailable-{{ $unavailableMenu['id'] }}" class="flex flex-col gap-1 text-sm text-amber-950 dark:text-amber-100">
                                <span class="font-medium">{{ $unavailableMenu['name'] }}</span>
                                <span class="text-xs text-amber-800 dark:text-amber-200">{{ $unavailableMenu['availability']['detail'] ?? __('menu.guest.schedule_unknown') }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
        </div>
    @endif

    @if ($selectedItem !== null)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-zinc-950/50 px-3 py-0 sm:items-center sm:py-6">
            <div class="max-h-[92dvh] w-full max-w-lg overflow-y-auto overscroll-contain rounded-t-dialog bg-white p-4 shadow-elevated dark:bg-zinc-950 sm:rounded-dialog">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-start gap-3">
                        <div class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900">
                            @if ($selectedItem['image_url'])
                                <img src="{{ $selectedItem['image_url'] }}" alt="{{ $selectedItem['name'] }}" width="64" height="64" decoding="async" class="size-full object-cover">
                            @else
                                <span class="px-2 text-center text-xs font-semibold text-zinc-600 dark:text-zinc-400">{{ __('menu.item_detail.gallery') }}</span>
                            @endif
                        </div>

                        <div class="min-w-0">
                            <p class="text-xs font-medium uppercase text-emerald-700 dark:text-emerald-300">{{ __('menu.item_detail.title') }}</p>
                            <h3 class="mt-1 text-lg font-semibold leading-tight text-zinc-950 dark:text-white">{{ $selectedItem['name'] }}</h3>
                            <p class="mt-1 text-sm font-semibold text-zinc-700 dark:text-zinc-200">{{ $selectedItemTotal }}</p>

                            <x-ui.plain-text :text="$selectedItem['description']" :preserve-lines="false" class="mt-1 line-clamp-2 block text-sm leading-5 text-zinc-600 dark:text-zinc-300" />
                        </div>
                    </div>

                    <button
                        type="button"
                        wire:click="closeItemSheet"
                        class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg border border-zinc-200 text-zinc-600 transition hover:bg-zinc-50 focus:outline-hidden focus:ring-2 focus:ring-zinc-500 dark:border-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-900"
                        aria-label="{{ __('menu.guest.close') }}"
                    >
                        <flux:icon name="x-mark" variant="micro" class="size-4" />
                    </button>
                </div>

                <x-menu.item-labels
                    class="mt-4"
                    :allergens="$selectedItem['allergens']"
                    :dietary-labels="$selectedItem['dietary_labels']"
                />

                <div class="mt-4 space-y-4">
                    @if ($selectedItem['variants'] !== [])
                        <fieldset class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                            <legend class="px-1 text-sm font-semibold text-zinc-950 dark:text-white">
                                {{ __('menu.variants.guest.choose') }}
                            </legend>

                            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                @foreach ($selectedItem['variants'] as $variant)
                                    <label
                                        wire:key="guest-menu-selected-variant-{{ $variant['id'] }}"
                                        @class([
                                            'flex min-h-12 cursor-pointer items-center justify-between gap-3 rounded-lg border px-3 py-2 text-sm transition focus-within:ring-2 focus-within:ring-emerald-500/30',
                                            'border-emerald-500 bg-emerald-50 text-emerald-950 dark:border-emerald-500 dark:bg-emerald-950/40 dark:text-emerald-50' => $selectedItemVariantId === $variant['id'],
                                            'border-zinc-200 bg-white text-zinc-800 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800' => $selectedItemVariantId !== $variant['id'],
                                        ])
                                    >
                                        <span class="flex min-w-0 items-center gap-2">
                                            <input type="radio" wire:model.live="selectedItemVariantId" value="{{ $variant['id'] }}" class="size-4 shrink-0 accent-emerald-600">
                                            <span class="min-w-0">
                                                <span class="block font-medium">{{ $variant['name'] }}</span>
                                                @if ($variant['weight'] || $variant['volume'])
                                                    <span class="block text-xs text-zinc-500 dark:text-zinc-400">
                                                        @if ($variant['weight']) {{ $variant['weight'] }} {{ __('menu.guest.unit_grams') }} @endif
                                                        @if ($variant['weight'] && $variant['volume']) · @endif
                                                        @if ($variant['volume']) {{ $variant['volume'] }} {{ __('menu.guest.unit_liters') }} @endif
                                                    </span>
                                                @endif
                                            </span>
                                        </span>
                                        <span class="shrink-0 font-semibold">{{ $variant['formatted_price'] }}</span>
                                    </label>
                                @endforeach
                            </div>

                            @error('selectedItemVariantId')
                                <p class="mt-2 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </fieldset>
                    @endif

                    @forelse ($selectedItem['modifier_groups'] as $modifierGroup)
                        <fieldset wire:key="guest-menu-selected-group-{{ $modifierGroup['id'] }}" class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                            <legend class="px-1 text-sm font-semibold text-zinc-950 dark:text-white">
                                {{ $modifierGroup['name'] }}
                            </legend>

                            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                @if ($modifierGroup['is_required'])
                                    <span>{{ __('menu.modifiers.required') }}</span>
                                @else
                                    <span>{{ __('menu.modifiers.optional') }}</span>
                                @endif

                                @if ((int) $modifierGroup['min_select'] > 0)
                                    <span>{{ __('menu.modifiers.choose_min', ['min' => $modifierGroup['min_select']]) }}</span>
                                @endif

                                @if ((int) $modifierGroup['max_select'] > 0)
                                    <span>{{ __('menu.modifiers.choose_max', ['max' => $modifierGroup['max_select']]) }}</span>
                                @endif
                            </div>

                            <div class="mt-3 grid gap-2">
                                @forelse ($modifierGroup['options'] as $modifierOption)
                                    <button
                                        type="button"
                                        wire:key="guest-menu-selected-option-{{ $modifierOption['id'] }}"
                                        wire:click="toggleModifierOption({{ $modifierGroup['id'] }}, {{ $modifierOption['id'] }})"
                                        aria-pressed="{{ in_array($modifierOption['id'], $selectedModifierOptions[(string) $modifierGroup['id']] ?? [], true) ? 'true' : 'false' }}"
                                        @class([
                                            'flex min-h-12 w-full items-center justify-between gap-3 rounded-lg border px-3 py-2 text-left text-sm transition focus:outline-hidden focus:ring-2 focus:ring-emerald-500/30',
                                            'border-emerald-500 bg-emerald-50 text-emerald-950 dark:border-emerald-500 dark:bg-emerald-950/40 dark:text-emerald-50' => in_array($modifierOption['id'], $selectedModifierOptions[(string) $modifierGroup['id']] ?? [], true),
                                            'border-zinc-200 bg-white text-zinc-800 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800' => ! in_array($modifierOption['id'], $selectedModifierOptions[(string) $modifierGroup['id']] ?? [], true),
                                        ])
                                    >
                                        <span class="font-medium">{{ $modifierOption['name'] }}</span>
                                        <span class="shrink-0 font-semibold">
                                            {{ __('menu.modifiers.price_delta', ['price' => $modifierOption['formatted_price_delta']]) }}
                                        </span>
                                    </button>
                                @empty
                                    <x-ui.empty-state
                                        icon="adjustments-horizontal"
                                        :heading="__('menu.modifiers.no_options')"
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
                            :heading="__('menu.guest.no_modifiers')"
                        />
                    @endforelse

                    <label class="grid gap-1 text-sm">
                        <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ __('menu.guest.comment') }}</span>
                        <textarea
                            wire:model="itemComment"
                            rows="3"
                            maxlength="500"
                            placeholder="{{ __('menu.guest.comment_placeholder') }}"
                            class="rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-emerald-500 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100"
                        ></textarea>
                        @error('itemComment')
                            <span class="text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</span>
                        @enderror
                    </label>
                </div>

                <x-ui.mobile-bottom-actions class="mt-5" :summary="$selectedItemTotal">
                    <x-ui.button
                        type="button"
                        wire:click="saveConfiguredItem"
                        wire:loading.attr="disabled"
                        wire:target="saveConfiguredItem"
                        variant="primary"
                        size="lg"
                        full-width
                    >
                        <span wire:loading.remove wire:target="saveConfiguredItem">{{ __('menu.guest.add_for_price', ['price' => $selectedItemTotal]) }}</span>
                        <span wire:loading wire:target="saveConfiguredItem">{{ __('menu.guest.adding') }}</span>
                    </x-ui.button>
                </x-ui.mobile-bottom-actions>
            </div>
        </div>
    @endif
</section>
