@props([
    'item', 'menuOptions', 'editingItemCategoryOptions', 'activeKitchenDepartmentOptions', 'languageOptions',
    'canChangePrices', 'canChangeAvailability', 'allergenOptions', 'dietaryLabelOptions',
    'itemImageUploads' => [], 'imagePresentationContext' => [], 'imagePresentationForm' => null,
    'saveAction' => 'updateItem', 'cancelAction' => 'cancelItemEditing', 'embeddedMedia' => true,
    'languageModel' => null, 'saveLabel' => 'ui.actions.save', 'cancelLabel' => 'ui.actions.cancel',
    'localDiscard' => false,
    'priceLabel' => 'guest.cart.price', 'priceHelp' => null,
    'boundedSearch' => false,
])

<form wire:submit="{{ $saveAction }}" novalidate class="grid min-w-0 gap-5" data-dish-main-form>
    @error('editingItemVersion')
        <p role="alert" tabindex="-1" class="rounded-control border border-warning-border bg-warning-surface p-3 text-sm text-warning">{{ $message }}</p>
    @enderror

    <div class="grid min-w-0 gap-3 md:grid-cols-2">
        <flux:select wire:model.live="editingItemForm.itemMenuId" :variant="$boundedSearch ? 'listbox' : null" :searchable="$boundedSearch" :filter="! $boundedSearch" :label="__('menu.guest.title')">
            @if ($boundedSearch)
                <x-slot:search>
                    <flux:select.search wire:model.live.debounce.250ms="search.menu" :aria-label="__('availability.search_menus')" :placeholder="__('availability.search_menus')" maxlength="160" data-menu-search />
                </x-slot:search>
            @endif
            @forelse ($menuOptions as $option)
                <flux:select.option wire:key="item-menu-edit-{{ $item['id'] ?? 'new' }}-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
            @empty
            @endforelse
        </flux:select>
        <flux:select wire:model="editingItemForm.itemCategoryId" :variant="$boundedSearch ? 'listbox' : null" :searchable="$boundedSearch" :filter="! $boundedSearch" :label="__('ui.organizations.brands.branches.menu.index.category')">
            @if ($boundedSearch)
                <x-slot:search>
                    <flux:select.search wire:model.live.debounce.250ms="search.category" :aria-label="__('layout.search').' · '.__('ui.organizations.brands.branches.menu.index.category')" :placeholder="__('layout.search')" maxlength="160" data-menu-search />
                </x-slot:search>
            @endif
            @forelse ($editingItemCategoryOptions as $option)
                <flux:select.option wire:key="item-category-edit-{{ $item['id'] ?? 'new' }}-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
            @empty
            @endforelse
        </flux:select>
    </div>

    <flux:select wire:model="editingItemForm.itemKitchenDepartmentId" :variant="$boundedSearch ? 'listbox' : null" :searchable="$boundedSearch" :filter="! $boundedSearch" :label="__('reports.csv.kitchen_department')">
        @if ($boundedSearch)
            <x-slot:search>
                <flux:select.search wire:model.live.debounce.250ms="search.department" :aria-label="__('layout.search').' · '.__('reports.csv.kitchen_department')" :placeholder="__('layout.search')" maxlength="160" data-menu-search />
            </x-slot:search>
        @endif
        <flux:select.option value="">{{ __('ui.livewire.organizations.brands.branches.menu.index.default_kitchen') }}</flux:select.option>
        @forelse ($activeKitchenDepartmentOptions as $option)
            <flux:select.option wire:key="item-department-edit-{{ $item['id'] ?? 'new' }}-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ $option['label'] }}{{ $option['is_active'] ? '' : ' - '.__('ui.status.inactive') }}</flux:select.option>
        @empty
        @endforelse
    </flux:select>

    <x-menu.translation-fields
        id-prefix="edit-menu-item-{{ $item['id'] ?? 'new' }}"
        model="editingItemForm.itemTranslations"
        base-name-model="editingItemForm.itemName"
        base-description-model="editingItemForm.itemDescription"
        :language-model="$languageModel"
        :language-options="$languageOptions"
        :name-max="180"
        :description-max="1200"
    />

    <div class="grid min-w-0 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @if ($canChangePrices)
            <flux:input wire:model="editingItemForm.itemPrice" :label="__($priceLabel)" :description="$priceHelp === null ? null : __($priceHelp)" type="number" required min="0" max="999999.99" step="0.01" />
        @endif
        <flux:input wire:model="editingItemForm.itemWeight" :label="__('reports.csv.weight')" type="number" min="0" step="0.01" />
        <flux:input wire:model="editingItemForm.itemVolume" :label="__('reports.csv.volume')" type="number" min="0" step="0.01" />
        <flux:input wire:model="editingItemForm.itemCalories" :label="__('reports.csv.calories')" type="number" min="0" max="999999" />
        <flux:input wire:model="editingItemForm.itemSortOrder" :label="__('ui.departments.dashboard.sort')" type="number" required min="0" max="9999" />
    </div>

    <x-menu.item-label-fields
        id-prefix="edit-menu-item-{{ $item['id'] ?? 'new' }}"
        allergens-model="editingItemForm.itemAllergens"
        dietary-labels-model="editingItemForm.itemDietaryLabels"
        :allergen-options="$allergenOptions"
        :dietary-label-options="$dietaryLabelOptions"
    />

    @if ($embeddedMedia)
        <x-menu.item-images :item="$item" :pending-uploads="$itemImageUploads[$item['id']] ?? []" :presentation-context="$imagePresentationContext" :presentation-form="$imagePresentationForm" />
    @endif

    @if ($item !== null)
        <div class="grid min-w-0 gap-2 border-t border-border-subtle pt-4">
            <flux:heading>{{ __('availability.effective') }}</flux:heading>
            @forelse ($item['effective_availability']['reasons'] as $reason)
                <flux:text>{{ $reason['label'] }} · {{ $reason['detail'] }}</flux:text>
            @empty
                <flux:text>{{ __('availability.guest.available') }}</flux:text>
            @endforelse
            @if ($canChangeAvailability)
                <flux:button :href="$item['availability_url']" wire:navigate class="justify-self-start">{{ __('availability.open_item') }}</flux:button>
            @endif
        </div>
    @endif

    <div class="flex min-w-0 flex-wrap gap-3 border-t border-border-subtle pt-4">
        <flux:button icon="check" variant="primary" type="submit" :disabled="$embeddedMedia && $imagePresentationContext !== []" wire:loading.attr="disabled" wire:offline.attr="disabled" wire:target="{{ $saveAction }}">{{ __($saveLabel) }}</flux:button>
        @if ($localDiscard)
            <flux:button icon="x-mark" type="button" wire:click="{{ $cancelAction }}" wire:loading.attr="disabled" wire:target="{{ $saveAction }}" x-on:click.capture="if (!navigator.onLine) discardFormLocally($event, 'editingItemForm', 'mainBaseline')">{{ __($cancelLabel) }}</flux:button>
        @else
            <flux:button icon="x-mark" type="button" wire:click="{{ $cancelAction }}" wire:loading.attr="disabled" wire:target="{{ $saveAction }}">{{ __($cancelLabel) }}</flux:button>
        @endif
        <flux:text wire:dirty wire:target="editingItemForm" role="status">{{ __('menu.editor.unsaved') }}</flux:text>
    </div>
</form>
