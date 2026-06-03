<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<div data-layout="restaurant-dashboard" class="flex h-full w-full flex-1 flex-col gap-5">
    <header class="flex flex-col gap-2">
        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Restaurant workspace</p>
        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">Restaurant dashboard</h1>
                <p class="mt-1 max-w-2xl text-sm text-zinc-600 dark:text-zinc-300">
                    Operational dashboard placeholder. Venues, menus, service points, and orders are not implemented yet.
                </p>
            </div>
        </div>
    </header>

    <section class="grid gap-4 md:grid-cols-3">
        @foreach (['Setup', 'Guest flow', 'Staff workflow'] as $label)
            <div wire:key="restaurant-dashboard-{{ $label }}" class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $label }}</p>
                <p class="mt-3 text-lg font-semibold text-zinc-950 dark:text-white">Not configured</p>
            </div>
        @endforeach
    </section>

    <section class="min-h-64 rounded-lg border border-dashed border-zinc-300 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <h2 class="text-base font-semibold text-zinc-950 dark:text-white">Next implementation area</h2>
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
            This space is reserved for restaurant administration once the domain model exists.
        </p>
    </section>
</div>
