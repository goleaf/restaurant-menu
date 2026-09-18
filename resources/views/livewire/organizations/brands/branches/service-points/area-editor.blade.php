<div data-floor-editor class="rm-floor__editor-content">
    <flux:heading size="lg">{{ $areaId === null ? __('floor.add_area') : __('floor.area_properties') }}</flux:heading>
    <x-floor.errors />
    @if ($message !== '')
        <flux:callout variant="success" :heading="$message" role="status" />
    @endif
    @if ($archived)
        <flux:callout :heading="__('floor.archived')" :text="__('floor.area_restore_effect')" />
        <flux:button wire:click="restore" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('floor.restore') }}</flux:button>
    @else
        <form wire:submit="save" novalidate class="rm-floor__form">
            <flux:input wire:model="form.name" :label="__('floor.fields.name')" />
            <flux:select wire:model="form.parentId" variant="listbox" searchable :filter="false" :label="__('floor.fields.parent')">
                <x-slot name="search"><flux:select.search data-menu-search wire:model.live.debounce.300ms="parentSearch" /></x-slot>
                <flux:select.option value="">{{ __('floor.no_parent') }}</flux:select.option>
                @forelse ($parents as $parent)
                    <flux:select.option :value="(string) $parent['id']" :disabled="! $parent['parent_available']">{{ $parent['label'] }}</flux:select.option>
                @empty
                @endforelse
            </flux:select>
            {{ $parentPages->links() }}
            <flux:text>{{ __('floor.parent_help') }}</flux:text>
            <div class="rm-floor__field-pair">
                <flux:select wire:model="form.type" :label="__('floor.fields.type')">
                    @forelse ($types as $type)
                        <flux:select.option :value="$type['value']">{{ $type['label'] }}</flux:select.option>
                    @empty
                    @endforelse
                </flux:select>
                <flux:input wire:model="form.sortOrder" type="number" min="0" max="9999" :label="__('floor.fields.order')" />
            </div>
            <flux:select wire:model="form.icon" :label="__('floor.fields.icon')">
                @forelse ($icons as $icon => $iconLabel)
                    <flux:select.option :value="$icon">{{ $iconLabel }}</flux:select.option>
                @empty
                @endforelse
            </flux:select>
            <flux:checkbox wire:model="form.isActive" :label="__('floor.fields.active')" :description="__('floor.area_active_help')" />
            <div class="rm-floor__actions">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('floor.save') }}</flux:button>
                <flux:button type="button" x-on:click="discardFormLocally($event, 'form', 'baseline')">{{ __('floor.discard') }}</flux:button>
            </div>
        </form>
        @if ($areaId !== null)
            <flux:button wire:click="reviewArchive" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('floor.review_archive') }}</flux:button>
        @endif
        @if ($archivePreview !== null)
            <div class="rm-floor__danger">
                <flux:heading>{{ __('floor.archive') }}</flux:heading>
                <flux:text>{{ __('floor.area_archive_effect', ['children' => $archivePreview['children_count'], 'tables' => $archivePreview['tables_count'], 'assignments' => $archivePreview['assignments_count']]) }}</flux:text>
                @if ($archivePreview['can_archive'])
                    <flux:button wire:click="archive" class="rm-action-danger" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('floor.archive_confirm') }}</flux:button>
                @else
                    <flux:callout variant="warning" :heading="__('floor.errors.area_busy')" />
                @endif
            </div>
        @endif
    @endif
</div>
