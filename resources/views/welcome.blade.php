<x-layouts::guest :title="__('ui.views.welcome.restaurant_menu_saas')">
    <main data-page="public-entry" class="min-h-svh bg-stone-50 text-zinc-950 dark:bg-zinc-950 dark:text-white">
        <section class="mx-auto flex min-h-svh w-full max-w-5xl flex-col justify-between gap-8 px-4 py-6 sm:px-6 lg:px-8">
            <header class="flex items-center justify-between gap-4">
                <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold" wire:navigate>
                    <x-app-logo-icon class="size-8 text-zinc-900 dark:text-white" />
                    <span>{{ __('layout.app_name') }}</span>
                </a>

                <a
                    href="{{ route('login') }}"
                    class="inline-flex min-h-10 items-center justify-center rounded-lg border border-zinc-300 px-3 text-sm font-medium text-zinc-800 dark:border-zinc-700 dark:text-zinc-100"
                    wire:navigate
                >
                    {{ __('ui.views.welcome.staff_login') }}
                </a>
            </header>

            <div class="grid gap-6 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
                <div class="flex flex-col gap-5">
                    <p class="text-sm font-medium text-emerald-700 dark:text-emerald-300">{{ __('ui.views.welcome.shared_hosting_restaurant_platform') }}</p>
                    <h1 class="max-w-2xl text-3xl font-semibold leading-tight sm:text-4xl">
                        {{ __('ui.views.welcome.qr_ordering_waiter_flow_kitchen_screens_and_restaurant_das') }}
                    </h1>
                    <p class="max-w-xl text-base leading-7 text-zinc-600 dark:text-zinc-300">
                        {{ __('ui.views.welcome.guests_normally_start_from_a_permanent_qr_link_staff_can_m') }}
                    </p>
                </div>

                <div class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex flex-col gap-3">
                        <a
                            href="{{ route('guest.home') }}"
                            class="flex min-h-12 items-center justify-between rounded-lg border border-zinc-200 px-4 text-sm font-medium dark:border-zinc-800"
                            wire:navigate
                        >
                            <span>{{ __('ui.views.welcome.guest_start') }}</span>
                            <span aria-hidden="true">-></span>
                        </a>
                        <a
                            href="{{ route('restaurant.dashboard') }}"
                            class="flex min-h-12 items-center justify-between rounded-lg border border-zinc-200 px-4 text-sm font-medium dark:border-zinc-800"
                            wire:navigate
                        >
                            <span>{{ __('navigation.restaurant_dashboard') }}</span>
                            <span aria-hidden="true">-></span>
                        </a>
                        <a
                            href="{{ route('superadmin.dashboard') }}"
                            class="flex min-h-12 items-center justify-between rounded-lg border border-zinc-200 px-4 text-sm font-medium dark:border-zinc-800"
                            wire:navigate
                        >
                            <span>{{ __('ui.superadmin.dashboard.platform_dashboard') }}</span>
                            <span aria-hidden="true">-></span>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>
</x-layouts::guest>
