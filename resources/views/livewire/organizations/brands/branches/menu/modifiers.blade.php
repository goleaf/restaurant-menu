<div class="grid gap-4">
    <flux:error name="configuration" />
    <flux:text>{{ __('dish.modifiers.shared_help') }}</flux:text>
    <flux:input wire:model.live.debounce.300ms="groupSearch" :label="__('dish.modifiers.search')" maxlength="100" type="search" />
    @if ($cloningGroupId !== null)
        <form wire:submit="cloneGroup" novalidate class="grid gap-3">
            <flux:heading>{{ __('dish.modifiers.copy_for_dish') }}</flux:heading>
            <flux:text>{{ __('dish.modifiers.copy_help') }}</flux:text>
            <flux:input wire:model="cloneName" :label="__('dish.modifiers.copy_name')" maxlength="160" />
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="cloneGroup">{{ __('dish.modifiers.copy_for_dish') }}</flux:button>
            <flux:button type="button" wire:click="cancelCloningGroup">{{ __('ui.actions.cancel') }}</flux:button>
        </form>
    @endif
    <form wire:submit="createModifierGroup" novalidate class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex items-center justify-between gap-3">
            <flux:heading size="lg">{{ __('ui.organizations.brands.branches.menu.index.new_modifier') }}</flux:heading>
            <flux:button icon="plus" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createModifierGroup">
                {{ __('ui.organizations.brands.branches.menu.index.create') }}
            </flux:button>
        </div>
        <div class="mt-4 grid gap-3">

            <x-menu.name-translations
                id-prefix="create-modifier-group"
                model="group.modifierGroupTranslations"
 base-name-model="group.modifierGroupName"
                :language-options="$languageOptions"
            />
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:input wire:model="group.modifierGroupMinSelect" :label="__('ui.organizations.brands.branches.menu.index.min')" type="number" required min="0" max="50" />
                <flux:input wire:model="group.modifierGroupMaxSelect" :label="__('ui.organizations.brands.branches.menu.index.max')" type="number" required min="0" max="50" />
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:input wire:model="group.modifierGroupSortOrder" :label="__('ui.departments.dashboard.sort')" type="number" required min="0" max="9999" />
                <div class="flex items-end"><flux:switch wire:model="group.modifierGroupIsRequired" :label="__('guest.cart.required')" /></div>
            </div>
        </div>
    </form>

    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
            <flux:heading size="lg">{{ __('ui.organizations.brands.branches.menu.index.modifier_groups') }}</flux:heading>
        </div>

        <div class="grid gap-4 border-b border-zinc-200 p-4 dark:border-zinc-800 lg:grid-cols-2">
            <form wire:submit="createModifierOption" novalidate class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-950/60">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('ui.organizations.brands.branches.menu.index.new_option') }}</p>
                    <flux:button icon="plus" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createModifierOption">
                        {{ __('ui.organizations.brands.branches.menu.index.create') }}
                    </flux:button>
                </div>

                <div class="mt-3 grid gap-3">
                    <flux:select wire:model.live="option.modifierOptionGroupId" :label="__('ui.organizations.brands.branches.menu.index.modifier_group')">
                        @forelse ($optionGroupOptions as $option)
                            <flux:select.option wire:key="modifier-option-group-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                        @empty
                            <flux:select.option value="">{{ __('ui.organizations.brands.branches.menu.index.create_a_modifier_group_first') }}</flux:select.option>
                        @endforelse
                    </flux:select>


                    <x-menu.name-translations
                        id-prefix="create-modifier-option"
                        model="option.modifierOptionTranslations"
 base-name-model="option.modifierOptionName"
                        :language-options="$languageOptions"
                    />

                    <div class="grid gap-3 sm:grid-cols-2">
                        @if ($canChangePrices)
                            <flux:input wire:model="option.modifierOptionPriceDelta" :label="__('ui.organizations.brands.branches.menu.index.price_change')" type="number" required min="-999999.99" max="999999.99" step="0.01" />
                        @endif

                        <flux:input wire:model="option.modifierOptionSortOrder" :label="__('ui.departments.dashboard.sort')" type="number" required min="0" max="9999" />
                    </div>

                    @if ($canChangeAvailability)
                        <flux:switch wire:model="option.modifierOptionIsAvailable" :label="__('menu.guest.available')" />
                    @endif
                </div>
            </form>

            @if ($itemId !== null)
            <form wire:submit="attachModifierGroupToItem" class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-950/60">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('ui.organizations.brands.branches.menu.index.assign_to_dish') }}</p>
                    <flux:button icon="link" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="attachModifierGroupToItem">
                        {{ __('ui.organizations.brands.branches.menu.index.assign') }}
                    </flux:button>
                </div>

                <div class="mt-3 grid gap-3">
                    <flux:select wire:model.live="assignment.modifierItemGroupId" :label="__('ui.organizations.brands.branches.menu.index.modifier_group')">
                        @forelse ($modifierGroupOptions as $option)
                            <flux:select.option wire:key="modifier-item-group-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                        @empty
                            <flux:select.option value="">{{ __('ui.organizations.brands.branches.menu.index.create_a_modifier_group_first') }}</flux:select.option>
                        @endforelse
                    </flux:select>
                </div>
            </form>
            @endif
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
            @forelse ($modifierGroupRows as $modifierGroup)
                <div wire:key="modifier-group-{{ $modifierGroup['id'] }}" class="px-4 py-4">
                    @if (! $modifierGroup['is_configurable'])
                        <flux:callout variant="warning" icon="exclamation-triangle">{{ __('dish.modifiers.not_orderable') }}</flux:callout>
                    @endif
                    @if ($editingModifierGroupId === $modifierGroup['id'])
                        <form wire:submit="updateModifierGroup" wire:confirm="{{ __('dish.modifiers.shared_confirm', ['count' => $editingGroupUses]) }}" novalidate class="grid gap-3 md:grid-cols-[1fr_100px_100px_120px_auto] md:items-end">

                            <x-menu.name-translations
                                class="md:col-span-full"
                                id-prefix="edit-modifier-group-{{ $modifierGroup['id'] }}"
                                model="editingGroup.modifierGroupTranslations"
 base-name-model="editingGroup.modifierGroupName"
                                :language-options="$languageOptions"
                            />
                            <flux:input wire:model="editingGroup.modifierGroupMinSelect" :label="__('ui.organizations.brands.branches.menu.index.min')" type="number" required min="0" max="50" />
                            <flux:input wire:model="editingGroup.modifierGroupMaxSelect" :label="__('ui.organizations.brands.branches.menu.index.max')" type="number" required min="0" max="50" />
                            <flux:input wire:model="editingGroup.modifierGroupSortOrder" :label="__('ui.departments.dashboard.sort')" type="number" required min="0" max="9999" />

                            <div class="flex flex-wrap items-center gap-2">
                                <flux:switch wire:model="editingGroup.modifierGroupIsRequired" :label="__('guest.cart.required')" />
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
                                    <flux:badge>{{ __('ui.organizations.brands.branches.menu.index.select') }} {{ $modifierGroup['min_select'] }}–{{ $modifierGroup['maximum_label'] }}</flux:badge>
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
                                @if ($itemId !== null)
                                    <flux:button icon="document-duplicate" type="button" wire:click="startCloningGroup({{ $modifierGroup['id'] }})">{{ __('dish.modifiers.copy_for_dish') }}</flux:button>
                                    <flux:button icon="link-slash" type="button" wire:click="detachModifierGroupFromItem({{ $itemId }}, {{ $modifierGroup['id'] }})" wire:confirm="{{ __('dish.modifiers.detach_confirm') }}">{{ __('dish.modifiers.detach') }}</flux:button>
                                @endif
                                <flux:button icon="pencil" type="button" wire:click="startEditingModifierGroup({{ $modifierGroup['id'] }})">
                                    {{ __('dish.modifiers.edit_shared') }}
                                </flux:button>
                                <flux:button icon="trash" type="button" variant="primary" color="red" wire:click="deleteModifierGroup({{ $modifierGroup['id'] }})" wire:confirm="{{ __('dish.modifiers.delete_shared_confirm', ['count' => $modifierGroup['items_count']]) }}" class="rm-action-danger">
                                    {{ __('ui.actions.delete') }}
                                </flux:button>
                            </div>
                        </div>
                    @endif

                    @if ($modifierGroup['options_truncated'])
                        <flux:text>{{ __('dish.modifiers.options_limit_notice') }}</flux:text>
                        <div class="flex gap-2">
                            <flux:button type="button" wire:click="previousOptionsPage({{ $modifierGroup['id'] }})" :disabled="! $modifierGroup['options_has_previous']">{{ __('pagination.previous') }}</flux:button>
                            <flux:button type="button" wire:click="nextOptionsPage({{ $modifierGroup['id'] }})" :disabled="! $modifierGroup['options_has_next']">{{ __('pagination.next') }}</flux:button>
                        </div>
                    @endif
                    <div class="mt-4 grid gap-2">
                        @forelse ($modifierGroup['options'] as $modifierOption)
                            <div wire:key="modifier-option-{{ $modifierOption['id'] }}" class="rounded-md border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-800 dark:bg-zinc-950/60">
                                @if ($editingModifierOptionId === $modifierOption['id'])
                                    <form wire:submit="updateModifierOption" wire:confirm="{{ __('dish.modifiers.shared_confirm', ['count' => $editingOptionUses]) }}" novalidate class="grid gap-3 md:grid-cols-[1fr_140px_120px_auto] md:items-end">

                                        <x-menu.name-translations
                                            class="md:col-span-full"
                                            id-prefix="edit-modifier-option-{{ $modifierOption['id'] }}"
                                            model="editingOption.modifierOptionTranslations"
 base-name-model="editingOption.modifierOptionName"
                                            :language-options="$languageOptions"
                                        />

                                        @if ($canChangePrices)
                                            <flux:input wire:model="editingOption.modifierOptionPriceDelta" :label="__('ui.organizations.brands.branches.menu.index.price_change')" type="number" required min="-999999.99" max="999999.99" step="0.01" />
                                        @endif

                                        <flux:input wire:model="editingOption.modifierOptionSortOrder" :label="__('ui.departments.dashboard.sort')" type="number" required min="0" max="9999" />

                                        <div class="flex flex-wrap items-center gap-2">
                                            @if ($canChangeAvailability)
                                                <flux:switch wire:model="editingOption.modifierOptionIsAvailable" :label="__('menu.guest.available')" />
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
                                            <flux:button icon="trash" type="button" variant="primary" color="red" wire:click="deleteModifierOption({{ $modifierOption['id'] }})" wire:confirm="{{ __('dish.modifiers.delete_shared_confirm', ['count' => $modifierGroup['items_count']]) }}" class="rm-action-danger">
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
    <flux:pagination :paginator="$groupPagination" />
</div>
