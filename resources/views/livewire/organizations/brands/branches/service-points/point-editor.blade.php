<div data-floor-editor data-floor-point-editor class="rm-floor__editor-content">
    <flux:heading size="lg">{{ $pointId === null ? __('floor.add_table') : __('floor.properties') }}</flux:heading>
    <x-floor.errors />
    @if ($message !== '')
        <flux:callout variant="success" :heading="$message" role="status" />
    @endif
    @if ($archived)
        <flux:callout :heading="__('floor.archived')" :text="__('floor.restore_effect')" />
        <flux:button wire:click="restore" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('floor.restore') }}</flux:button>
    @else
        <form wire:submit="save" novalidate class="rm-floor__form">
            <flux:input wire:model="form.name" :label="__('floor.fields.name')" />
            <flux:input wire:model="form.displayNumber" :label="__('floor.fields.number')" :description="__('floor.number_help')" />
            <div class="rm-floor__field-pair">
                <flux:select wire:model="form.type" :label="__('floor.fields.type')">
                    @forelse ($types as $type)
                        <flux:select.option :value="$type['value']">{{ $type['label'] }}</flux:select.option>
                    @empty
                    @endforelse
                </flux:select>
                <flux:input wire:model="form.capacity" type="number" min="1" max="999" :label="__('floor.fields.capacity')" :description="__('floor.capacity_help')" />
            </div>
            @if ($pointId === null)
                <flux:select wire:model="form.areaNodeId" variant="listbox" searchable :filter="false" :label="__('floor.fields.area')">
                    <x-slot name="search"><flux:select.search data-menu-search wire:model.live.debounce.300ms="areaSearch" /></x-slot>
                    <flux:select.option value="">{{ __('floor.no_area') }}</flux:select.option>
                    @forelse ($areas as $area)
                        <flux:select.option :value="(string) $area['id']">{{ $area['label'] }}</flux:select.option>
                    @empty
                    @endforelse
                </flux:select>
            @else
                <flux:text>{{ __('floor.move_separately') }}</flux:text>
            @endif
            <flux:select wire:model="form.icon" :label="__('floor.fields.icon')">
                @forelse ($icons as $icon => $iconLabel)
                    <flux:select.option :value="$icon">{{ $iconLabel }}</flux:select.option>
                @empty
                @endforelse
            </flux:select>
            <flux:checkbox wire:model="form.isActive" :label="__('floor.fields.active')" :description="__('floor.active_help')" />
            <div class="rm-floor__actions">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('floor.save') }}</flux:button>
                <flux:button type="button" x-on:click="discardFormLocally($event, 'form', 'baseline')">{{ __('floor.discard') }}</flux:button>
            </div>
        </form>
        @if ($pointId !== null)
            <div class="rm-floor__danger">
                <flux:text>{{ __('floor.archive_effect') }}</flux:text>
                <flux:checkbox wire:model="confirmArchive" :label="__('floor.archive_confirm')" />
                <flux:button wire:click="archive" class="rm-action-danger" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('floor.archive') }}</flux:button>
            </div>
        @endif
    @endif
</div>
