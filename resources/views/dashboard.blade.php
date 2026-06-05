<x-layouts::app :title="__('ui.headers.dashboard.title')">
    <div data-layout="dashboard-overview" class="flex h-full w-full flex-1 flex-col gap-5">
        <header class="flex flex-col gap-2">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('layout.app_name') }}</p>
            <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">{{ __('ui.headers.dashboard.title') }}</h1>
            <p class="max-w-2xl text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('ui.headers.dashboard.description') }}
            </p>
        </header>

        <div class="grid gap-4 md:grid-cols-2">
            <a href="{{ route('onboarding.restaurant') }}" class="rounded-lg border border-zinc-200 bg-white p-5 transition hover:border-zinc-300 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700" wire:navigate>
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('ui.headers.dashboard.quick_start') }}</p>
                <h2 class="mt-2 text-lg font-semibold text-zinc-950 dark:text-white">{{ __('navigation.onboarding') }}</h2>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ __('ui.headers.dashboard.setup_description') }}</p>
            </a>

            <a href="{{ route('restaurant.dashboard') }}" class="rounded-lg border border-zinc-200 bg-white p-5 transition hover:border-zinc-300 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700" wire:navigate>
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('ui.headers.dashboard.restaurant_staff') }}</p>
                <h2 class="mt-2 text-lg font-semibold text-zinc-950 dark:text-white">{{ __('navigation.restaurant_dashboard') }}</h2>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ __('ui.headers.dashboard.restaurant_staff_description') }}</p>
            </a>

            @if ($canAccessPlatformDashboard ?? false)
                <a href="{{ route('superadmin.dashboard') }}" class="rounded-lg border border-zinc-200 bg-white p-5 transition hover:border-zinc-300 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700" wire:navigate>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('ui.headers.dashboard.platform_staff') }}</p>
                    <h2 class="mt-2 text-lg font-semibold text-zinc-950 dark:text-white">{{ __('navigation.superadmin') }}</h2>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ __('ui.headers.dashboard.platform_staff_description') }}</p>
                </a>
            @endif
        </div>

        <div class="rounded-lg border border-dashed border-zinc-300 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('Guest mobile UI starts from permanent QR links.') }}
                {{ __('The public fallback page is available at') }}
                <a href="{{ route('guest.home') }}" class="font-medium underline underline-offset-4" wire:navigate>{{ __('navigation.guest_area') }}</a>.
            </p>
        </div>
    </div>
</x-layouts::app>
