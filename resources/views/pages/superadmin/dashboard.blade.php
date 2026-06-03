<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<div data-layout="superadmin-dashboard" class="flex h-full w-full flex-1 flex-col gap-5">
    <header class="flex flex-col gap-2">
        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Platform workspace</p>
        <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">Superadmin dashboard</h1>
        <p class="max-w-2xl text-sm text-zinc-600 dark:text-zinc-300">
            Platform administration placeholder. Companies, plans, roles, analytics, and billing are intentionally not implemented yet.
        </p>
    </header>

    <section class="grid gap-4 md:grid-cols-3">
        @foreach (['Platform health', 'Tenants', 'Access control'] as $label)
            <div wire:key="superadmin-dashboard-{{ $label }}" class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $label }}</p>
                <p class="mt-3 text-lg font-semibold text-zinc-950 dark:text-white">Placeholder</p>
            </div>
        @endforeach
    </section>

    <section class="min-h-64 rounded-lg border border-dashed border-zinc-300 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <h2 class="text-base font-semibold text-zinc-950 dark:text-white">Platform shell</h2>
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
            This page only establishes the superadmin interface zone.
        </p>
    </section>
</div>
