<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('ui.settings.profile.profile_settings') }}</flux:heading>

    <x-settings.layout :heading="__('layout.profile')" :subheading="__('ui.settings.profile.update_your_name_and_email_address')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('reports.csv.name')" type="text" required autofocus autocomplete="name" />

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
                <flux:select wire:model="locale">
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

        @if ($this->showDeleteUser)
            <livewire:settings.delete-user-form />
        @endif
    </x-settings.layout>
</section>
