<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('ui.settings.appearance.appearance_settings') }}</flux:heading>

    <x-settings.layout :heading="__('ui.components.settings.layout.appearance')" :subheading=" __('ui.settings.appearance.update_the_appearance_settings_for_your_account')">
        <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
            <flux:radio value="light" icon="sun">{{ __('ui.settings.appearance.light') }}</flux:radio>
            <flux:radio value="dark" icon="moon">{{ __('ui.settings.appearance.dark') }}</flux:radio>
            <flux:radio value="system" icon="computer-desktop">{{ __('ui.settings.appearance.system') }}</flux:radio>
        </flux:radio.group>
    </x-settings.layout>
</section>
