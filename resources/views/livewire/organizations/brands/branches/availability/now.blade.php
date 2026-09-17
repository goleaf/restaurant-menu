<div class="rm-availability__section" data-availability-panel="now">
    <flux:card>
        <flux:heading size="lg">{{ __('availability.branch_now') }}</flux:heading>
        @include('livewire.organizations.brands.branches.availability.result', ['result' => $now])
        <flux:text>{{ __('availability.checked_at', ['time' => $now['evaluated_at']]) }}</flux:text>
        @if ($canManageSettings)
            <flux:button wire:click="openPause" wire:offline.attr="disabled">{{ __('availability.manage_pause') }}</flux:button>
        @endif
    </flux:card>
    @if ($editor === 'pause')
        @include('livewire.organizations.brands.branches.availability.pause')
    @endif
</div>
