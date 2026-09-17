<div x-data class="workspace-restaurant" data-workspace-restaurant wire:init="remember">
    @if ($canChoose)
        <flux:modal.trigger name="workspace-restaurant">
            <flux:button variant="ghost" icon="building-storefront" icon:trailing="chevron-down" x-ref="restaurantTrigger" x-on:click="$el.focus()" class="workspace-restaurant__trigger h-auto! min-h-touch max-w-full text-start py-1.5!" :aria-label="__('workspace.choose')">
                <span class="workspace-restaurant__identity">
                    <strong>{{ $currentName ?? $modeLabel }}</strong>
                    @if ($currentDescription)<small>{{ $currentDescription }}</small>@endif
                </span>
            </flux:button>
        </flux:modal.trigger>
    @else
        <div class="workspace-restaurant__identity" aria-live="polite">
            <strong>{{ $currentName }}</strong>
            <small>{{ $currentDescription }}</small>
        </div>
    @endif
    <flux:modal name="workspace-restaurant" x-on:close="$refs.restaurantTrigger?.focus()" class="workspace-restaurant__dialog">
        <form wire:submit="choose" class="grid gap-4">
            <flux:heading id="workspace-restaurant-heading" level="2" size="lg" x-bind="dialogLabel">{{ __('workspace.choose') }}</flux:heading>
            <flux:text>{{ __('workspace.switch_description') }}</flux:text>
            @if ($canAggregate)
                <flux:button type="button" wire:click="chooseAggregate" wire:loading.attr="disabled" wire:target="chooseAggregate,choose" wire:offline.attr="disabled">{{ __('workspace.mode.aggregate') }}</flux:button>
            @endif
            <flux:select wire:offline.attr="disabled" variant="listbox" searchable wire:model="form.branchId" :filter="false" :label="__('workspace.restaurant')" :placeholder="__('workspace.search')" :aria-invalid="$errors->has('form.branchId') ? 'true' : 'false'" aria-describedby="workspace-restaurant-error">
                <x-slot:search>
                    <flux:select.search wire:offline.attr="disabled" :aria-label="__('workspace.search')" :aria-invalid="$errors->has('form.search') ? 'true' : 'false'" aria-describedby="workspace-search-error" wire:model.live.debounce.250ms="form.search" :placeholder="__('workspace.search')" maxlength="100" />
                </x-slot:search>
                @forelse ($options as $option)
                    <flux:select.option :value="$option['id']" wire:key="workspace-option-{{ $option['id'] }}">
                        {{ $option['name'] }} — {{ $option['description'] }}
                    </flux:select.option>
                @empty
                    <flux:select.option.empty>{{ __('workspace.search_empty') }}</flux:select.option.empty>
                @endforelse
            </flux:select>
            <flux:error name="form.branchId" id="workspace-restaurant-error" class="text-danger!" />
            <flux:error name="form.search" id="workspace-search-error" class="text-danger!" />
            <flux:text wire:loading wire:target="form.search,more" role="status">{{ __('workspace.loading') }}</flux:text>
            @if ($next !== null)
                <flux:button type="button" wire:offline.attr="disabled" wire:click="more" variant="ghost">{{ __('workspace.more') }}</flux:button>
            @endif
            <flux:text wire:offline role="alert">{{ __('workspace.offline') }}</flux:text>
            <div class="flex flex-wrap justify-end gap-2">
                <flux:modal.close><flux:button type="button" variant="ghost">{{ __('workspace.cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="choose" wire:offline.attr="disabled">{{ __('workspace.open') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
