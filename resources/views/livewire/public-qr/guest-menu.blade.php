<section data-component="guest-menu" class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-xs font-medium uppercase text-emerald-700 dark:text-emerald-300">{{ __('Меню') }}</p>
            <h2 class="mt-1 text-lg font-semibold leading-tight text-zinc-950 dark:text-white">
                {{ $guestMenu['menu']['name'] ?? __('Выбор блюд') }}
            </h2>
        </div>
    </div>

    @if ($guestMenu['menu'] === null)
        <div class="mt-4 rounded-lg border border-dashed border-zinc-300 bg-zinc-50 px-4 py-6 text-center dark:border-zinc-700 dark:bg-zinc-950/60">
            <p class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('Меню пока недоступно') }}</p>
        </div>
    @else
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
                                                {{ $item['price'] }} {{ $currency }}
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
                                            @if ($item['is_available'])
                                                <span class="inline-flex rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200">
                                                    {{ __('Доступно') }}
                                                </span>
                                            @else
                                                <span class="inline-flex rounded-md bg-zinc-200 px-2.5 py-1 text-xs font-semibold text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                                                    {{ __('Недоступно') }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </article>
                        @empty
                            <p class="rounded-lg bg-zinc-50 px-3 py-3 text-sm text-zinc-600 dark:bg-zinc-950/60 dark:text-zinc-300">
                                {{ __('В этой категории пока нет блюд') }}
                            </p>
                        @endforelse
                    </div>
                </section>
            @empty
                <p class="rounded-lg bg-zinc-50 px-3 py-3 text-sm text-zinc-600 dark:bg-zinc-950/60 dark:text-zinc-300">
                    {{ __('Категории меню пока не настроены') }}
                </p>
            @endforelse
        </div>
    @endif
</section>
