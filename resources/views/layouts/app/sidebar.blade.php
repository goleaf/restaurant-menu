<!DOCTYPE html>
<html lang="{{ __('meta.document_language') }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <a href="#main-content" class="skip-link">
            {{ __('ui.accessibility.skip_to_content') }}
        </a>
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" class="min-h-touch" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="min-h-touch min-w-touch lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('navigation.workspaces')" class="grid [&>div:first-child>div]:!text-text-muted">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="$currentNavigation['dashboard']" wire:navigate>
                        {{ __('navigation.dashboard') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="building-office" :href="route('organizations.index')" :current="$currentNavigation['organizations']" wire:navigate>
                        {{ __('navigation.organizations') }}
                    </flux:sidebar.item>

                    @if ($canAccessOnboarding ?? false)
                        <flux:sidebar.item icon="sparkles" :href="route('onboarding.restaurant')" :current="$currentNavigation['onboarding']" wire:navigate>
                            {{ __('navigation.onboarding') }}
                        </flux:sidebar.item>
                    @endif

                    <flux:sidebar.item icon="layout-grid" :href="route('restaurant.dashboard')" :current="$currentNavigation['restaurant_dashboard']" wire:navigate>
                        {{ __('navigation.restaurant') }}
                    </flux:sidebar.item>

                    @if ($canAccessQrLookup ?? false)
                        <flux:sidebar.item icon="qr-code" :href="route('restaurant.qr-lookup.index')" :current="$currentNavigation['qr_lookup']" wire:navigate>
                            {{ __('navigation.qr_codes') }}
                        </flux:sidebar.item>
                    @endif

                    @if ($canAccessWaiterDashboard ?? false)
                        <flux:sidebar.item icon="clipboard-document-list" :href="route('restaurant.waiter.dashboard')" :current="$currentNavigation['waiter']" wire:navigate>
                            {{ __('navigation.waiter') }}
                        </flux:sidebar.item>
                    @endif

                    @if ($canAccessKitchenDashboard ?? false)
                        <flux:sidebar.item icon="fire" :href="route('restaurant.kitchen.dashboard')" :current="$currentNavigation['kitchen']" wire:navigate>
                            {{ __('navigation.kitchen') }}
                        </flux:sidebar.item>
                    @endif

                    @if ($canAccessBarDashboard ?? false)
                        <flux:sidebar.item icon="beaker" :href="route('restaurant.bar.dashboard')" :current="$currentNavigation['bar']" wire:navigate>
                            {{ __('navigation.bar') }}
                        </flux:sidebar.item>
                    @endif

                    @if ($canAccessAuditLog ?? false)
                        <flux:sidebar.item icon="shield-check" :href="route('restaurant.audit-log.index')" :current="$currentNavigation['audit_log']" wire:navigate>
                            {{ __('navigation.audit_log') }}
                        </flux:sidebar.item>
                    @endif

                    @if ($canAccessDataExports ?? false)
                        <flux:sidebar.item icon="arrow-down-tray" :href="route('restaurant.exports.index')" :current="$currentNavigation['exports']" wire:navigate>
                            {{ __('navigation.exports') }}
                        </flux:sidebar.item>
                    @endif

                    @if ($canAccessPlatformDashboard ?? false)
                        <flux:sidebar.item icon="rectangle-group" :href="route('superadmin.dashboard')" :current="$currentNavigation['superadmin']" wire:navigate>
                            {{ __('navigation.superadmin') }}
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
                    {{ __('navigation.guest_area') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="layout-grid" :href="route('profile.edit')" :current="$currentNavigation['profile']" wire:navigate>
                    {{ __('navigation.settings') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            @if ($authenticatedUser !== null)
                <x-desktop-user-menu
                    class="hidden lg:block"
                    :name="$authenticatedUser['name']"
                    :email="$authenticatedUser['email']"
                    :initials="$authenticatedUser['initials']"
                />
            @endif
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="min-h-touch min-w-touch lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <livewire:notifications.unread-count :compact="true" />

            @if ($authenticatedUser !== null)
            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="$authenticatedUser['initials']"
                    :aria-label="__('navigation.account_menu', ['initials' => $authenticatedUser['initials'], 'name' => $authenticatedUser['name']])"
                    class="min-h-touch"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="$authenticatedUser['name']"
                                    :initials="$authenticatedUser['initials']"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ $authenticatedUser['name'] }}</flux:heading>
                                    <flux:text class="truncate">{{ $authenticatedUser['email'] }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('navigation.settings') }}
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
                            {{ __('navigation.logout') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
            @endif
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
