<section data-page="restaurant-onboarding" class="flex h-full w-full flex-1 flex-col gap-6">
    <header class="flex flex-col gap-3">
        <div class="flex flex-col gap-1">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Быстрый старт') }}</p>
            <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">{{ __('Настроить ресторан') }}</h1>
            <p class="max-w-2xl text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('Пройдите простые шаги: название, адрес, первый зал, столы, QR и тестовое меню.') }}
            </p>
        </div>
    </header>

    <div class="grid gap-4 xl:grid-cols-[18rem_1fr]">
        <aside class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Шаги') }}</flux:heading>

            <div class="mt-4 grid gap-2">
                @foreach ($this->steps as $wizardStep)
                    <button
                        type="button"
                        wire:key="onboarding-step-{{ $wizardStep['number'] }}"
                        wire:click="goToStep({{ $wizardStep['number'] }})"
                        @disabled(! $wizardStep['is_available'])
                        class="flex min-h-14 w-full items-center gap-3 rounded-lg border px-3 py-2 text-left transition {{ $wizardStep['is_current'] ? 'border-zinc-900 bg-zinc-100 dark:border-white dark:bg-zinc-800' : 'border-zinc-200 bg-zinc-50 hover:bg-white dark:border-zinc-800 dark:bg-zinc-950 dark:hover:bg-zinc-900' }} disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <flux:badge :icon="$wizardStep['icon']" :color="$wizardStep['is_done'] ? 'green' : ($wizardStep['is_current'] ? 'amber' : 'zinc')">
                            {{ $wizardStep['number'] }}
                        </flux:badge>

                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-zinc-950 dark:text-white">{{ $wizardStep['label'] }}</span>
                            <span class="block text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $wizardStep['is_done'] ? __('Готово') : ($wizardStep['is_current'] ? __('Сейчас') : __('Позже')) }}
                            </span>
                        </span>
                    </button>
                @endforeach
            </div>

            <div class="mt-5 rounded-lg border border-zinc-200 bg-zinc-50 p-3 text-sm text-zinc-600 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-300">
                <p class="font-medium text-zinc-900 dark:text-white">{{ __('Что уже создано') }}</p>
                <dl class="mt-2 grid gap-1">
                    <div class="flex justify-between gap-3">
                        <dt>{{ __('Компания') }}</dt>
                        <dd class="truncate font-medium">{{ $this->summary['organization'] ?? __('нет') }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt>{{ __('Ресторан') }}</dt>
                        <dd class="truncate font-medium">{{ $this->summary['brand'] ?? __('нет') }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt>{{ __('Точка') }}</dt>
                        <dd class="truncate font-medium">{{ $this->summary['branch'] ?? __('нет') }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt>{{ __('Зона') }}</dt>
                        <dd class="truncate font-medium">{{ $this->summary['area'] ?? __('нет') }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt>{{ __('Столы') }}</dt>
                        <dd class="font-medium">{{ $this->summary['service_points'] }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt>{{ __('QR') }}</dt>
                        <dd class="font-medium">{{ $this->summary['qr_codes'] }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt>{{ __('Меню') }}</dt>
                        <dd class="truncate font-medium">{{ $this->summary['menu'] ?? __('нет') }}</dd>
                    </div>
                </dl>
            </div>
        </aside>

        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            @if ($step === 1)
                <form wire:submit="createOrganization" class="grid gap-5">
                    <div class="flex flex-col gap-1">
                        <flux:badge icon="building-office" color="amber">{{ __('Шаг 1') }}</flux:badge>
                        <flux:heading size="xl">{{ __('Кто владеет заведением?') }}</flux:heading>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Напишите название компании или владельца. Вы автоматически станете владельцем этой компании.') }}</p>
                    </div>

                    <flux:input wire:model="organizationName" :label="__('Название компании')" type="text" required maxlength="120" autocomplete="organization" />

                    <div class="flex flex-wrap gap-3">
                        <flux:button icon="arrow-right" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createOrganization">
                            {{ __('Дальше') }}
                        </flux:button>
                    </div>
                </form>
            @elseif ($step === 2)
                <form wire:submit="createBrand" class="grid gap-5">
                    <div class="flex flex-col gap-1">
                        <flux:badge icon="building-storefront" color="amber">{{ __('Шаг 2') }}</flux:badge>
                        <flux:heading size="xl">{{ __('Как называется ресторан?') }}</flux:heading>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Это название гости и сотрудники будут узнавать в системе.') }}</p>
                    </div>

                    <flux:input wire:model="brandName" :label="__('Название ресторана')" type="text" required maxlength="120" />

                    <div class="flex flex-wrap gap-3">
                        <flux:button icon="arrow-left" type="button" wire:click="goToStep(1)">{{ __('Назад') }}</flux:button>
                        <flux:button icon="arrow-right" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createBrand">
                            {{ __('Дальше') }}
                        </flux:button>
                    </div>
                </form>
            @elseif ($step === 3)
                <form wire:submit="createBranch" class="grid gap-5">
                    <div class="flex flex-col gap-1">
                        <flux:badge icon="map-pin" color="amber">{{ __('Шаг 3') }}</flux:badge>
                        <flux:heading size="xl">{{ __('Где находится эта точка?') }}</flux:heading>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Укажите адрес первого филиала. Потом можно добавить другие точки обычным способом.') }}</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <flux:input wire:model="branchName" :label="__('Название точки')" type="text" required maxlength="160" />
                        <flux:input wire:model="branchAddress" :label="__('Адрес')" type="text" required maxlength="255" />
                        <flux:input wire:model="branchCity" :label="__('Город')" type="text" required maxlength="120" />
                        <flux:input wire:model="branchCountry" :label="__('Страна')" type="text" required maxlength="120" />
                        <flux:input wire:model="branchTimezone" :label="__('Часовой пояс')" type="text" required maxlength="64" />
                        <flux:field>
                            <flux:label>{{ __('Валюта') }}</flux:label>
                            <flux:select wire:model="branchCurrency">
                                @foreach ($currencyOptions as $currencyCode => $currencyLabel)
                                    <flux:select.option wire:key="onboarding-branch-currency-{{ $currencyCode }}" value="{{ $currencyCode }}">
                                        {{ $currencyLabel }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="branchCurrency" />
                        </flux:field>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <flux:button icon="arrow-left" type="button" wire:click="goToStep(2)">{{ __('Назад') }}</flux:button>
                        <flux:button icon="arrow-right" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createBranch">
                            {{ __('Дальше') }}
                        </flux:button>
                    </div>
                </form>
            @elseif ($step === 4)
                <form wire:submit="createArea" class="grid gap-5">
                    <div class="flex flex-col gap-1">
                        <flux:badge icon="rectangle-group" color="amber">{{ __('Шаг 4') }}</flux:badge>
                        <flux:heading size="xl">{{ __('Добавьте первый зал') }}</flux:heading>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Например: Главный зал, Терраса, VIP-зал. Столы позже будут внутри этой зоны.') }}</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <flux:input wire:model="areaName" :label="__('Название зоны')" type="text" required maxlength="160" />

                        <flux:select wire:model="areaType" :label="__('Что это?')">
                            <flux:select.option value="hall">{{ __('Зал') }}</flux:select.option>
                            <flux:select.option value="terrace">{{ __('Терраса') }}</flux:select.option>
                            <flux:select.option value="vip_room">{{ __('VIP-зал') }}</flux:select.option>
                            <flux:select.option value="custom">{{ __('Своя зона') }}</flux:select.option>
                        </flux:select>

                        <flux:select wire:model="areaIcon" :label="__('Иконка')">
                            <flux:select.option value="rectangle-group">{{ __('Зал') }}</flux:select.option>
                            <flux:select.option value="sparkles">{{ __('VIP') }}</flux:select.option>
                            <flux:select.option value="sun">{{ __('Терраса') }}</flux:select.option>
                            <flux:select.option value="map-pin">{{ __('Другое') }}</flux:select.option>
                        </flux:select>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <flux:button icon="arrow-left" type="button" wire:click="goToStep(3)">{{ __('Назад') }}</flux:button>
                        <flux:button icon="arrow-right" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createArea">
                            {{ __('Дальше') }}
                        </flux:button>
                    </div>
                </form>
            @elseif ($step === 5)
                <form wire:submit="createServicePoints" class="grid gap-5">
                    <div class="flex flex-col gap-1">
                        <flux:badge icon="squares-2x2" color="amber">{{ __('Шаг 5') }}</flux:badge>
                        <flux:heading size="xl">{{ __('Добавьте первые столы') }}</flux:heading>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Система создаст несколько столов сразу. QR позже останется тем же, даже если стол переименовать или перенести.') }}</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <flux:input wire:model="tableCount" :label="__('Сколько столов?')" type="number" required min="1" max="20" />
                        <flux:input wire:model="tablePrefix" :label="__('Как назвать?')" type="text" required maxlength="40" />
                        <flux:input wire:model="tableCapacity" :label="__('Гостей за столом')" type="number" required min="1" max="50" />
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <flux:button icon="arrow-left" type="button" wire:click="goToStep(4)">{{ __('Назад') }}</flux:button>
                        <flux:button icon="arrow-right" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createServicePoints">
                            {{ __('Создать столы') }}
                        </flux:button>
                    </div>
                </form>
            @elseif ($step === 6)
                <div class="grid gap-5">
                    <div class="flex flex-col gap-1">
                        <flux:badge icon="qr-code" color="amber">{{ __('Шаг 6') }}</flux:badge>
                        <flux:heading size="xl">{{ __('Создайте QR для столов') }}</flux:heading>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Один стол получает один постоянный QR. В ссылке нет номера стола, адреса или ID ресторана.') }}</p>
                    </div>

                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-600 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-300">
                        {{ trans_choice('{1} Будет создан :count постоянный QR.|[2,*] Будет создано :count постоянных QR.', count($servicePointIds), ['count' => count($servicePointIds)]) }}
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <flux:button icon="arrow-left" type="button" wire:click="goToStep(5)">{{ __('Назад') }}</flux:button>
                        <flux:button icon="qr-code" variant="primary" type="button" wire:click="generateQrCodes" wire:loading.attr="disabled" wire:target="generateQrCodes">
                            {{ __('Сгенерировать QR') }}
                        </flux:button>
                    </div>
                </div>
            @elseif ($step === 7)
                <form wire:submit="createStarterMenu" class="grid gap-5">
                    <div class="flex flex-col gap-1">
                        <flux:badge icon="book-open" color="amber">{{ __('Шаг 7') }}</flux:badge>
                        <flux:heading size="xl">{{ __('Добавьте первое меню') }}</flux:heading>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Для проверки достаточно одного раздела и одного блюда. Подробное меню можно дополнять позже.') }}</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <flux:input wire:model="menuName" :label="__('Название меню')" type="text" required maxlength="160" />
                        <flux:input wire:model="categoryName" :label="__('Раздел меню')" type="text" required maxlength="160" />
                        <flux:input wire:model="itemName" :label="__('Первое блюдо')" type="text" required maxlength="180" />
                        <flux:input wire:model="itemPrice" :label="__('Цена')" type="number" required min="0" max="999999.99" step="0.01" />
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <flux:button icon="arrow-left" type="button" wire:click="goToStep(6)">{{ __('Назад') }}</flux:button>
                        <flux:button icon="check" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createStarterMenu">
                            {{ __('Добавить меню') }}
                        </flux:button>
                    </div>
                </form>
            @else
                <div class="grid gap-5">
                    <div class="flex flex-col gap-1">
                        <flux:badge icon="check-circle" color="green">{{ __('Готово') }}</flux:badge>
                        <flux:heading size="xl">{{ __('Ресторан готов к проверке') }}</flux:heading>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Откройте гостевую страницу и убедитесь, что QR ведёт на созданную точку и показывает первое меню.') }}</p>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        @if ($this->summary['guest_url'])
                            <a href="{{ $this->summary['guest_url'] }}" target="_blank" rel="noopener" class="flex min-h-24 items-center gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 transition hover:bg-white dark:border-zinc-800 dark:bg-zinc-950 dark:hover:bg-zinc-900">
                                <flux:badge icon="book-open" color="green">{{ __('Гость') }}</flux:badge>
                                <span>
                                    <span class="block font-semibold text-zinc-950 dark:text-white">{{ __('Открыть гостевое меню') }}</span>
                                    <span class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400">{{ __('Ссылка содержит только скрытый QR-токен.') }}</span>
                                </span>
                            </a>
                        @endif

                        @if ($this->summary['print_url'])
                            <a href="{{ $this->summary['print_url'] }}" class="flex min-h-24 items-center gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 transition hover:bg-white dark:border-zinc-800 dark:bg-zinc-950 dark:hover:bg-zinc-900" wire:navigate>
                                <flux:badge icon="printer" color="zinc">{{ __('QR') }}</flux:badge>
                                <span>
                                    <span class="block font-semibold text-zinc-950 dark:text-white">{{ __('Напечатать QR') }}</span>
                                    <span class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400">{{ __('Откройте страницу печати наклеек.') }}</span>
                                </span>
                            </a>
                        @endif

                        @if ($this->summary['branch_url'])
                            <a href="{{ $this->summary['branch_url'] }}" class="flex min-h-24 items-center gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 transition hover:bg-white dark:border-zinc-800 dark:bg-zinc-950 dark:hover:bg-zinc-900" wire:navigate>
                                <flux:badge icon="squares-2x2" color="zinc">{{ __('Столы') }}</flux:badge>
                                <span>
                                    <span class="block font-semibold text-zinc-950 dark:text-white">{{ __('Открыть настройки филиала') }}</span>
                                    <span class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400">{{ __('Зоны, столы и QR остаются в обычном CRUD.') }}</span>
                                </span>
                            </a>
                        @endif

                        @if ($this->summary['menu_url'])
                            <a href="{{ $this->summary['menu_url'] }}" class="flex min-h-24 items-center gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 transition hover:bg-white dark:border-zinc-800 dark:bg-zinc-950 dark:hover:bg-zinc-900" wire:navigate>
                                <flux:badge icon="book-open" color="zinc">{{ __('Меню') }}</flux:badge>
                                <span>
                                    <span class="block font-semibold text-zinc-950 dark:text-white">{{ __('Дополнить меню') }}</span>
                                    <span class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400">{{ __('Добавьте блюда, цены, фото и модификаторы позже.') }}</span>
                                </span>
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</section>
