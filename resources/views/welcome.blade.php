<x-layouts::guest :title="__('ui.views.welcome.restaurant_menu_saas')">
    <main id="main-content" tabindex="-1" data-page="public-entry" class="min-h-svh bg-stone-50 text-zinc-950 dark:bg-zinc-950 dark:text-white">
        <section class="mx-auto grid min-h-svh w-full max-w-6xl grid-rows-[auto_1fr_auto] gap-10 px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
            <header class="flex items-center justify-between gap-4">
                <a href="{{ route('home') }}" class="-m-2 flex min-h-touch items-center gap-2 rounded-control p-2 font-semibold focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-focus" wire:navigate>
                    <x-app-logo-icon class="size-8 text-zinc-900 dark:text-white" />
                    <span>{{ __('layout.app_name') }}</span>
                </a>

                <flux:button class="hidden min-h-touch sm:inline-flex" :href="route('login')" wire:navigate>{{ __('ui.views.welcome.staff_login') }}</flux:button>
            </header>

            <div class="grid self-center gap-8 lg:grid-cols-[minmax(0,1.15fr)_minmax(20rem,0.85fr)] lg:items-center lg:gap-14">
                <div class="flex flex-col items-start gap-5">
                    <p class="text-sm font-semibold text-brand-700 dark:text-brand-300">{{ __('ui.views.welcome.shared_hosting_restaurant_platform') }}</p>
                    <h1 class="max-w-3xl text-balance text-3xl font-semibold leading-tight sm:text-4xl lg:text-5xl">
                        {{ __('ui.views.welcome.qr_ordering_waiter_flow_kitchen_screens_and_restaurant_das') }}
                    </h1>
                    <p class="max-w-2xl text-base leading-7 text-zinc-600 dark:text-zinc-300 sm:text-lg">
                        {{ __('ui.views.welcome.guests_normally_start_from_a_permanent_qr_link_staff_can_m') }}
                    </p>

                    <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
                        <flux:button class="min-h-touch" data-primary-action="staff-login" variant="primary" icon-trailing="arrow-right" :href="route('login')" wire:navigate>
                            {{ __('ui.views.welcome.staff_login') }}
                        </flux:button>
                        <flux:button class="min-h-touch" icon="qr-code" :href="route('guest.home')" wire:navigate>
                            {{ __('ui.views.welcome.guest_start') }}
                        </flux:button>
                    </div>
                </div>

                <ol class="overflow-hidden rounded-card border border-border-subtle bg-surface shadow-card">
                    <li class="grid grid-cols-[3rem_minmax(0,1fr)] gap-3 border-b border-border-subtle p-4 sm:p-5">
                        <span class="font-semibold tabular-nums text-brand-700 dark:text-brand-300" aria-hidden="true">01</span>
                        <div>
                            <h2 class="font-semibold text-text-primary">{{ __('ui.views.welcome.steps.guest.title') }}</h2>
                            <p class="mt-1 text-sm leading-6 text-text-muted">{{ __('ui.views.welcome.steps.guest.description') }}</p>
                        </div>
                    </li>
                    <li class="grid grid-cols-[3rem_minmax(0,1fr)] gap-3 border-b border-border-subtle p-4 sm:p-5">
                        <span class="font-semibold tabular-nums text-brand-700 dark:text-brand-300" aria-hidden="true">02</span>
                        <div>
                            <h2 class="font-semibold text-text-primary">{{ __('ui.views.welcome.steps.staff.title') }}</h2>
                            <p class="mt-1 text-sm leading-6 text-text-muted">{{ __('ui.views.welcome.steps.staff.description') }}</p>
                        </div>
                    </li>
                    <li class="grid grid-cols-[3rem_minmax(0,1fr)] gap-3 p-4 sm:p-5">
                        <span class="font-semibold tabular-nums text-brand-700 dark:text-brand-300" aria-hidden="true">03</span>
                        <div>
                            <h2 class="font-semibold text-text-primary">{{ __('ui.views.welcome.steps.kitchen.title') }}</h2>
                            <p class="mt-1 text-sm leading-6 text-text-muted">{{ __('ui.views.welcome.steps.kitchen.description') }}</p>
                        </div>
                    </li>
                </ol>
            </div>

            <footer class="border-t border-border-subtle py-4 text-sm text-text-muted">
                {{ __('ui.views.welcome.no_app_required') }}
            </footer>
        </section>
    </main>
</x-layouts::guest>
