@pushOnce('page-module-status', 'menu-status')
    <x-page-module-status module="menu" />
@endPushOnce

<section class="rm-dish content-safe mx-auto grid grid-cols-1 w-full max-w-content gap-5" data-page="dish-card" data-page-module="menu" wire:ignore.self inert x-data="menuWorkspace({ retainSectionDrafts: true, cleanFormActions: { discardMainChanges: 'saveItem' }, invalidEvent: 'dish-main-invalid', invalidSelector: '[data-dish-section=main] [aria-invalid=true], [data-dish-section=main] [role=alert]' })">
    <header class="rm-dish__header content-safe grid grid-cols-1 content-start gap-4">
        <div class="rm-dish__identity content-safe grid grid-cols-1 content-start gap-4">
            <flux:button :href="$returnUrl" wire:navigate variant="ghost" icon="arrow-left" class="justify-self-start">{{ __('dish.back_to_catalog') }}</flux:button>
            <p class="text-sm text-text-muted">{{ $headerDescription }}</p>
            <h1 class="text-2xl font-semibold text-text-primary" tabindex="-1" data-dish-heading>{{ $headerTitle }}</h1>
            <p x-show="hasUnsavedChanges()" x-cloak role="status" class="text-sm text-warning">{{ __('dish.unsaved_kept') }}</p>
        </div>
        <div class="rm-dish__actions flex flex-wrap items-center gap-2">
            <flux:button type="button" icon="eye" wire:click="openPreview" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('dish.preview.open') }}</flux:button>
        </div>
    </header>

    <flux:callout wire:offline variant="warning" :heading="__('menu.workspace.offline')" :text="__('dish.offline_description')" />
    @error('section')
        <flux:callout variant="danger" :heading="$message" role="alert" tabindex="-1" />
    @enderror

    <div class="rm-dish__layout content-safe grid content-start gap-4">
        <nav class="rm-dish__nav" aria-label="{{ __('dish.sections') }}">
            @forelse ($sectionLinks as $link)
                @if ($link['disabled'])
                    <flux:button variant="ghost" :icon="$link['icon']" disabled>{{ $link['label'] }}</flux:button>
                @else
                    <flux:button :href="$link['href']" :icon="$link['icon']" :variant="$section === $link['key'] ? 'primary' : 'ghost'" :aria-current="$section === $link['key'] ? 'page' : null" :data-menu-section="$link['key']" x-on:click="navigateSection($event, $event.currentTarget.dataset.menuSection)">{{ $link['label'] }}</flux:button>
                @endif
            @empty
            @endforelse
        </nav>

        <div class="rm-dish__content content-safe grid grid-cols-1 content-start gap-4" data-menu-workspace-content x-bind:aria-busy="navigating">
            @if ($previewOpen)
                <x-menu.dish-preview :preview="$preview" :language-options="$languageOptions" />
            @endif
            <p wire:loading wire:target="selectSection" role="status" class="text-sm text-text-muted">{{ __('menu.workspace.loading') }}</p>
            <section wire:show="section === 'main'" class="rm-dish__section content-safe grid grid-cols-1 content-start gap-4 rounded-card border border-border-subtle bg-surface p-2 sm:p-4" data-dish-section="main" aria-labelledby="dish-main-heading">
                <div>
                    <flux:heading level="2" size="lg" id="dish-main-heading">{{ __('dish.section.main') }}</flux:heading>
                    <flux:text>{{ __('dish.main_description') }}</flux:text>
                </div>
                @if ($mainSavedMessage !== '')
                    <flux:callout variant="success" :heading="$mainSavedMessage" role="status" data-dish-saved />
                @endif
                @if ($isCreating)
                    <flux:callout :heading="__('dish.create.before_sections')" :text="__('dish.create.unpublished')" />
                @endif
                <x-menu.item-editor :item="$item" :menu-options="$menuOptions" :editing-item-category-options="$editingItemCategoryOptions" :active-kitchen-department-options="$activeKitchenDepartmentOptions" :language-options="$languageOptions" :can-change-prices="$canChangePrices" :can-change-availability="$canChangeAvailability" :allergen-options="$allergenOptions" :dietary-label-options="$dietaryLabelOptions" :embedded-media="false" :local-discard="true" :bounded-search="true" save-action="saveItem" cancel-action="discardMainChanges" language-model="contentLanguage" :save-label="$isCreating ? 'dish.create.continue' : 'dish.save_main'" cancel-label="dish.discard_main" price-label="dish.base_price" price-help="dish.base_price_help" />
            </section>

            @if ($item !== null && in_array('photos', $visitedSections, true))
                <section wire:show="section === 'photos'" class="rm-dish__section content-safe grid grid-cols-1 content-start gap-4 rounded-card border border-border-subtle bg-surface p-2 sm:p-4" data-dish-section="photos" aria-labelledby="dish-photos-heading">
                    <div>
                        <flux:heading level="2" size="lg" id="dish-photos-heading">{{ __('dish.section.photos') }}</flux:heading>
                        <flux:text>{{ __('dish.photos_description') }}</flux:text>
                    </div>
                    <x-menu.item-images :item="$item" :pending-uploads="$pendingItemImageUploads[$item['id']] ?? []" :presentation-context="$imagePresentationContext" :presentation-form="$imagePresentationForm" :has-pending-cleanup="$hasPendingImageCleanup" />
                </section>
            @endif

            @if ($item !== null && in_array('variants', $visitedSections, true))
                <section wire:show="section === 'variants'" class="rm-dish__section content-safe grid grid-cols-1 content-start gap-4 rounded-card border border-border-subtle bg-surface p-2 sm:p-4" data-dish-section="variants" aria-labelledby="dish-variants-heading">
                    <flux:heading level="2" size="lg" id="dish-variants-heading">{{ __('dish.section.variants') }}</flux:heading>
                    <livewire:organizations.brands.branches.menu.variants :organization-id="$organizationId" :brand-id="$brandId" :branch-id="$branchId" :item-id="$item['id']" :key="'dish-variants-'.$item['id']" />
                </section>
            @endif

            @if ($item !== null && in_array('modifiers', $visitedSections, true))
                <section wire:show="section === 'modifiers'" class="rm-dish__section content-safe grid grid-cols-1 content-start gap-4 rounded-card border border-border-subtle bg-surface p-2 sm:p-4" data-dish-section="modifiers" aria-labelledby="dish-modifiers-heading">
                    <flux:heading level="2" size="lg" id="dish-modifiers-heading">{{ __('dish.section.modifiers') }}</flux:heading>
                    <livewire:organizations.brands.branches.menu.modifiers :organization-id="$organizationId" :brand-id="$brandId" :branch-id="$branchId" :item-id="$item['id']" :key="'dish-modifiers-'.$item['id']" />
                </section>
            @endif
        </div>
    </div>

    <flux:modal name="menu-workspace-unsaved" :closable="false" class="w-full max-w-md" x-on:close="pendingNavigation = null">
        <x-modal-close-button :autofocus="true" />
        <div class="grid gap-4">
            <div class="pe-10">
                <flux:heading size="lg">{{ __('menu.workspace.unsaved_title') }}</flux:heading>
                <flux:text class="mt-2">{{ __('dish.unsaved_description') }}</flux:text>
            </div>
            <div class="rm-dish__actions flex flex-wrap items-center gap-2">
                <flux:button type="button" x-on:click="cancelNavigation">{{ __('menu.workspace.keep_editing') }}</flux:button>
                <flux:button type="button" variant="primary" color="red" x-on:click="discardAndNavigate" class="rm-action-danger">{{ __('menu.workspace.discard') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
