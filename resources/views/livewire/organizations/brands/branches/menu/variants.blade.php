<section data-section="menu-item-variants" class="grid gap-4">
    <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
        <flux:heading size="lg">{{ __('menu.variants.admin.title') }}</flux:heading>
        <flux:text class="mt-1">{{ __('menu.variants.admin.description') }}</flux:text>

        <div class="mt-4 grid gap-3 md:grid-cols-2">
            <flux:select wire:model.live="variantMenuId" :label="__('menu.guest.title')">
                @forelse ($menuOptions as $option)
                    <flux:select.option wire:key="variant-menu-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                @empty
                    <flux:select.option value="">{{ __('ui.organizations.brands.branches.menu.index.create_a_menu_first') }}</flux:select.option>
                @endforelse
            </flux:select>

            <flux:select wire:model.live="variantItemId" :label="__('ui.actions.analytics.buildbasicanalyticsdashboardaction.dish')">
                @forelse ($itemOptions as $option)
                    <flux:select.option wire:key="variant-item-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                @empty
                    <flux:select.option value="">{{ __('ui.organizations.brands.branches.menu.index.create_a_dish_first') }}</flux:select.option>
                @endforelse
            </flux:select>
        </div>
    </div>

    @if ($variantItemId !== '')
        <form wire:submit="createVariant" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <flux:heading size="lg">{{ __('menu.variants.admin.new') }}</flux:heading>
                <flux:button icon="plus" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createVariant">
                    {{ __('ui.organizations.brands.branches.menu.index.create') }}
                </flux:button>
            </div>

            <div class="mt-4 grid gap-3 md:grid-cols-2">
                <flux:select wire:model="variantType" :label="__('menu.variants.admin.type')">
                    @foreach ($variantTypeOptions as $value => $label)
                        <flux:select.option wire:key="new-variant-type-{{ $value }}" value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="variantName" :label="__('reports.csv.name')" required maxlength="160" />
                @if ($canChangePrices)
                    <flux:input wire:model="variantPrice" :label="__('guest.cart.price')" type="number" required min="0" max="999999.99" step="0.01" />
                @endif
                <flux:input wire:model="variantSortOrder" :label="__('ui.departments.dashboard.sort')" type="number" required min="0" max="9999" />
                <flux:input wire:model="variantWeight" :label="__('menu.variants.admin.weight')" type="number" min="0" max="999999.99" step="0.01" />
                <flux:input wire:model="variantVolume" :label="__('menu.variants.admin.volume')" type="number" min="0" max="999999.99" step="0.01" />
            </div>

            <fieldset class="mt-4 rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                <legend class="px-1 text-sm font-semibold text-zinc-950 dark:text-white">{{ __('menu.variants.admin.translations') }}</legend>
                <div class="mt-2 grid gap-3 md:grid-cols-3">
                    @foreach ($languageOptions as $languageCode => $languageLabel)
                        <flux:input wire:key="new-variant-translation-{{ $languageCode }}" wire:model="variantTranslations.{{ $languageCode }}" :label="$languageLabel" maxlength="160" />
                    @endforeach
                </div>
            </fieldset>

            <div class="mt-4 flex flex-wrap gap-4">
                <flux:switch wire:model="variantIsDefault" :label="__('menu.variants.admin.default')" />
                @if ($canChangeAvailability)
                    <flux:switch wire:model="variantIsAvailable" :label="__('menu.guest.available')" />
                @endif
            </div>
        </form>

        <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                <flux:heading size="lg">{{ __('menu.variants.admin.existing') }}</flux:heading>
            </div>

            <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @forelse ($variantRows as $variant)
                    <article wire:key="menu-item-variant-{{ $variant['id'] }}" class="p-4">
                        @if ($editingVariantId === $variant['id'])
                            <form wire:submit="updateVariant" class="grid gap-3">
                                <div class="grid gap-3 md:grid-cols-2">
                                    <flux:select wire:model="editingVariantType" :label="__('menu.variants.admin.type')">
                                        @foreach ($variantTypeOptions as $value => $label)
                                            <flux:select.option wire:key="editing-variant-{{ $variant['id'] }}-type-{{ $value }}" value="{{ $value }}">{{ $label }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:input wire:model="editingVariantName" :label="__('reports.csv.name')" required maxlength="160" />
                                    @if ($canChangePrices)
                                        <flux:input wire:model="editingVariantPrice" :label="__('guest.cart.price')" type="number" required min="0" max="999999.99" step="0.01" />
                                    @endif
                                    <flux:input wire:model="editingVariantSortOrder" :label="__('ui.departments.dashboard.sort')" type="number" required min="0" max="9999" />
                                    <flux:input wire:model="editingVariantWeight" :label="__('menu.variants.admin.weight')" type="number" min="0" max="999999.99" step="0.01" />
                                    <flux:input wire:model="editingVariantVolume" :label="__('menu.variants.admin.volume')" type="number" min="0" max="999999.99" step="0.01" />
                                </div>

                                <fieldset class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                                    <legend class="px-1 text-sm font-semibold text-zinc-950 dark:text-white">{{ __('menu.variants.admin.translations') }}</legend>
                                    <div class="mt-2 grid gap-3 md:grid-cols-3">
                                        @foreach ($languageOptions as $languageCode => $languageLabel)
                                            <flux:input wire:key="editing-variant-translation-{{ $languageCode }}" wire:model="editingVariantTranslations.{{ $languageCode }}" :label="$languageLabel" maxlength="160" />
                                        @endforeach
                                    </div>
                                </fieldset>

                                <div class="flex flex-wrap items-center gap-3">
                                    <flux:switch wire:model="editingVariantIsDefault" :label="__('menu.variants.admin.default')" />
                                    @if ($canChangeAvailability)
                                        <flux:switch wire:model="editingVariantIsAvailable" :label="__('menu.guest.available')" />
                                    @endif
                                    <flux:button icon="check" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="updateVariant">{{ __('ui.actions.save') }}</flux:button>
                                    <flux:button icon="x-mark" type="button" wire:click="cancelVariantEditing">{{ __('ui.actions.cancel') }}</flux:button>
                                </div>
                            </form>
                        @else
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-semibold text-zinc-950 dark:text-white">{{ $variant['name'] }}</span>
                                        <flux:badge>{{ $variant['type'] }}</flux:badge>
                                        <flux:badge>{{ $variant['formatted_price'] }}</flux:badge>
                                        @if ($variant['is_default'])
                                            <flux:badge color="blue">{{ __('menu.variants.admin.default') }}</flux:badge>
                                        @endif
                                        <flux:badge :color="$variant['is_available'] ? 'green' : 'zinc'">
                                            {{ $variant['is_available'] ? __('menu.guest.available') : __('menu.guest.unavailable') }}
                                        </flux:badge>
                                    </div>

                                    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-zinc-500 dark:text-zinc-400">
                                        @if ($variant['weight']) <span>{{ $variant['weight'] }} {{ __('menu.guest.unit_grams') }}</span> @endif
                                        @if ($variant['volume']) <span>{{ $variant['volume'] }} {{ __('menu.guest.unit_liters') }}</span> @endif
                                        <span>{{ __('ui.departments.dashboard.sort') }}: {{ $variant['sort_order'] }}</span>
                                    </div>

                                    <dl class="mt-3 grid gap-1 text-sm sm:grid-cols-3">
                                        @foreach ($languageOptions as $languageCode => $languageLabel)
                                            <div wire:key="variant-{{ $variant['id'] }}-translation-{{ $languageCode }}" class="min-w-0">
                                                <dt class="font-medium text-zinc-500 dark:text-zinc-400">{{ $languageLabel }}</dt>
                                                <dd class="truncate text-zinc-900 dark:text-zinc-100">{{ $variant['translations'][$languageCode] ?: __('menu.variants.admin.fallback') }}</dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                </div>

                                <div class="flex shrink-0 flex-wrap gap-2 lg:justify-end">
                                    <flux:button icon="pencil" type="button" wire:click="startEditingVariant({{ $variant['id'] }})">{{ __('guest.cart.edit_item') }}</flux:button>
                                    <flux:button icon="trash" variant="danger" type="button" wire:click="deleteVariant({{ $variant['id'] }})" wire:confirm="{{ __('menu.variants.admin.delete_confirm') }}">{{ __('ui.actions.delete') }}</flux:button>
                                </div>
                            </div>
                        @endif
                    </article>
                @empty
                    <x-ui.empty-state icon="arrows-right-left" :heading="__('menu.variants.admin.empty')" />
                @endforelse
            </div>
        </div>
    @endif
</section>
