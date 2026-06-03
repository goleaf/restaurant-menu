<x-layouts::guest :title="__('Guest area')">
    <main class="min-h-svh bg-stone-50 text-zinc-950 dark:bg-zinc-950 dark:text-white">
        <section class="mx-auto flex min-h-svh w-full max-w-5xl flex-col justify-between gap-8 px-4 py-6 sm:px-6 lg:px-8">
            <header class="flex items-center justify-between gap-4">
                <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold" wire:navigate>
                    <x-app-logo-icon class="size-8 text-zinc-900 dark:text-white" />
                    <span>{{ config('app.name', 'Laravel') }}</span>
                </a>

                <a
                    href="{{ route('login') }}"
                    class="inline-flex min-h-10 items-center justify-center rounded-lg border border-zinc-300 px-3 text-sm font-medium text-zinc-800 dark:border-zinc-700 dark:text-zinc-100"
                    wire:navigate
                >
                    Staff login
                </a>
            </header>

            <div class="grid gap-6 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
                <div class="flex flex-col gap-5">
                    <p class="text-sm font-medium text-emerald-700 dark:text-emerald-300">Public shell</p>
                    <h1 class="max-w-2xl text-3xl font-semibold leading-tight sm:text-4xl">
                        Guest and staff interface zones are ready.
                    </h1>
                    <p class="max-w-xl text-base leading-7 text-zinc-600 dark:text-zinc-300">
                        This is the clean project entry point. Restaurant entities, menus, QR flows, table sessions, and orders are not implemented yet.
                    </p>
                </div>

                <div class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex flex-col gap-3">
                        <a
                            href="{{ route('guest.home') }}"
                            class="flex min-h-12 items-center justify-between rounded-lg border border-zinc-200 px-4 text-sm font-medium dark:border-zinc-800"
                            wire:navigate
                        >
                            <span>Guest placeholder</span>
                            <span aria-hidden="true">-></span>
                        </a>
                        <a
                            href="{{ route('restaurant.dashboard') }}"
                            class="flex min-h-12 items-center justify-between rounded-lg border border-zinc-200 px-4 text-sm font-medium dark:border-zinc-800"
                            wire:navigate
                        >
                            <span>Restaurant dashboard</span>
                            <span aria-hidden="true">-></span>
                        </a>
                        <a
                            href="{{ route('superadmin.dashboard') }}"
                            class="flex min-h-12 items-center justify-between rounded-lg border border-zinc-200 px-4 text-sm font-medium dark:border-zinc-800"
                            wire:navigate
                        >
                            <span>Superadmin dashboard</span>
                            <span aria-hidden="true">-></span>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>
</x-layouts::guest>
