@props(['preview', 'languageOptions', 'stale' => false])

<section class="rm-dish__section content-safe grid grid-cols-1 content-start gap-4 rounded-card border border-border-subtle bg-surface-muted p-2 sm:p-4" aria-labelledby="dish-preview-heading" data-menu-preview>
    <div class="rm-dish__header content-safe grid grid-cols-1 content-start gap-4">
        <div>
            <flux:heading level="2" size="lg" id="dish-preview-heading">{{ __('dish.preview.open') }}</flux:heading>
            <flux:text>{{ __('dish.preview.description') }}</flux:text>
        </div>
        <flux:button type="button" variant="ghost" icon="x-mark" wire:click="$set('previewOpen', false)">{{ __('dish.preview.close') }}</flux:button>
    </div>
    <form wire:submit="refreshPreview" novalidate class="rm-dish__content content-safe grid grid-cols-1 content-start gap-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:select wire:model="previewForm.source" :label="__('dish.preview.source')">
                <flux:select.option value="saved">{{ __('dish.preview.saved') }}</flux:select.option>
                <flux:select.option value="draft">{{ __('dish.preview.draft') }}</flux:select.option>
            </flux:select>
            <flux:select wire:model="contentLanguage" :label="__('dish.preview.language')">
                @forelse ($languageOptions as $code => $label)
                    <flux:select.option value="{{ $code }}">{{ $label }}</flux:select.option>
                @empty
                @endforelse
            </flux:select>
        </div>
        @if ($preview !== null)
            @if ($preview['variants'] !== [])
                <flux:select wire:model="previewForm.variantId" :label="__('dish.section.variants')">
                    <flux:select.option value="">{{ __('dish.preview.choose_variant') }}</flux:select.option>
                    @forelse ($preview['variants'] as $variant)
                        <flux:select.option value="{{ $variant['id'] }}" wire:key="dish-preview-variant-{{ $variant['id'] }}">{{ $variant['name'] }} · {{ $variant['formatted_price'] }}</flux:select.option>
                    @empty
                    @endforelse
                </flux:select>
            @endif
            <div class="grid gap-4 sm:grid-cols-2">
                @forelse ($preview['modifier_groups'] as $group)
                    <fieldset wire:key="dish-preview-modifiers-{{ $group['id'] }}" class="rm-dish__content content-safe grid grid-cols-1 content-start gap-4">
                        <legend class="text-sm font-semibold text-text-primary">{{ $group['name'] }}</legend>
                        <flux:text>{{ $group['selection_limits'] }}</flux:text>
                        @if ($group['is_required'])
                            <flux:badge class="justify-self-start">{{ __('dish.preview.required') }}</flux:badge>
                        @endif
                        <flux:checkbox.group wire:model="previewForm.modifiers.{{ $group['id'] }}" :aria-label="$group['name']">
                            @forelse ($group['options'] as $option)
                                <flux:checkbox value="{{ $option['id'] }}" :label="$option['name'].' · '.$option['formatted_price']" wire:key="dish-preview-option-{{ $option['id'] }}" />
                            @empty
                                <flux:text>{{ __('dish.preview.no_options') }}</flux:text>
                            @endforelse
                        </flux:checkbox.group>
                    </fieldset>
                @empty
                @endforelse
            </div>
        @endif
        <flux:error name="previewForm" class="text-danger!" />
        <div class="rm-dish__actions flex flex-wrap items-center gap-2">
            <flux:button type="submit" wire:loading.attr="disabled" wire:offline.attr="disabled" icon="arrow-path">{{ __('dish.preview.refresh') }}</flux:button>
            @if (! $stale)
                <flux:text wire:dirty wire:target="previewForm,editingItemForm,contentLanguage" role="status">{{ __('dish.preview.refresh_needed') }}</flux:text>
            @endif
        </div>
    </form>

    @if ($stale)
        <flux:callout variant="warning" :heading="__('dish.preview.refresh_needed')" role="status" data-dish-preview-stale />
    @endif

    @if ($preview !== null)
        <div class="rm-dish__content content-safe grid grid-cols-1 content-start gap-4" data-dish-preview-result lang="{{ $preview['language'] }}">
            <p class="text-sm text-text-muted">{{ $preview['source'] === 'draft' ? __('dish.preview.draft') : __('dish.preview.saved') }} · {{ $preview['evaluated_at'] }}</p>
            <div class="rm-media-gallery">
                @forelse ($preview['images'] as $image)
                    <figure class="rm-media-figure">
                        <img src="{{ $image['url'] }}" alt="{{ $image['alt'] }}" loading="lazy" width="640" height="480" style="object-position: {{ $image['object_position'] }}" class="rm-media-image">
                        @if ($image['caption'] !== '')
                            <figcaption class="p-3 text-sm text-text-muted">{{ $image['caption'] }}</figcaption>
                        @endif
                    </figure>
                @empty
                @endforelse
            </div>
            <h3 class="text-xl font-semibold text-text-primary">{{ $preview['name'] }}</h3>
            <p class="whitespace-pre-line text-text-muted">{{ $preview['description'] }}</p>
            <x-menu.item-labels :allergens="$preview['allergens']" :dietary-labels="$preview['dietary_labels']" />
            @if ($preview['configuration_error'] !== null)
                <flux:callout variant="warning" :heading="$preview['configuration_error']" role="status" />
            @elseif (! $stale && $preview['formatted_price'] !== null)
                <p class="text-xl font-semibold text-text-primary" wire:dirty.remove wire:target="previewForm,editingItemForm,contentLanguage" data-dish-preview-price>{{ $preview['formatted_price'] }}</p>
            @endif
            @if (! $preview['availability']['accepts_new_orders'])
                <flux:callout variant="warning" :heading="__('availability.effective')">
                    @forelse ($preview['availability']['reasons'] as $reason)
                        <p>{{ $reason['label'] }} · {{ $reason['detail'] }}</p>
                    @empty
                    @endforelse
                </flux:callout>
            @endif
        </div>
    @endif
</section>
