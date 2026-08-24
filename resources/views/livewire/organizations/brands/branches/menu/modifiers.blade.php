<div class="grid gap-4">
    <form wire:submit="createModifierGroup" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex items-center justify-between gap-3">
            <flux:heading size="lg">{{ __('ui.organizations.brands.branches.menu.index.new_modifier') }}</flux:heading>
            <flux:button icon="plus" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createModifierGroup">
                {{ __('ui.organizations.brands.branches.menu.index.create') }}
            </flux:button>
        </div>
        <div class="mt-4 grid gap-3">
            <flux:input wire:model="modifierGroupName" :label="__('reports.csv.name')" type="text" required maxlength="160" />
            <x-menu.name-translations
                id-prefix="create-modifier-group"
                model="modifierGroupTranslations"
                :language-options="$languageOptions"
            />
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:input wire:model="modifierGroupMinSelect" :label="__('ui.organizations.brands.branches.menu.index.min')" type="number" required min="0" max="50" />
                <flux:input wire:model="modifierGroupMaxSelect" :label="__('ui.organizations.brands.branches.menu.index.max')" type="number" required min="0" max="50" />
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:input wire:model="modifierGroupSortOrder" :label="__('ui.departments.dashboard.sort')" type="number" required min="0" max="9999" />
                <div class="flex items-end"><flux:switch wire:model="modifierGroupIsRequired" :label="__('guest.cart.required')" /></div>
            </div>
        </div>
    </form>

    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
            <flux:heading size="lg">{{ __('ui.organizations.brands.branches.menu.index.modifier_groups') }}</flux:heading>
        </div>

        <div class="grid gap-4 border-b border-zinc-200 p-4 dark:border-zinc-800 lg:grid-cols-2">
            <form wire:submit="createModifierOption" class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-950/60">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('ui.organizations.brands.branches.menu.index.new_option') }}</p>
                    <flux:button icon="plus" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createModifierOption">
                        {{ __('ui.organizations.brands.branches.menu.index.create') }}
                    </flux:button>
                </div>

                <div class="mt-3 grid gap-3">
                    <flux:select wire:model="modifierOptionGroupId" :label="__('ui.organizations.brands.branches.menu.index.modifier_group')">
                        @forelse ($modifierGroupOptions as $option)
                            <flux:select.option wire:key="modifier-option-group-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                        @empty
                            <flux:select.option value="">{{ __('ui.organizations.brands.branches.menu.index.create_a_modifier_group_first') }}</flux:select.option>
                        @endforelse
                    </flux:select>

                    <flux:input wire:model="modifierOptionName" :label="__('reports.csv.name')" type="text" required maxlength="160" />
                    <x-menu.name-translations
                        id-prefix="create-modifier-option"
                        model="modifierOptionTranslations"
                        :language-options="$languageOptions"
                    />

                    <div class="grid gap-3 sm:grid-cols-2">
                        @if ($canChangePrices)
                            <flux:input wire:model="modifierOptionPriceDelta" :label="__('ui.organizations.brands.branches.menu.index.price_change')" type="number" required min="-999999.99" max="999999.99" step="0.01" />
                        @endif

                        <flux:input wire:model="modifierOptionSortOrder" :label="__('ui.departments.dashboard.sort')" type="number" required min="0" max="9999" />
                    </div>

                    @if ($canChangeAvailability)
                        <flux:switch wire:model="modifierOptionIsAvailable" :label="__('menu.guest.available')" />
                    @endif
                </div>
            </form>

            <form wire:submit="attachModifierGroupToItem" class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-950/60">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('ui.organizations.brands.branches.menu.index.assign_to_dish') }}</p>
                    <flux:button icon="link" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="attachModifierGroupToItem">
                        {{ __('ui.organizations.brands.branches.menu.index.assign') }}
                    </flux:button>
                </div>

                <div class="mt-3 grid gap-3">
                    <flux:select wire:model.live="modifierItemMenuId" :label="__('menu.guest.title')">
                        @forelse ($menuOptions as $option)
                            <flux:select.option wire:key="modifier-item-menu-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                        @empty
                            <flux:select.option value="">{{ __('ui.organizations.brands.branches.menu.index.create_a_menu_first') }}</flux:select.option>
                        @endforelse
                    </flux:select>

                    <flux:select wire:model="modifierItemId" :label="__('ui.actions.analytics.buildbasicanalyticsdashboardaction.dish')">
                        @forelse ($modifierItemOptions as $option)
                            <flux:select.option wire:key="modifier-item-dish-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                        @empty
                            <flux:select.option value="">{{ __('ui.organizations.brands.branches.menu.index.create_a_dish_first') }}</flux:select.option>
                        @endforelse
                    </flux:select>

                    <flux:select wire:model="modifierItemGroupId" :label="__('ui.organizations.brands.branches.menu.index.modifier_group')">
                        @forelse ($modifierGroupOptions as $option)
                            <flux:select.option wire:key="modifier-item-group-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                        @empty
                            <flux:select.option value="">{{ __('ui.organizations.brands.branches.menu.index.create_a_modifier_group_first') }}</flux:select.option>
                        @endforelse
                    </flux:select>
                </div>
            </form>
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
            @forelse ($modifierGroupRows as $modifierGroup)
                <div wire:key="modifier-group-{{ $modifierGroup['id'] }}" class="px-4 py-4">
                    @if ($editingModifierGroupId === $modifierGroup['id'])
                        <form wire:submit="updateModifierGroup" class="grid gap-3 md:grid-cols-[1fr_100px_100px_120px_auto] md:items-end">
                            <flux:input wire:model="editingModifierGroupName" :label="__('reports.csv.name')" type="text" required maxlength="160" />
                            <x-menu.name-translations
                                class="md:col-span-full"
                                id-prefix="edit-modifier-group-{{ $modifierGroup['id'] }}"
                                model="editingModifierGroupTranslations"
                                :language-options="$languageOptions"
                            />
                            <flux:input wire:model="editingModifierGroupMinSelect" :label="__('ui.organizations.brands.branches.menu.index.min')" type="number" required min="0" max="50" />
                            <flux:input wire:model="editingModifierGroupMaxSelect" :label="__('ui.organizations.brands.branches.menu.index.max')" type="number" required min="0" max="50" />
                            <flux:input wire:model="editingModifierGroupSortOrder" :label="__('ui.departments.dashboard.sort')" type="number" required min="0" max="9999" />

                            <div class="flex flex-wrap items-center gap-2">
                                <flux:switch wire:model="editingModifierGroupIsRequired" :label="__('guest.cart.required')" />
                                <flux:button icon="check" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="updateModifierGroup">
                                    {{ __('ui.actions.save') }}
                                </flux:button>
                                <flux:button icon="x-mark" type="button" wire:click="cancelModifierGroupEditing">
                                    {{ __('ui.actions.cancel') }}
                                </flux:button>
                            </div>
                        </form>
                    @else
                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="truncate text-base font-semibold text-zinc-950 dark:text-white">{{ $modifierGroup['name'] }}</h2>
                                    @if ($modifierGroup['is_required'])
                                        <flux:badge color="amber">{{ __('guest.cart.required') }}</flux:badge>
                                    @else
                                        <flux:badge color="zinc">{{ __('guest.cart.optional') }}</flux:badge>
                                    @endif
                                    <flux:badge>{{ __('ui.organizations.brands.branches.menu.index.select') }} {{ $modifierGroup['min_select'] }}–{{ $modifierGroup['max_select'] }}</flux:badge>
                                    <flux:badge>{{ trans_choice('ui.organizations.brands.branches.menu.index.dish_dishes', $modifierGroup['items_count'], ['count' => $modifierGroup['items_count']]) }}</flux:badge>
                                </div>
                                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('ui.departments.dashboard.sort') }} {{ $modifierGroup['sort_order'] }}</p>
                                <dl class="mt-2 grid gap-2 text-sm sm:grid-cols-3">
                                    @foreach ($languageOptions as $languageCode => $languageLabel)
                                        <div wire:key="modifier-group-{{ $modifierGroup['id'] }}-translation-{{ $languageCode }}">
                                            <dt class="font-medium text-zinc-500 dark:text-zinc-400">{{ $languageLabel }}</dt>
                                            <dd class="text-zinc-900 dark:text-zinc-100">{{ $modifierGroup['translations'][$languageCode] }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </div>

                            <div class="flex flex-wrap gap-2 md:justify-end">
                                <flux:button icon="pencil" type="button" wire:click="startEditingModifierGroup({{ $modifierGroup['id'] }})">
                                    {{ __('guest.cart.edit_item') }}
                                </flux:button>
                                <flux:button icon="trash" type="button" variant="danger" wire:click="deleteModifierGroup({{ $modifierGroup['id'] }})">
                                    {{ __('ui.actions.delete') }}
                                </flux:button>
                            </div>
                        </div>
                    @endif

                    <div class="mt-4 grid gap-2">
                        @forelse ($modifierGroup['options'] as $modifierOption)
                            <div wire:key="modifier-option-{{ $modifierOption['id'] }}" class="rounded-md border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-800 dark:bg-zinc-950/60">
                                @if ($editingModifierOptionId === $modifierOption['id'])
                                    <form wire:submit="updateModifierOption" class="grid gap-3 md:grid-cols-[1fr_140px_120px_auto] md:items-end">
                                        <flux:input wire:model="editingModifierOptionName" :label="__('reports.csv.name')" type="text" required maxlength="160" />
                                        <x-menu.name-translations
                                            class="md:col-span-full"
                                            id-prefix="edit-modifier-option-{{ $modifierOption['id'] }}"
                                            model="editingModifierOptionTranslations"
                                            :language-options="$languageOptions"
                                        />

                                        @if ($canChangePrices)
                                            <flux:input wire:model="editingModifierOptionPriceDelta" :label="__('ui.organizations.brands.branches.menu.index.price_change')" type="number" required min="-999999.99" max="999999.99" step="0.01" />
                                        @endif

                                        <flux:input wire:model="editingModifierOptionSortOrder" :label="__('ui.departments.dashboard.sort')" type="number" required min="0" max="9999" />

                                        <div class="flex flex-wrap items-center gap-2">
                                            @if ($canChangeAvailability)
                                                <flux:switch wire:model="editingModifierOptionIsAvailable" :label="__('menu.guest.available')" />
                                            @endif
                                            <flux:button icon="check" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="updateModifierOption">
                                                {{ __('ui.actions.save') }}
                                            </flux:button>
                                            <flux:button icon="x-mark" type="button" wire:click="cancelModifierOptionEditing">
                                                {{ __('ui.actions.cancel') }}
                                            </flux:button>
                                        </div>
                                        <dl class="grid gap-2 text-sm sm:grid-cols-3 md:w-full">
                                            @foreach ($languageOptions as $languageCode => $languageLabel)
                                                <div wire:key="modifier-option-{{ $modifierOption['id'] }}-translation-{{ $languageCode }}">
                                                    <dt class="font-medium text-zinc-500 dark:text-zinc-400">{{ $languageLabel }}</dt>
                                                    <dd class="text-zinc-900 dark:text-zinc-100">{{ $modifierOption['translations'][$languageCode] }}</dd>
                                                </div>
                                            @endforeach
                                        </dl>
                                    </form>
                                @else
                                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-medium text-zinc-950 dark:text-white">{{ $modifierOption['name'] }}</span>
                                            <flux:badge>{{ $modifierOption['formatted_price_delta'] }}</flux:badge>
                                            @if ($modifierOption['is_available'])
                                                <flux:badge color="green">{{ __('menu.guest.available') }}</flux:badge>
                                            @else
                                                <flux:badge color="zinc">{{ __('menu.guest.unavailable') }}</flux:badge>
                                            @endif
                                            <flux:badge>{{ __('ui.departments.dashboard.sort') }} {{ $modifierOption['sort_order'] }}</flux:badge>
                                        </div>

                                        <div class="flex flex-wrap gap-2 md:justify-end">
                                            <flux:button icon="pencil" type="button" wire:click="startEditingModifierOption({{ $modifierOption['id'] }})">
                                                {{ __('guest.cart.edit_item') }}
                                            </flux:button>
                                            <flux:button icon="trash" type="button" variant="danger" wire:click="deleteModifierOption({{ $modifierOption['id'] }})">
                                                {{ __('ui.actions.delete') }}
                                            </flux:button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('menu.empty.no_options') }}</p>
                        @endforelse
                    </div>
                </div>
            @empty
                <div class="px-4 py-8 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('menu.empty.no_modifier_groups') }}
                </div>
            @endforelse
        </div>
    </div>
</div>
