@props(['item', 'pendingUploads'])

<section
    aria-labelledby="item-{{ $item['id'] }}-photos-heading" class="grid gap-4 rounded-card border border-border p-4"
    x-data="menuImagePicker({ itemId: {{ $item['id'] }} })"
    x-on:livewire-upload-start="uploading = true; failed = false; progress = 0"
    x-on:livewire-upload-progress="progress = $event.detail.progress"
    x-on:livewire-upload-finish="uploading = false; progress = 100"
    x-on:livewire-upload-error="uploading = false; failed = true"
    x-on:livewire-upload-cancel="uploading = false"
    x-on:item-images-saved.window="if ($event.detail.itemId === {{ $item['id'] }}) clear()"
>
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <h3 id="item-{{ $item['id'] }}-photos-heading" class="text-sm font-semibold text-text">{{ __('uploads.labels.gallery') }}</h3>
            <p class="mt-1 text-xs text-text-muted">{{ __('uploads.labels.image_count', ['count' => $item['image_count'], 'max' => $item['max_image_count']]) }}</p>
        </div>
        <p class="text-xs text-text-muted">{{ __('uploads.editor.primary_first') }}</p>
    </div>

    @if ($item['remaining_image_slots'] > 0)
        <div
            class="grid gap-3 rounded-control border border-dashed border-border-strong bg-surface-muted p-4"
            @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="drop($event)"
            :class="dragging ? 'outline-2 outline-focus' : ''"
        >
            <label for="item-images-{{ $item['id'] }}" class="text-sm font-medium text-text">{{ __('uploads.editor.drop_or_choose') }}</label>
            <x-ui.image-upload-input id="item-images-{{ $item['id'] }}" x-ref="files" @change="preview($event)" wire:model="itemImageUploads.{{ $item['id'] }}" multiple :aria-label="__('uploads.labels.multiple_images')" />
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
                            <button type="button" class="min-h-touch rounded-control px-2 text-sm font-medium text-danger-foreground focus-visible:outline-2 focus-visible:outline-focus disabled:opacity-50" :disabled="uploading" @click="remove(index)">{{ __('uploads.editor.remove_pending') }}</button>
                        </figcaption>
                    </figure>
                </template>
            </div>
            <flux:button type="button" variant="primary" icon="arrow-up-tray" wire:click="saveItemImages({{ $item['id'] }})" wire:loading.attr="disabled" wire:target="itemImageUploads.{{ $item['id'] }},saveItemImages" :disabled="$pendingUploads === []" x-bind:disabled="uploading || failed || previews.length === 0">{{ __('uploads.actions.upload') }}</flux:button>
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

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        @forelse ($item['images'] as $image)
            <figure wire:key="menu-item-{{ $item['id'] }}-image-{{ $image['key'] }}" class="min-w-0 overflow-hidden rounded-control border border-border bg-surface-muted">
                <img src="{{ $image['thumbnail_url'] ?? $image['url'] }}" srcset="{{ $image['srcset'] ?? '' }}" sizes="(min-width: 1024px) 180px, (min-width: 640px) 28vw, 42vw" alt="{{ $image['alt'] }}" width="{{ $image['width'] ?? 320 }}" height="{{ $image['height'] ?? 240 }}" loading="lazy" decoding="async" class="aspect-4/3 w-full object-cover">
                <figcaption class="grid gap-2 p-2">
                    @if ($image['is_primary'])
                        <x-ui.status-badge tone="success">{{ __('uploads.labels.primary_image') }}</x-ui.status-badge>
                    @else
                        <flux:button icon="star" type="button" wire:click="{{ $image['promote_action'] }}" wire:loading.attr="disabled">{{ __('uploads.actions.make_primary') }}</flux:button>
                        <div class="grid grid-cols-2 gap-1">
                            <flux:button type="button" icon="arrow-left" :aria-label="__('uploads.editor.move_before')" wire:click="reorderItemImages({{ $item['id'] }}, {{ json_encode($image['previous_order'], JSON_THROW_ON_ERROR) }})" :disabled="! $image['can_move_before']" />
                            <flux:button type="button" icon="arrow-right" :aria-label="__('uploads.editor.move_after')" wire:click="reorderItemImages({{ $item['id'] }}, {{ json_encode($image['next_order'], JSON_THROW_ON_ERROR) }})" :disabled="! $image['can_move_after']" />
                        </div>
                    @endif
                    <x-dangerous-action-confirmation name="remove-menu-item-image-{{ $item['id'] }}-{{ $image['key'] }}" action="delete_media_file" :confirm-action="$image['remove_action']" confirm-label="ui.actions.confirm" loading-label="ui.actions.removing">
                        <x-slot:trigger><flux:button type="button" variant="danger" icon="trash">{{ __('uploads.actions.remove') }}</flux:button></x-slot:trigger>
                    </x-dangerous-action-confirmation>
                </figcaption>
            </figure>
        @empty
            <p class="col-span-full text-sm text-text-muted">{{ __('uploads.editor.no_images') }}</p>
        @endforelse
    </div>
</section>
