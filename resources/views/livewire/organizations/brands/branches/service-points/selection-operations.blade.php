<div data-floor-editor class="rm-floor__editor-content">
    <flux:heading size="lg">{{ $operation === 'move' ? __('floor.move_selected') : __('floor.qr.create_selected') }}</flux:heading>
    <x-floor.errors />
    <flux:text>{{ __('floor.selected_count', ['count' => $totalCount]) }}</flux:text>
    <div class="rm-floor__preview-list" tabindex="0">
        @forelse ($rows as $row)
            <div wire:key="selected-{{ $row['id'] }}">
                <p>{{ $row['name'] }} · {{ $row['area'] }} · {{ $row['done'] ? __('floor.completed') : ($row['qr'] ?? __('floor.qr.missing')) }}</p>
                @if ($row['issue'] !== null)
                    <flux:text>{{ $row['issue'] }}</flux:text>
                    @if ($row['canReview'])
                        <flux:button wire:click="openQr({{ $row['id'] }})" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('floor.qr.manage') }}</flux:button>
                    @endif
                @endif
            </div>
        @empty
        @endforelse
    </div>
    @if ($operation === 'move')
        <form wire:submit="reviewMove" novalidate class="rm-floor__form">
            <flux:select wire:model="form.targetAreaId" variant="listbox" searchable :filter="false" :label="__('floor.fields.target')">
                <x-slot name="search"><flux:select.search data-menu-search wire:model.live.debounce.300ms="areaSearch" /></x-slot>
                <flux:select.option value="">{{ __('floor.no_area') }}</flux:select.option>
                @forelse ($areas as $area)
                    <flux:select.option :value="(string) $area['id']">{{ $area['label'] }}</flux:select.option>
                @empty
                @endforelse
            </flux:select>
            <flux:text>{{ __('floor.move_help') }}</flux:text>
            <flux:button type="submit" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('floor.review') }}</flux:button>
        </form>
        @if ($preview !== null && ! $finished)
            <flux:callout :heading="__('floor.move_effect')" :text="__('floor.move_coverage')" />
            <flux:button wire:click="applyMove" variant="primary" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('floor.move_confirm') }}</flux:button>
        @endif
    @else
        <flux:text>{{ __('floor.qr.batch_help') }}</flux:text>
        <flux:text role="status">{{ __('floor.qr.progress', ['done' => $completedCount, 'total' => $totalCount]) }}</flux:text>
        @if ($skippedCount > 0 || $failedCount > 0)
            <flux:text role="status">{{ __('floor.qr.result.counts', ['skipped' => $skippedCount, 'failed' => $failedCount]) }}</flux:text>
        @endif
        @if (! $finished)
            <flux:button wire:click="generateNext" variant="primary" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('floor.qr.continue') }}</flux:button>
        @endif
        @if ($failedCount > 0)
            <flux:button wire:click="retryFailed" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('floor.qr.retry_failed') }}</flux:button>
        @endif
    @endif
    @if ($finished)
        @if ($skippedCount > 0 || $failedCount > 0)
            <flux:callout variant="warning" :heading="__('floor.qr.result.partial')" role="status" />
        @else
            <flux:callout variant="success" :heading="__('floor.completed')" role="status" />
        @endif
    @endif
</div>
