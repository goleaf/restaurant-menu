<section data-page="branch-service-points" class="flex h-full w-full flex-1 flex-col gap-6">
    <header class="flex flex-col gap-3">
        <flux:button icon="arrow-left" :href="route('organizations.brands.branches.index', [$organization, $brand])" wire:navigate>
            {{ __('Филиалы') }}
            <span class="sr-only">{{ __('Branches') }}</span>
        </flux:button>

        <div class="flex flex-col gap-1">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $organization->name }} / {{ $brand->name }} / {{ $branch->name }}</p>
            <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">
                {{ __('Столы и места') }}
                <span class="sr-only">{{ __('Service points') }}</span>
            </h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Здесь добавляют физические места: столы, барные места, комнаты и точки самовывоза.') }}</p>
        </div>
    </header>

    @if ($canManageServicePoints)
        <x-ui.card
            :heading="__('Шаг 3: добавьте столы')"
            :description="__('Выберите тип места, задайте понятное название и при необходимости номер на наклейке.')"
        >

            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($this->quickCreateOptions as $option)
                    <flux:button
                        wire:key="service-point-preset-{{ $option['type'] }}"
                        :icon="$option['icon']"
                        type="button"
                        wire:click="prepareCreate('{{ $option['type'] }}')"
                        class="min-h-14 justify-start"
                    >
                        {{ $option['label'] }}
                    </flux:button>
                @endforeach
            </div>

            <form wire:submit="create" class="mt-4 grid gap-4 md:grid-cols-2">
                <flux:input wire:model="name" :label="__('Название')" type="text" required maxlength="160" />
                <flux:input wire:model="displayNumber" :label="__('Номер на наклейке')" type="text" maxlength="80" />

                <flux:select wire:model="type" :label="__('Тип места')">
                    @foreach ($this->servicePointTypeOptions as $value => $label)
                        <flux:select.option wire:key="service-point-type-{{ $value }}" value="{{ $value }}">{{ __($label) }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="areaNodeId" :label="__('Зона')">
                    @foreach ($this->areaOptions as $option)
                        <flux:select.option wire:key="service-point-area-create-{{ $option['value'] === '' ? 'none' : $option['value'] }}" value="{{ $option['value'] }}">
                            {{ $option['label'] }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="icon" :label="__('Иконка')">
                    @foreach ($this->iconOptions as $value => $label)
                        <flux:select.option wire:key="service-point-icon-{{ $value }}" value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="capacity" :label="__('Сколько гостей')" type="number" required min="1" max="999" />

                <div class="flex items-end justify-between gap-4 md:col-span-2">
                    <flux:switch wire:model="isActive" :label="__('Можно использовать')" />

                    <flux:button icon="plus" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="create">
                        {{ __('Добавить место') }}
                    </flux:button>
                </div>
            </form>
        </x-ui.card>
    @endif

    <x-ui.card padding="none" class="overflow-hidden">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
            <flux:heading size="lg">
                {{ __('Столы и места филиала') }}
                <span class="sr-only">{{ __('Service points in this branch') }}</span>
            </flux:heading>
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
            @forelse ($this->servicePoints as $servicePoint)
                <div wire:key="service-point-{{ $servicePoint->id }}" class="grid gap-4 px-4 py-4 md:grid-cols-[1fr_auto] md:items-center">
                    @if ($editingServicePointId === $servicePoint->id)
                        <form wire:submit="update" class="grid gap-3 md:col-span-2 md:grid-cols-2">
                            <flux:input wire:model="editingName" :label="__('Название')" type="text" required maxlength="160" />
                            <flux:input wire:model="editingDisplayNumber" :label="__('Номер на наклейке')" type="text" maxlength="80" />

                            <flux:select wire:model="editingType" :label="__('Тип места')">
                                @foreach ($this->servicePointTypeOptions as $value => $label)
                                    <flux:select.option wire:key="editing-service-point-type-{{ $servicePoint->id }}-{{ $value }}" value="{{ $value }}">{{ __($label) }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model="editingAreaNodeId" :label="__('Зона')">
                                @foreach ($this->areaOptions as $option)
                                    <flux:select.option wire:key="editing-service-point-area-{{ $servicePoint->id }}-{{ $option['value'] === '' ? 'none' : $option['value'] }}" value="{{ $option['value'] }}">
                                        {{ $option['label'] }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model="editingIcon" :label="__('Иконка')">
                                @foreach ($this->iconOptions as $value => $label)
                                    <flux:select.option wire:key="editing-service-point-icon-{{ $servicePoint->id }}-{{ $value }}" value="{{ $value }}">{{ $label }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:input wire:model="editingCapacity" :label="__('Сколько гостей')" type="number" required min="1" max="999" />

                            <div class="flex items-end justify-between gap-4 md:col-span-2">
                                <flux:switch wire:model="editingIsActive" :label="__('Можно использовать')" />

                                <div class="flex flex-wrap gap-2">
                                    <flux:button icon="check" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="update">
                                        {{ __('Сохранить') }}
                                    </flux:button>

                                    <flux:button icon="x-mark" type="button" wire:click="cancelEditing">
                                        {{ __('Отмена') }}
                                    </flux:button>
                                </div>
                            </div>
                        </form>
                    @else
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-ui.service-point-icon :type="$servicePoint->type" :icon="$servicePoint->icon" :label="__($servicePoint->type->label())" :active="$servicePoint->is_active" />

                                <h2 class="truncate text-base font-semibold text-zinc-950 dark:text-white">{{ $servicePoint->name }}</h2>

                                <x-ui.status-badge tone="muted">{{ __($servicePoint->type->label()) }}</x-ui.status-badge>
                                <x-ui.status-badge :tone="$servicePoint->status->badgeColor()" dot>{{ __($servicePoint->status->label()) }}</x-ui.status-badge>

                                @if ($servicePoint->is_active)
                                    <x-ui.status-badge tone="success">{{ __('Работает') }}</x-ui.status-badge>
                                @else
                                    <x-ui.status-badge tone="muted">{{ __('Выключено') }}</x-ui.status-badge>
                                @endif

                                @if ($servicePoint->activeTableSession)
                                    <x-ui.status-badge tone="info">
                                        {{ __('Стол открыт') }}
                                        <span class="sr-only">{{ __('Active session') }}</span>
                                    </x-ui.status-badge>
                                @endif

                                @if ($canGenerateQr)
                                    @if ($servicePoint->activeQrCode)
                                        <x-ui.status-badge tone="success" icon="qr-code">
                                            {{ __('QR готов') }}
                                            <span class="sr-only">{{ __('QR active') }}</span>
                                        </x-ui.status-badge>
                                    @else
                                        <x-ui.status-badge tone="muted" icon="qr-code">
                                            {{ __('QR нет') }}
                                            <span class="sr-only">{{ __('No QR') }}</span>
                                        </x-ui.status-badge>
                                    @endif
                                @endif
                            </div>

                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Номер') }}: {{ $servicePoint->display_number ?: __('не указан') }}
                            </p>

                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Зона') }}: {{ $servicePoint->areaNode?->name ?? __('Без зоны') }} / {{ __('Гостей') }}: {{ $servicePoint->capacity }}
                            </p>

                            @if ($servicePoint->activeTableSession)
                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Открыт') }}:
                                    {{ $servicePoint->activeTableSession->started_at?->format('Y-m-d H:i') ?? __('сейчас') }}
                                </p>
                            @endif

                            @if ($canGenerateQr && $servicePoint->activeQrCode)
                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('QR') }}: {{ $servicePoint->activeQrCode->short_code }} / {{ __($servicePoint->activeQrCode->status->label()) }}
                                </p>
                            @endif
                        </div>

                        <div class="flex flex-wrap gap-2 md:justify-end">
                            @if ($canOpenTable)
                                @if ($servicePoint->activeTableSession)
                                    <flux:button icon="check" type="button" disabled>
                                        {{ __('Стол открыт') }}
                                        <span class="sr-only">{{ __('Table opened') }}</span>
                                    </flux:button>
                                @elseif ($servicePoint->is_active)
                                    <flux:button icon="play" variant="primary" type="button" wire:click="openTable({{ $servicePoint->id }})" wire:loading.attr="disabled" wire:target="openTable({{ $servicePoint->id }})">
                                        {{ __('Открыть стол') }}
                                        <span class="sr-only">{{ __('Open table') }}</span>
                                    </flux:button>
                                @else
                                    <flux:button icon="lock-closed" type="button" disabled>
                                        {{ __('Место выключено') }}
                                        <span class="sr-only">{{ __('Place inactive') }}</span>
                                    </flux:button>
                                @endif
                            @endif

                            @if ($canChangeServicePointStatus)
                                <form wire:submit="changeStatus({{ $servicePoint->id }})" class="flex flex-wrap items-end gap-2">
                                    <flux:select wire:model="statusSelections.{{ $servicePoint->id }}" :label="__('Статус')">
                                        @foreach ($this->servicePointStatusOptions as $value => $label)
                                            <flux:select.option wire:key="service-point-status-{{ $servicePoint->id }}-{{ $value }}" value="{{ $value }}">{{ __($label) }}</flux:select.option>
                                        @endforeach
                                    </flux:select>

                                    <flux:button icon="arrow-path" type="submit" wire:loading.attr="disabled" wire:target="changeStatus({{ $servicePoint->id }})">
                                        {{ __('Сменить') }}
                                    </flux:button>
                                </form>
                            @endif

                            @if ($canGenerateQr)
                                @if ($servicePoint->activeQrCode)
                                    <flux:button
                                        icon="qr-code"
                                        :href="route('organizations.brands.branches.service-points.qr.show', [$organization, $brand, $branch, $servicePoint, $servicePoint->activeQrCode])"
                                        wire:navigate
                                    >
                                        {{ __('Показать QR') }}
                                        <span class="sr-only">{{ __('Show QR') }}</span>
                                    </flux:button>
                                @else
                                    <flux:button icon="qr-code" variant="primary" type="button" wire:click="generateQr({{ $servicePoint->id }})" wire:loading.attr="disabled" wire:target="generateQr({{ $servicePoint->id }})">
                                        {{ __('Создать QR') }}
                                        <span class="sr-only">{{ __('Create QR') }}</span>
                                    </flux:button>
                                @endif
                            @endif

                            @if ($canManageServicePoints)
                                @if ($servicePoint->is_active)
                                    <flux:button icon="eye-slash" type="button" wire:click="disable({{ $servicePoint->id }})">
                                        {{ __('Выключить') }}
                                    </flux:button>
                                @else
                                    <flux:button icon="eye" type="button" wire:click="enable({{ $servicePoint->id }})">
                                        {{ __('Включить') }}
                                    </flux:button>
                                @endif

                                <flux:button icon="pencil" type="button" wire:click="startEditing({{ $servicePoint->id }})">
                                    {{ __('Изменить') }}
                                </flux:button>
                            @endif
                        </div>

                        @if ($canGenerateQr && $shownQrServicePointId === $servicePoint->id)
                            <div class="border-t border-zinc-200 pt-4 md:col-span-2 dark:border-zinc-800">
                                @if ($servicePoint->activeQrCode)
                                    <div class="grid gap-3 text-sm md:grid-cols-[1fr_auto] md:items-center">
                                        <div class="min-w-0 space-y-1">
                                            <p class="font-medium text-zinc-950 dark:text-white">{{ __('QR') }} {{ $servicePoint->activeQrCode->short_code }}</p>
                                            <p class="break-all text-zinc-600 dark:text-zinc-300">{{ $servicePoint->activeQrCode->publicPath() }}</p>
                                            <p class="text-zinc-500 dark:text-zinc-400">{{ __('Статус') }}: {{ __($servicePoint->activeQrCode->status->label()) }}</p>
                                        </div>

                                        <flux:button icon="x-mark" type="button" wire:click="hideQr">
                                            {{ __('Скрыть') }}
                                        </flux:button>
                                    </div>
                                @else
                                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Активного QR пока нет.') }}</p>
                                @endif
                            </div>
                        @endif
                    @endif
                </div>
            @empty
                <div class="p-4">
                    <x-ui.empty-state
                        icon="squares-2x2"
                        :heading="__('Столов и мест пока нет')"
                        :description="__('Начните с кнопки “Стол”. QR позже привяжется к месту и не изменится при переименовании или переносе.')"
                    />
                    <span class="sr-only">{{ __('No service points yet.') }}</span>
                </div>
            @endforelse
        </div>
    </x-ui.card>
</section>
