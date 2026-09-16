@props(['module', 'heading' => null, 'description' => null])

<div data-page-module-status="{{ $module }}" class="mb-4">
    <flux:callout icon="arrow-path" color="amber" class="callout-contrast" role="status">
        <flux:callout.heading>{{ $heading ?? __('frontend.editor_loading') }}</flux:callout.heading>
        <flux:callout.text>{{ $description ?? __('frontend.editor_loading_help') }}</flux:callout.text>
        <x-slot:actions>
            <flux:button as="a" href="" variant="filled" class="min-h-touch" data-page-module-reload>{{ __('frontend.reload') }}</flux:button>
        </x-slot:actions>
    </flux:callout>
</div>
