<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('ui.settings.profile.profile_settings') }}</flux:heading>

    <x-settings.layout :heading="__('layout.profile')" :subheading="__('ui.settings.profile.update_your_name_and_email_address')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('reports.csv.name')" type="text" required autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('ui.auth.reset_password.email')" type="email" required autocomplete="email" />

                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text class="mt-4">
                            {{ __('ui.settings.profile.your_email_address_is_unverified') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('ui.settings.profile.click_here_to_re_send_the_verification_email') }}
                            </flux:link>
                        </flux:text>

                    </div>
                @endif
            </div>

            <flux:field>
                <flux:label>{{ __('guest.table.interface_language') }}</flux:label>
                <flux:select id="profile-interface-language" wire:model="locale">
                    @foreach ($localeOptions as $localeCode => $localeLabel)
                        <flux:select.option wire:key="profile-locale-{{ $localeCode }}" value="{{ $localeCode }}">
                            {{ $localeLabel }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="locale" />
            </flux:field>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ __('ui.actions.save') }}</flux:button>
            </div>
        </form>

        <livewire:settings.display-formats />

        <section id="profile-appearance" aria-labelledby="profile-appearance-heading" class="my-6 space-y-6">
            <div>
                <flux:heading id="profile-appearance-heading" level="2">{{ __('ui.settings.appearance.appearance_settings') }}</flux:heading>
                <flux:subheading id="profile-appearance-description">{{ __('ui.settings.appearance.update_the_appearance_settings_for_your_account') }}</flux:subheading>
            </div>

            <flux:radio.group x-data variant="segmented" x-model="$flux.appearance" aria-labelledby="profile-appearance-heading" aria-describedby="profile-appearance-description" class="h-auto! flex-col sm:flex-row">
                <flux:radio value="light" icon="sun" class="min-h-touch">{{ __('ui.settings.appearance.light') }}</flux:radio>
                <flux:radio value="dark" icon="moon" class="min-h-touch">{{ __('ui.settings.appearance.dark') }}</flux:radio>
                <flux:radio value="system" icon="computer-desktop" class="min-h-touch">{{ __('ui.settings.appearance.system') }}</flux:radio>
            </flux:radio.group>
        </section>

        @if ($this->showDeleteUser)
            <livewire:settings.delete-user-form />
        @endif
    </x-settings.layout>
</section>
