@props(['item', 'menuOptions', 'editingItemCategoryOptions', 'activeKitchenDepartmentOptions', 'languageOptions', 'canChangePrices', 'canChangeAvailability', 'allergenOptions', 'dietaryLabelOptions', 'itemImageUploads', 'imagePresentationContext', 'imagePresentationForm'])

<form wire:submit="updateItem" novalidate class="grid gap-4">
    @error('editingItemVersion')
        <p role="alert" class="rounded-control border border-warning-border bg-warning-surface p-3 text-sm text-warning-foreground">{{ $message }}</p>
    @enderror
                                                    <div class="grid gap-3 md:grid-cols-2">
                                                        <flux:select wire:model.live="editingItemMenuId" :label="__('menu.guest.title')">
                                                            @foreach ($menuOptions as $option)
                                                                <flux:select.option wire:key="item-menu-edit-{{ $item['id'] }}-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                                                            @endforeach
                                                        </flux:select>

                                                        <flux:select wire:model="editingItemCategoryId" :label="__('ui.organizations.brands.branches.menu.index.category')">
                                                            @foreach ($editingItemCategoryOptions as $option)
                                                                <flux:select.option wire:key="item-category-edit-{{ $item['id'] }}-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                                                            @endforeach
                                                        </flux:select>
                                                    </div>



                                                    <flux:select wire:model="editingItemKitchenDepartmentId" :label="__('reports.csv.kitchen_department')">
                                                        <flux:select.option value="">{{ __('ui.livewire.organizations.brands.branches.menu.index.default_kitchen') }}</flux:select.option>
                                                        @foreach ($activeKitchenDepartmentOptions as $option)
                                                            <flux:select.option wire:key="item-department-edit-{{ $item['id'] }}-{{ $option['value'] }}" value="{{ $option['value'] }}">
                                                                {{ $option['label'] }}{{ $option['is_active'] ? '' : ' - '.__('ui.status.inactive') }}
                                                            </flux:select.option>
                                                        @endforeach
                                                    </flux:select>



                                                    <x-menu.translation-fields
                                                        id-prefix="edit-menu-item-{{ $item['id'] }}"
                                                        model="editingItemTranslations"
 base-name-model="editingItemName"
 base-description-model="editingItemDescription"
                                                        :language-options="$languageOptions"
                                                        :name-max="180"
                                                        :description-max="1200"
                                                    />

                                                    <div class="grid gap-3 md:grid-cols-4">
                                                        @if ($canChangePrices)
                                                            <flux:input wire:model="editingItemPrice" :label="__('guest.cart.price')" type="number" required min="0" max="999999.99" step="0.01" />
                                                        @endif

                                                        <flux:input wire:model="editingItemWeight" :label="__('reports.csv.weight')" type="number" min="0" step="0.01" />
                                                        <flux:input wire:model="editingItemVolume" :label="__('reports.csv.volume')" type="number" min="0" step="0.01" />
                                                        <flux:input wire:model="editingItemCalories" :label="__('reports.csv.calories')" type="number" min="0" max="999999" />
                                                        <flux:input wire:model="editingItemSortOrder" :label="__('ui.departments.dashboard.sort')" type="number" required min="0" max="9999" />
                                                    </div>

                                                    <x-menu.item-label-fields
                                                        id-prefix="edit-menu-item-{{ $item['id'] }}"
                                                        allergens-model="editingItemAllergens"
                                                        dietary-labels-model="editingItemDietaryLabels"
                                                        :allergen-options="$allergenOptions"
                                                        :dietary-label-options="$dietaryLabelOptions"
                                                    />

                                                    <x-menu.item-images :item="$item" :pending-uploads="$itemImageUploads[$item['id']] ?? []" :presentation-context="$imagePresentationContext" :presentation-form="$imagePresentationForm" />

                                                    <div class="flex items-center justify-between gap-3">
                                                        @if ($canChangeAvailability)
                                                            <div class="grid gap-3 sm:grid-cols-2">
                                                                <flux:switch wire:model="editingItemIsAvailable" :label="__('menu.guest.available')" />
                                                                <flux:input wire:model="editingItemHiddenUntil" :label="__('menu.admin.hidden_until')" type="datetime-local" />
                                                            </div>
                                                        @endif

                                                        <div class="flex flex-wrap gap-2">
                                                            <flux:button icon="check" variant="primary" type="submit" :disabled="$imagePresentationContext !== []" wire:loading.attr="disabled" wire:target="updateItem">
                                                                {{ __('ui.actions.save') }}
                                                            </flux:button>

                                                            <flux:button icon="x-mark" type="button" wire:click="cancelItemEditing">
                                                                {{ __('ui.actions.cancel') }}
                                                            </flux:button>
                                                        </div>
                                                    </div>
                                                </form>
