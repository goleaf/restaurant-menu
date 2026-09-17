<div class="flex flex-col gap-6">
    <x-auth-header :title="$title" :description="$message" />

    @if ($canSwitchAccount)
        <form wire:submit="switchAccount">
            <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('invitations.actions.switch_account') }}</flux:button>
        </form>
    @else
        <flux:button :href="$actionUrl" variant="primary" class="w-full" wire:loading.attr="disabled" wire:offline.attr="disabled">
            {{ $actionLabel }}
        </flux:button>
    @endif
</div>
