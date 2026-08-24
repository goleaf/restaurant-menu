<div data-layout="guest" class="min-h-svh">
    <header class="border-b border-border-subtle bg-surface-raised px-4 py-3">
        <div class="mx-auto flex w-full max-w-5xl items-center justify-between gap-3">
            <a href="{{ route('guest.home') }}" class="-m-2 flex min-h-touch items-center gap-2 rounded-control p-2 font-semibold focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-focus" wire:navigate>
                <x-app-logo-icon class="size-8 text-text-primary" />
                <span>{{ __('layout.app_name') }}</span>
            </a>

            <a
                href="{{ route('login') }}"
                class="inline-flex min-h-touch items-center justify-center rounded-control border border-border-strong px-3 text-sm font-medium text-text-primary focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-focus"
                wire:navigate
            >
                {{ __('navigation.staff') }}
            </a>
        </div>
    </header>

    <main id="main-content" tabindex="-1" class="mx-auto flex w-full max-w-md flex-col gap-5 px-4 py-6 sm:max-w-5xl sm:py-10">
        <section class="flex flex-col gap-4 rounded-card border border-border-subtle bg-surface p-5 sm:p-7">
            <div class="flex items-start justify-between gap-4">
                <div class="flex flex-col gap-2">
                    <p class="text-sm font-medium text-brand-700 dark:text-brand-300">{{ __('ui.pages.guest.home.guest_interface') }}</p>
                    <h1 class="text-balance text-2xl font-semibold leading-tight text-text-primary sm:text-3xl">
                        {{ __('ui.pages.guest.home.scan_a_table_qr_code_to_join_your_table') }}
                    </h1>
                </div>
                <span class="shrink-0 rounded-control bg-success-surface px-2.5 py-1 text-xs font-medium text-success">
                    {{ __('ui.pages.guest.home.mobile_first') }}
                </span>
            </div>

            <p class="max-w-2xl text-sm leading-6 text-text-muted">
                {{ __('ui.pages.guest.home.the_public_guest_flow_opens_from_a_permanent_q_token_li') }}
            </p>
        </section>

        <section aria-labelledby="guest-journey-title" class="rounded-card border border-border-subtle bg-surface p-5 sm:p-7">
            <h2 id="guest-journey-title" class="text-lg font-semibold text-text-primary">{{ __('ui.pages.guest.home.how_it_works') }}</h2>

            <ol class="mt-5 grid gap-5 sm:grid-cols-3">
                @foreach ($journeySteps as $step)
                    <li wire:key="guest-entry-{{ $step['title'] }}" data-journey-step="{{ $loop->iteration }}" class="grid grid-cols-[2.5rem_minmax(0,1fr)] gap-3 sm:grid-cols-1">
                        <span class="flex size-10 items-center justify-center rounded-control bg-brand-100 text-sm font-semibold text-brand-800 dark:bg-brand-900 dark:text-brand-100" aria-hidden="true">
                            {{ $loop->iteration }}
                        </span>
                        <div>
                            <h3 class="text-sm font-semibold text-text-primary">{{ __($step['title']) }}</h3>
                            <p class="mt-1 text-sm leading-6 text-text-muted">{{ __($step['description']) }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>
    </main>
</div>
