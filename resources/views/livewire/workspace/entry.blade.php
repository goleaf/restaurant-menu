<flux:main data-workspace-entry id="main-content" class="grid content-start justify-items-start gap-6">
    <flux:heading level="1" size="xl">{{ __('workspace.choose') }}</flux:heading>
    <flux:text>{{ __('workspace.entry_description') }}</flux:text>
    @if ($canSetUp)
        <flux:button href="{{ route('onboarding.restaurant') }}" wire:navigate icon="plus">{{ __('navigation.onboarding') }}</flux:button>
    @elseif (! $hasRestaurants)
        <flux:text>{{ __('workspace.no_assignment') }}</flux:text>
    @endif
</flux:main>
