<div class="rm-availability__section" data-availability-panel="schedules">
    <flux:heading size="lg">{{ __('availability.section.schedules') }}</flux:heading>
    <flux:text>{{ __('availability.schedule_description') }}</flux:text>
    @if ($canManageSettings)
        <div class="rm-availability__actions">
            <flux:button wire:click="openHours" wire:offline.attr="disabled">{{ __('availability.branch_schedule') }}</flux:button>
            <flux:button wire:click="openExceptions" wire:offline.attr="disabled">{{ __('availability.exceptions_title') }}</flux:button>
        </div>
    @endif
    @if ($canManageMenu)
        <div class="rm-availability__filters">
            <flux:select variant="listbox" searchable :filter="false" wire:model="menuId" :label="__('availability.menu')">
                <x-slot name="search"><flux:select.search wire:model.live.debounce.300ms="menuSearch" :placeholder="__('availability.search_menus')" /></x-slot>
                <flux:select.option value="">{{ __('availability.choose_menu') }}</flux:select.option>
                @forelse ($menuOptions as $option)
                    <flux:select.option :value="$option['id']">{{ $option['name'] }}</flux:select.option>
                @empty
                    <flux:select.option disabled>{{ __('availability.no_menus') }}</flux:select.option>
                @endforelse
            </flux:select>
            <flux:button wire:click="openMenuSchedule" wire:offline.attr="disabled">{{ __('availability.edit_menu_schedule') }}</flux:button>
        </div>
        <flux:error name="menuId" />
    @endif
    @if ($editor === 'hours' || $editor === 'menu')
        @include('livewire.organizations.brands.branches.availability.weekly')
    @elseif ($editor === 'exceptions')
        @include('livewire.organizations.brands.branches.availability.exceptions')
    @endif
</div>
