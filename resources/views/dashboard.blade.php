<x-layouts::app :title="__('ui.headers.dashboard.title')">
    <div data-layout="dashboard-overview" class="flex h-full w-full flex-1 flex-col gap-5">
        <header class="flex flex-col gap-2">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('layout.app_name') }}</p>
            <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">{{ __('ui.headers.dashboard.title') }}</h1>
            <p class="max-w-2xl text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('ui.headers.dashboard.description') }}
            </p>
        </header>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(18rem,0.65fr)]">
            <a data-primary-workspace="restaurant" href="{{ route('restaurant.dashboard') }}" class="group rounded-card border border-brand-200 bg-brand-50 p-5 shadow-card transition hover:border-brand-300 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2 dark:border-brand-800 dark:bg-brand-950 sm:p-6" wire:navigate>
                <div class="flex items-start justify-between gap-5">
                    <div>
                        <p class="text-sm font-semibold text-brand-700 dark:text-brand-300">{{ __('ui.headers.dashboard.restaurant_staff') }}</p>
                        <h2 class="mt-2 text-xl font-semibold text-text-primary">{{ __('navigation.restaurant_dashboard') }}</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-text-muted">{{ __('ui.headers.dashboard.restaurant_staff_description') }}</p>
                    </div>
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-control bg-brand-700 text-white transition-transform group-hover:translate-x-1 motion-reduce:transition-none dark:bg-brand-300 dark:text-brand-950" aria-hidden="true">
                        <flux:icon name="arrow-right" class="size-5 rtl:rotate-180" />
                    </span>
                </div>
            </a>

            @if ($canAccessOnboarding ?? false)
                <a href="{{ route('onboarding.restaurant') }}" class="rounded-card border border-border-subtle bg-surface p-5 shadow-card transition hover:border-brand-300 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2" wire:navigate>
                    <p class="text-sm font-medium text-text-muted">{{ __('ui.headers.dashboard.quick_start') }}</p>
                    <h2 class="mt-2 text-lg font-semibold text-text-primary">{{ __('navigation.onboarding') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-text-muted">{{ __('ui.headers.dashboard.setup_description') }}</p>
                </a>
            @endif

            @if ($canAccessPlatformDashboard ?? false)
                <a href="{{ route('superadmin.dashboard') }}" class="rounded-card border border-border-subtle bg-surface p-5 shadow-card transition hover:border-brand-300 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2 xl:col-start-2" wire:navigate>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('ui.headers.dashboard.platform_staff') }}</p>
                    <h2 class="mt-2 text-lg font-semibold text-zinc-950 dark:text-white">{{ __('navigation.superadmin') }}</h2>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ __('ui.headers.dashboard.platform_staff_description') }}</p>
                </a>
            @endif
        </div>

        <aside class="rounded-card border border-border-subtle bg-surface-muted p-5">
            <p class="text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('ui.views.dashboard.guest_mobile_ui_starts_from_permanent_qr_links') }}
                {{ __('ui.views.dashboard.the_public_fallback_page_is_available_at') }}
                <a href="{{ route('guest.home') }}" class="font-medium underline underline-offset-4" wire:navigate>{{ __('navigation.guest_area') }}</a>.
            </p>
        </aside>
    </div>
</x-layouts::app>
