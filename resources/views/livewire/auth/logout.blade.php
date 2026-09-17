<form wire:submit="logout" novalidate>
    <flux:button variant="ghost" type="submit" class="w-full" icon="arrow-right-start-on-rectangle" data-test="logout-button" wire:loading.attr="disabled" wire:offline.attr="disabled">
        {{ __('navigation.logout') }}
    </flux:button>
</form>
