<div class="min-h-svh">
    <header class="border-b border-zinc-200 bg-white/90 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-950/90">
        <div class="mx-auto flex w-full max-w-3xl items-center justify-between gap-3">
            <a href="{{ route('guest.home') }}" class="flex items-center gap-2 font-semibold" wire:navigate>
                <x-app-logo-icon class="size-8 text-zinc-900 dark:text-white" />
                <span>{{ config('app.name', 'Laravel') }}</span>
            </a>

            @if ($state === 'ready')
                <span class="rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200">
                    {{ $landing['short_code'] }}
                </span>
            @endif
        </div>
    </header>

    <main class="mx-auto flex w-full max-w-md flex-col gap-4 px-4 py-6 sm:max-w-3xl sm:py-10">
        @if ($state === 'ready')
            <section class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-col gap-2">
                    <p class="text-sm font-medium text-emerald-700 dark:text-emerald-300">{{ $landing['brand_name'] }}</p>
                    <h1 class="text-2xl font-semibold leading-tight text-zinc-950 dark:text-white">{{ $title }}</h1>
                    <p class="text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $message }}</p>
                </div>

                <div class="mt-5 grid gap-3">
                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/50">
                        <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('Place') }}</p>
                        <p class="mt-1 text-lg font-semibold text-zinc-950 dark:text-white">{{ $landing['service_point_name'] }}</p>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ __($landing['service_point_type']) }}</p>
                    </div>

                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/50">
                        <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('Location') }}</p>
                        <p class="mt-1 text-base font-semibold text-zinc-950 dark:text-white">{{ $landing['branch_name'] }}</p>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                            {{ $landing['branch_city'] }}, {{ $landing['branch_country'] }}
                        </p>

                        @if ($landing['area_name'])
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ __('Area') }}: {{ $landing['area_name'] }}</p>
                        @endif
                    </div>
                </div>

                <div class="mt-5 rounded-lg bg-emerald-50 p-4 text-sm text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
                    {{ __('Guest session and menu will appear here in the next steps.') }}
                </div>
            </section>
        @else
            <section class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-col gap-3">
                    <span class="flex size-10 items-center justify-center rounded-lg bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-200">
                        !
                    </span>

                    <div class="space-y-2">
                        <h1 class="text-2xl font-semibold leading-tight text-zinc-950 dark:text-white">{{ $title }}</h1>
                        <p class="text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $message }}</p>
                    </div>
                </div>
            </section>
        @endif
    </main>
</div>
