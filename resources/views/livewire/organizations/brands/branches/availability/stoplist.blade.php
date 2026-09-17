<div class="rm-availability__section" data-availability-panel="stoplist">
    <div class="rm-availability__filters">
        <flux:input wire:model.live.debounce.300ms="filters.search" :label="__('availability.search_items')" type="search" />
        <flux:select wire:model.live="filters.state" :label="__('availability.restriction_filter')">
            <flux:select.option value="">{{ __('availability.all_items') }}</flux:select.option>
            <flux:select.option value="stopped">{{ __('availability.stopped') }}</flux:select.option>
            <flux:select.option value="hidden">{{ __('availability.hidden') }}</flux:select.option>
            <flux:select.option value="unrestricted">{{ __('availability.unrestricted') }}</flux:select.option>
        </flux:select>
        <flux:select variant="listbox" searchable :filter="false" wire:model.live="menuId" :label="__('availability.menu')">
            <x-slot name="search"><flux:select.search wire:model.live.debounce.300ms="menuSearch" :placeholder="__('availability.search_menus')" /></x-slot>
            <flux:select.option value="">{{ __('availability.all_menus') }}</flux:select.option>
            @forelse ($menuOptions as $option)
                <flux:select.option :value="$option['id']">{{ $option['name'] }}</flux:select.option>
            @empty
                <flux:select.option disabled>{{ __('availability.no_menus') }}</flux:select.option>
            @endforelse
        </flux:select>
    </div>
    <flux:error name="filters.search" /><flux:error name="filters.state" /><flux:error name="menuId" />
    <flux:text>{{ __('availability.page_items', ['count' => $items->total()]) }}</flux:text>
    <flux:button wire:click="openBulk" wire:offline.attr="disabled" icon="adjustments-horizontal">{{ __('availability.edit_selected') }}</flux:button>
    <flux:error name="selectedItems" /><flux:error name="selectedItems.*" />
    <div class="rm-availability__items">
        @forelse ($items as $item)
            <flux:card wire:key="availability-item-{{ $item['id'] }}" class="rm-availability__item">
                <flux:checkbox wire:model="selectedItems" :value="$item['id']" :label="__('availability.select_item', ['name' => $item['name']])" />
                <flux:heading>{{ $item['name'] }}</flux:heading>
                <flux:text>{{ $item['menu_name'] }} · {{ $item['category_name'] }}</flux:text>
                <div class="rm-availability__actions">
                    @if ($item['stopped']) <flux:badge color="amber">{{ __('availability.stopped') }}</flux:badge> @endif
                    @if ($item['hidden_until']) <flux:badge>{{ __('availability.hidden_until', ['time' => $item['hidden_until']]) }}</flux:badge> @endif
                    <flux:badge :color="$item['result']['accepts_new_orders'] ? 'green' : 'zinc'">{{ $item['result']['accepts_new_orders'] ? __('availability.orderable') : __('availability.not_orderable') }}</flux:badge>
                </div>
                <flux:button wire:click="openItem({{ $item['id'] }})" wire:offline.attr="disabled">{{ __('availability.explain_edit') }}</flux:button>
            </flux:card>
        @empty
            <flux:callout :heading="__('availability.no_items')" :text="__('availability.no_items_description')" />
        @endforelse
    </div>
    {{ $items->links() }}
    @if ($editor === 'restriction')
        <section class="rm-availability__editor" data-availability-editor x-show="!locallyDiscarded" tabindex="-1" aria-labelledby="restriction-title">
            <flux:heading id="restriction-title" size="lg">{{ __('availability.restriction_editor') }}</flux:heading>
            @if ($detail)
                <flux:heading>{{ $detail['name'] }}</flux:heading>
                @include('livewire.organizations.brands.branches.availability.result', ['result' => $detail['result']])
            @endif
            <form wire:submit="previewRestriction" novalidate class="rm-availability__section">
                <flux:select wire:model="restriction.operation" :label="__('availability.operation')">
                    <flux:select.option value="stop">{{ __('availability.operation.stop') }}</flux:select.option>
                    <flux:select.option value="resume">{{ __('availability.operation.resume') }}</flux:select.option>
                    <flux:select.option value="hide">{{ __('availability.operation.hide') }}</flux:select.option>
                    <flux:select.option value="unhide">{{ __('availability.operation.unhide') }}</flux:select.option>
                </flux:select>
                <flux:text>{{ __('availability.independent_restrictions') }}</flux:text>
                <div class="rm-availability__filters" x-show="$wire.restriction.operation === 'hide'" x-cloak data-restriction-deadline>
                    <flux:date-picker :placeholder="__('availability.choose_date')" wire:model="restriction.untilDate" :label="__('availability.until_date')" :locale="$locale" />
                    <flux:time-picker :placeholder="__('availability.choose_time')" wire:model="restriction.untilTime" :label="__('availability.until_time')" :locale="$locale" time-format="24-hour" />
                </div>
                <flux:textarea wire:model="restriction.reason" :label="__('availability.reason')" rows="2" />
                <div class="rm-availability__actions">
                    <flux:button type="submit" variant="primary" wire:offline.attr="disabled" wire:loading.attr="disabled">{{ __('availability.preview') }}</flux:button>
                    <flux:button type="button" x-on:click="cancelDraft">{{ __('availability.cancel_draft') }}</flux:button>
                </div>
            </form>
            @if ($preview)
                <div class="rm-availability__preview" tabindex="-1" data-availability-preview role="status">
                    <flux:heading>{{ __('availability.preview_title') }}</flux:heading>
                    @forelse ($preview['rows'] as $row)
                        <flux:heading>{{ $row['name'] }}</flux:heading>
                        <div class="rm-availability__comparison">
                            <div><flux:heading>{{ __('availability.before') }}</flux:heading>@include('livewire.organizations.brands.branches.availability.result', ['result' => $row['before']])</div>
                            <div><flux:heading>{{ __('availability.after') }}</flux:heading>@include('livewire.organizations.brands.branches.availability.result', ['result' => $row['after']])</div>
                        </div>
                    @empty
                    @endforelse
                    <flux:button wire:click="applyRestriction" variant="primary" wire:offline.attr="disabled" wire:loading.attr="disabled">{{ __('availability.apply_restriction') }}</flux:button>
                </div>
            @endif
        </section>
    @endif
</div>
