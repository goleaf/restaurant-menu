<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Workspaces')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Overview') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="building-office" :href="route('organizations.index')" :current="request()->routeIs('organizations.*')" wire:navigate>
                        {{ __('Organizations') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="sparkles" :href="route('onboarding.restaurant')" :current="request()->routeIs('onboarding.*')" wire:navigate>
                        {{ __('Настроить ресторан') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="layout-grid" :href="route('restaurant.dashboard')" :current="request()->routeIs('restaurant.dashboard')" wire:navigate>
                        {{ __('Restaurant') }}
                    </flux:sidebar.item>

                    @if ($canAccessQrLookup ?? false)
                        <flux:sidebar.item icon="qr-code" :href="route('restaurant.qr-lookup.index')" :current="request()->routeIs('restaurant.qr-lookup.*')" wire:navigate>
                            {{ __('QR lookup') }}
                        </flux:sidebar.item>
                    @endif

                    @if ($canAccessWaiterDashboard ?? false)
                        <flux:sidebar.item icon="clipboard-document-list" :href="route('restaurant.waiter.dashboard')" :current="request()->routeIs('restaurant.waiter.*')" wire:navigate>
                            {{ __('Waiter') }}
                        </flux:sidebar.item>
                    @endif

                    @if ($canAccessKitchenDashboard ?? false)
                        <flux:sidebar.item icon="fire" :href="route('restaurant.kitchen.dashboard')" :current="request()->routeIs('restaurant.kitchen.*')" wire:navigate>
                            {{ __('Kitchen') }}
                        </flux:sidebar.item>
                    @endif

                    @if ($canAccessBarDashboard ?? false)
                        <flux:sidebar.item icon="beaker" :href="route('restaurant.bar.dashboard')" :current="request()->routeIs('restaurant.bar.*')" wire:navigate>
                            {{ __('Bar') }}
                        </flux:sidebar.item>
                    @endif

                    @if ($canAccessAuditLog ?? false)
                        <flux:sidebar.item icon="shield-check" :href="route('restaurant.audit-log.index')" :current="request()->routeIs('restaurant.audit-log.*')" wire:navigate>
                            {{ __('Audit log') }}
                        </flux:sidebar.item>
                    @endif

                    @if ($canAccessDataExports ?? false)
                        <flux:sidebar.item icon="arrow-down-tray" :href="route('restaurant.exports.index')" :current="request()->routeIs('restaurant.exports.*')" wire:navigate>
                            {{ __('Exports') }}
                        </flux:sidebar.item>
                    @endif

                    @if ($canAccessPlatformDashboard ?? false)
                        <flux:sidebar.item icon="rectangle-group" :href="route('superadmin.dashboard')" :current="request()->routeIs('superadmin.*')" wire:navigate>
                            {{ __('Platform') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <div class="px-3 pb-3">
                <livewire:notifications.unread-count />
            </div>

            <flux:sidebar.nav>
                <flux:sidebar.item icon="home" :href="route('guest.home')" wire:navigate>
                    {{ __('Guest area') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="layout-grid" :href="route('profile.edit')" :current="request()->routeIs('profile.edit')" wire:navigate>
                    {{ __('Settings') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <livewire:notifications.unread-count :compact="true" />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
