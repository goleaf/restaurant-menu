<div data-floor-editor class="rm-floor__editor-content">
    <flux:heading size="lg">{{ __('floor.add_several') }}</flux:heading>
    <x-floor.errors />
    <form wire:submit="review" novalidate class="rm-floor__form">
        <flux:select wire:model="form.areaNodeId" variant="listbox" searchable :filter="false" :label="__('floor.fields.area')">
            <x-slot name="search"><flux:select.search data-menu-search wire:model.live.debounce.300ms="areaSearch" /></x-slot>
            <flux:select.option value="">{{ __('floor.no_area') }}</flux:select.option>
            @forelse ($areas as $area)
                <flux:select.option :value="(string) $area['id']">{{ $area['label'] }}</flux:select.option>
            @empty
            @endforelse
        </flux:select>
        <flux:select wire:model="form.bulkType" :label="__('floor.fields.type')">
            @forelse ($types as $type)
                <flux:select.option :value="$type['value']">{{ $type['label'] }}</flux:select.option>
            @empty
            @endforelse
        </flux:select>
        <flux:input wire:model="form.bulkPrefix" :label="__('floor.fields.prefix')" />
        <div class="rm-floor__field-pair">
            <flux:input type="number" min="1" max="9999" wire:model="form.bulkFrom" :label="__('floor.fields.from')" />
            <flux:input type="number" min="1" max="9999" wire:model="form.bulkTo" :label="__('floor.fields.to')" />
        </div>
        <flux:input type="number" min="1" max="999" wire:model="form.bulkCapacity" :label="__('floor.fields.capacity')" />
        <flux:text>{{ __('floor.bulk_help') }}</flux:text>
        <flux:button type="submit" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('floor.review') }}</flux:button>
    </form>
    @if ($preview !== [])
        <div class="rm-floor__preview-list" tabindex="0">
            @forelse ($preview as $row)
                <p wire:key="preview-{{ $row['code'] }}">{{ $row['name'] }} · {{ $row['exists'] ? __('floor.duplicate') : __('floor.will_create') }}</p>
            @empty
            @endforelse
        </div>
        @if ($result === null)
            <flux:button wire:click="apply" variant="primary" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('floor.create_reviewed') }}</flux:button>
        @endif
    @endif
    @if ($result !== null)
        <flux:callout variant="success" :heading="__('floor.bulk_result', ['created' => $result['created_count'], 'skipped' => $result['skipped_count']])" :text="__('floor.bulk_next')" role="status" />
    @endif
</div>
