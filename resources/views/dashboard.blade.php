<x-layouts::app :title="__('Dashboard')">
    <div data-layout="dashboard-overview" class="flex h-full w-full flex-1 flex-col gap-5">
        <header class="flex flex-col gap-2">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Workspace overview</p>
            <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">Dashboard</h1>
            <p class="max-w-2xl text-sm text-zinc-600 dark:text-zinc-300">
                Choose the interface zone you want to inspect. Domain features are intentionally not implemented yet.
            </p>
        </header>

        <div class="grid gap-4 md:grid-cols-2">
            <a href="{{ route('restaurant.dashboard') }}" class="rounded-lg border border-zinc-200 bg-white p-5 transition hover:border-zinc-300 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700" wire:navigate>
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Restaurant staff</p>
                <h2 class="mt-2 text-lg font-semibold text-zinc-950 dark:text-white">Restaurant dashboard</h2>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">Simple placeholder for restaurant operations.</p>
            </a>

            <a href="{{ route('superadmin.dashboard') }}" class="rounded-lg border border-zinc-200 bg-white p-5 transition hover:border-zinc-300 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700" wire:navigate>
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Platform staff</p>
                <h2 class="mt-2 text-lg font-semibold text-zinc-950 dark:text-white">Superadmin dashboard</h2>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">Simple placeholder for platform administration.</p>
            </a>
        </div>

        <div class="rounded-lg border border-dashed border-zinc-300 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-sm text-zinc-600 dark:text-zinc-300">
                Guest mobile UI is public at <a href="{{ route('guest.home') }}" class="font-medium underline underline-offset-4" wire:navigate>guest area</a>.
            </p>
        </div>
    </div>
</x-layouts::app>
