@props(['item', 'pendingUploads', 'presentationContext' => [], 'presentationForm' => null])

<section
    data-menu-item-images
    aria-labelledby="item-{{ $item['id'] }}-photos-heading" class="grid gap-4 rounded-card border border-border p-4"
    x-data="menuImagePicker({ itemId: {{ $item['id'] }} })"
    x-on:livewire-upload-start="uploading = true; failed = false; progress = 0"
    x-on:livewire-upload-progress="progress = $event.detail.progress"
    x-on:livewire-upload-finish="finishUpload()"
    x-on:livewire-upload-error="failUpload()"
    x-on:livewire-upload-cancel="cancelUpload()"
    x-on:offline.window="offline = true; dragging = false"
    x-on:online.window="offline = false"
    x-on:input.capture="if ($event.target.closest('[data-image-presentation-editor]')) { metadataDirty = true; notifyDirty() }"
    x-on:image-presentation-closed.window="if ($event.detail.itemId === {{ $item['id'] }}) { metadataDirty = false; notifyDirty(); if (settingsTrigger?.isConnected) settingsTrigger.focus() }"
    x-on:item-images-saved.window="if ($event.detail.itemId === {{ $item['id'] }}) clear()"
>
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <h3 id="item-{{ $item['id'] }}-photos-heading" class="text-sm font-semibold text-text">{{ __('uploads.labels.gallery') }}</h3>
            <p class="mt-1 text-xs text-text-muted">{{ __('uploads.labels.image_count', ['count' => $item['image_count'], 'max' => $item['max_image_count']]) }}</p>
        </div>
        <p class="text-xs text-text-muted">{{ __('uploads.editor.primary_first') }}</p>
    </div>

    <p class="text-xs text-text-muted">{{ __('uploads.presentation.separate_save') }}</p>

    @if ($item['remaining_image_slots'] > 0 && $presentationContext === [])
        <div
            class="grid gap-3 rounded-control border border-dashed border-border-strong bg-surface-muted p-4"
            @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="drop($event)"
            :class="dragging ? 'outline-2 outline-focus' : ''"
        >
            <label for="item-images-{{ $item['id'] }}" class="text-sm font-medium text-text">{{ __('uploads.editor.drop_or_choose') }}</label>
            <x-ui.image-upload-input id="item-images-{{ $item['id'] }}" x-ref="files" @change.capture="preview($event)" x-bind:disabled="busy" wire:model="itemImageUploads.{{ $item['id'] }}" multiple :aria-label="__('uploads.labels.multiple_images')" />
            <p class="text-xs text-text-muted">{{ __('uploads.labels.up_to_images', ['count' => $item['remaining_image_slots']]) }}</p>
            <div x-show="uploading" class="grid gap-1" role="status">
                <span class="text-xs text-text-muted">{{ __('uploads.editor.uploading') }} <span x-text="progress + '%'"></span></span>
                <progress max="100" :value="progress" class="h-2 w-full accent-[var(--color-accent)]" aria-label="{{ __('uploads.editor.uploading') }}"></progress>
            </div>
            <p x-show="failed" role="alert" class="text-sm text-danger-foreground">{{ __('uploads.editor.retry_help') }}</p>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3" x-show="previews.length > 0">
                <template x-for="(preview, index) in previews" :key="preview.key">
                    <figure class="min-w-0 overflow-hidden rounded-control border border-border bg-surface">
                        <img :src="preview.url" :alt="preview.name" width="240" height="180" class="aspect-4/3 w-full object-cover">
                        <figcaption class="grid gap-1 p-2">
                            <span class="truncate text-xs text-text" x-text="preview.name"></span>
                            <button type="button" class="min-h-touch rounded-control px-2 text-sm font-medium text-danger-foreground focus-visible:outline-2 focus-visible:outline-focus disabled:opacity-50" :disabled="busy" @click="remove(preview.key)">{{ __('uploads.editor.remove_pending') }}</button>
                        </figcaption>
                    </figure>
                </template>
            </div>
            <flux:button type="button" variant="primary" icon="arrow-up-tray" wire:click="saveItemImages({{ $item['id'] }})" @click.capture="beginSave($event)" wire:loading.attr="disabled" wire:target="itemImageUploads.{{ $item['id'] }},saveItemImages" :disabled="$pendingUploads === []" x-bind:disabled="busy || previews.length === 0">{{ __('uploads.actions.upload') }}</flux:button>
        </div>
    @endif

    @error('itemImageUploads.'.$item['id'])
        <p role="alert" class="text-sm font-medium text-danger-foreground">{{ $message }}</p>
    @enderror
    @error('catalogOperation')
        <p role="alert" class="text-sm font-medium text-danger-foreground">{{ $message }}</p>
        <flux:button type="button" wire:click="resumeCatalogOperation" wire:loading.attr="disabled" icon="arrow-path">{{ __('menu.operations.resume') }}</flux:button>
    @enderror
    @forelse ($pendingUploads as $uploadIndex => $pendingUpload)
        @error('itemImageUploads.'.$item['id'].'.'.$uploadIndex)
            <p role="alert" class="text-sm text-danger-foreground">{{ __('uploads.editor.file_number', ['number' => $uploadIndex + 1]) }}: {{ $message }}</p>
        @enderror
    @empty
    @endforelse

    @if ($presentationContext !== [] && $presentationContext['item_id'] === $item['id'])
        <div
            wire:key="image-presentation-{{ $presentationContext['identity'] }}"
            class="grid min-w-0 gap-5 rounded-control border border-border-strong bg-surface p-4"
            x-data="menuImagePresentationEditor({ focalX: @js($presentationForm->focal_x), focalY: @js($presentationForm->focal_y) })"
            x-on:image-presentation-invalid.window="showError($event.detail.locale)"
            data-image-presentation-editor
        >
            <div>
                <h4 tabindex="-1" x-ref="heading" class="text-base font-semibold text-text">{{ __('uploads.presentation.title') }}</h4>
                <p class="mt-1 text-sm text-text-muted">{{ __('uploads.presentation.help') }}</p>
                <p class="mt-2 text-xs text-text-muted">{{ $presentationContext['file_details'] }}</p>
            </div>
            @error('imagePresentationForm')
                <p role="alert" tabindex="-1" data-image-error class="text-sm font-medium text-danger-foreground">{{ $message }}</p>
            @enderror
            <div class="grid min-w-0 gap-4 sm:grid-cols-2">
                <figure class="min-w-0">
                    <img src="{{ $presentationContext['thumbnail_url'] }}" alt="{{ $presentationContext['alt'] }}" width="{{ $presentationContext['width'] }}" height="{{ $presentationContext['height'] }}" :style="{ objectPosition: position }" class="aspect-4/3 w-full rounded-control bg-surface-muted object-cover">
                    <figcaption class="mt-2 text-xs text-text-muted">{{ __('uploads.presentation.card_preview') }}</figcaption>
                </figure>
                <figure class="min-w-0">
                    <img src="{{ $presentationContext['url'] }}" alt="{{ $presentationContext['alt'] }}" width="{{ $presentationContext['width'] }}" height="{{ $presentationContext['height'] }}" :style="{ objectPosition: position }" class="aspect-square w-full rounded-control bg-surface-muted object-contain">
                    <figcaption class="mt-2 text-xs text-text-muted">{{ __('uploads.presentation.detail_preview') }}</figcaption>
                </figure>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="image-focal-x-{{ $item['id'] }}" class="text-sm font-medium text-text">{{ __('uploads.presentation.horizontal') }} <output x-text="focalX + '%'" class="tabular-nums"></output></label>
                    <input id="image-focal-x-{{ $item['id'] }}" type="range" min="0" max="100" step="1" x-model="focalX" wire:model="imagePresentationForm.focal_x" class="mt-1 min-h-touch w-full accent-[var(--color-accent)] focus-visible:outline-2 focus-visible:outline-focus">
                    @error('imagePresentationForm.focal_x')<p role="alert" tabindex="-1" data-image-error class="text-sm text-danger-foreground">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="image-focal-y-{{ $item['id'] }}" class="text-sm font-medium text-text">{{ __('uploads.presentation.vertical') }} <output x-text="focalY + '%'" class="tabular-nums"></output></label>
                    <input id="image-focal-y-{{ $item['id'] }}" type="range" min="0" max="100" step="1" x-model="focalY" wire:model="imagePresentationForm.focal_y" class="mt-1 min-h-touch w-full accent-[var(--color-accent)] focus-visible:outline-2 focus-visible:outline-focus">
                    @error('imagePresentationForm.focal_y')<p role="alert" tabindex="-1" data-image-error class="text-sm text-danger-foreground">{{ $message }}</p>@enderror
                </div>
            </div>
            <fieldset class="min-w-0">
                <legend class="mb-2 text-sm font-medium text-text">{{ __('uploads.presentation.translations') }}</legend>
                <div role="tablist" aria-label="{{ __('uploads.presentation.translations') }}" class="mb-3 flex gap-1 rounded-control bg-surface-muted p-1">
                    @forelse (['en', 'lt', 'ru'] as $photoLocale)
                        <button type="button" role="tab" id="image-{{ $item['id'] }}-{{ $photoLocale }}-tab" aria-controls="image-{{ $item['id'] }}-{{ $photoLocale }}-panel" :aria-selected="locale === '{{ $photoLocale }}'" :tabindex="locale === '{{ $photoLocale }}' ? 0 : -1" @click="locale = '{{ $photoLocale }}'" @keydown.arrow-right.prevent="nextLocale(1)" @keydown.arrow-left.prevent="nextLocale(-1)" class="min-h-touch flex-1 rounded-control px-3 text-sm font-medium uppercase focus-visible:outline-2 focus-visible:outline-focus" :class="locale === '{{ $photoLocale }}' ? 'bg-surface text-text shadow-sm' : 'text-text-muted'">{{ $photoLocale }}</button>
                    @empty
                    @endforelse
                </div>
                <p class="mb-3 text-xs text-text-muted">{{ __('uploads.presentation.text_help') }}</p>
                @forelse (['en', 'lt', 'ru'] as $photoLocale)
                    <div role="tabpanel" id="image-{{ $item['id'] }}-{{ $photoLocale }}-panel" aria-labelledby="image-{{ $item['id'] }}-{{ $photoLocale }}-tab" x-show="locale === '{{ $photoLocale }}'" class="grid gap-3" data-photo-locale="{{ $photoLocale }}">
                        <flux:input wire:model="imagePresentationForm.translations.{{ $photoLocale }}.alt" :label="__('uploads.presentation.alt')" maxlength="250" />
                        <flux:textarea wire:model="imagePresentationForm.translations.{{ $photoLocale }}.caption" :label="__('uploads.presentation.caption')" rows="3" maxlength="1000" />
                    </div>
                @empty
                @endforelse
            </fieldset>
            <div class="grid gap-2 border-t border-border pt-4 sm:flex sm:flex-wrap">
                <flux:button type="button" variant="primary" wire:click="saveItemImagePresentation" wire:loading.attr="disabled" wire:target="saveItemImagePresentation" x-bind:disabled="offline">{{ __('uploads.presentation.save') }}</flux:button>
                <flux:button type="button" wire:click="closeItemImagePresentation" wire:loading.attr="disabled" wire:target="saveItemImagePresentation">{{ __('uploads.presentation.discard') }}</flux:button>
                <p wire:dirty wire:target="imagePresentationForm" class="self-center text-xs text-warning-foreground" role="status">{{ __('uploads.presentation.unsaved') }}</p>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($item['images'] as $image)
            <figure wire:key="menu-item-{{ $item['id'] }}-image-{{ $image['key'] }}" class="min-w-0 overflow-hidden rounded-control border border-border bg-surface-muted">
                <img src="{{ $image['thumbnail_url'] ?? $image['url'] }}" srcset="{{ $image['srcset'] ?? '' }}" sizes="(min-width: 1024px) 180px, (min-width: 640px) 28vw, 42vw" alt="{{ $image['alt'] }}" width="{{ $image['width'] ?? 320 }}" height="{{ $image['height'] ?? 240 }}" loading="lazy" decoding="async" style="object-position: {{ $image['object_position'] ?? '50% 50%' }}" class="aspect-4/3 w-full object-cover">
                <figcaption class="grid gap-2 p-3">
                    <flux:button type="button" icon="adjustments-horizontal" wire:click="{{ $image['presentation_action'] }}" wire:loading.attr="disabled" :disabled="$presentationContext !== []" data-image-settings-trigger @click="settingsTrigger = $event.currentTarget">{{ __('uploads.presentation.edit') }}</flux:button>
                    @if ($image['is_primary'])
                        <x-ui.status-badge tone="success">{{ __('uploads.labels.primary_image') }}</x-ui.status-badge>
                    @else
                        <flux:button icon="star" type="button" wire:click="{{ $image['promote_action'] }}" wire:loading.attr="disabled" :disabled="$presentationContext !== []">{{ __('uploads.actions.make_primary') }}</flux:button>
                        <div class="grid grid-cols-2 gap-1">
                            <flux:button type="button" icon="arrow-left" :aria-label="__('uploads.editor.move_before')" wire:click="reorderItemImages({{ $item['id'] }}, {{ json_encode($image['previous_order'], JSON_THROW_ON_ERROR) }})" :disabled="$presentationContext !== [] || ! $image['can_move_before']" />
                            <flux:button type="button" icon="arrow-right" :aria-label="__('uploads.editor.move_after')" wire:click="reorderItemImages({{ $item['id'] }}, {{ json_encode($image['next_order'], JSON_THROW_ON_ERROR) }})" :disabled="$presentationContext !== [] || ! $image['can_move_after']" />
                        </div>
                    @endif
                    <x-dangerous-action-confirmation name="remove-menu-item-image-{{ $item['id'] }}-{{ $image['key'] }}" action="delete_media_file" :confirm-action="$image['remove_action']" confirm-label="ui.actions.confirm" loading-label="ui.actions.removing">
                        <x-slot:trigger><flux:button type="button" variant="danger" icon="trash" :disabled="$presentationContext !== []">{{ __('uploads.actions.remove') }}</flux:button></x-slot:trigger>
                    </x-dangerous-action-confirmation>
                </figcaption>
            </figure>
        @empty
            <p class="col-span-full text-sm text-text-muted">{{ __('uploads.editor.no_images') }}</p>
        @endforelse
    </div>
</section>
