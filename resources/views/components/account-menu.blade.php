@props(['name', 'email', 'initials'])

<flux:dropdown position="bottom" align="end" data-account-menu>
    <flux:profile
        :initials="$initials"
        :chevron="false"
        :aria-label="__('navigation.account_menu', ['initials' => $initials, 'name' => $name])"
        class="min-h-touch min-w-touch justify-center"
        data-test="sidebar-menu-button"
    />

    <flux:menu class="w-64 max-w-[calc(100vw-2rem)] border-border-subtle! bg-surface-raised! shadow-elevated!">
        <div class="min-w-0 px-2 py-2 text-start text-sm">
            <flux:heading class="break-words">{{ $name }}</flux:heading>
            <flux:text class="break-all">{{ $email }}</flux:text>
        </div>
        <flux:menu.separator />
        <flux:menu.item :href="route('profile.edit')" icon="cog-6-tooth" class="min-h-touch" wire:navigate>
            {{ __('navigation.settings') }}
        </flux:menu.item>
        <flux:menu.item :href="route('profile.edit').'#profile-interface-language'" icon="language" class="min-h-touch" wire:navigate>
            {{ __('navigation.language') }}
        </flux:menu.item>
        <flux:menu.group :heading="__('ui.components.settings.layout.appearance')">
            <flux:menu.radio.group x-data x-model="$flux.appearance" :aria-label="__('ui.components.settings.layout.appearance')">
                <flux:menu.radio value="light" class="min-h-touch">{{ __('ui.settings.appearance.light') }}</flux:menu.radio>
                <flux:menu.radio value="dark" class="min-h-touch">{{ __('ui.settings.appearance.dark') }}</flux:menu.radio>
                <flux:menu.radio value="system" class="min-h-touch">{{ __('ui.settings.appearance.system') }}</flux:menu.radio>
            </flux:menu.radio.group>
        </flux:menu.group>
        <flux:menu.separator />
        <form method="POST" action="{{ route('logout') }}" class="w-full">
            @csrf
            <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="min-h-touch w-full cursor-pointer" data-test="logout-button">
                {{ __('navigation.logout') }}
            </flux:menu.item>
        </form>
    </flux:menu>
</flux:dropdown>
