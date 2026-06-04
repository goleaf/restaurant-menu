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
                    Operational workspace for branch setup, service points, guest drafts, and staff workflows.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                @if ($canAccessWaiterDashboard ?? false)
                    <flux:button icon="clipboard-document-list" variant="primary" :href="route('restaurant.waiter.dashboard')" wire:navigate>
                        {{ __('Waiter dashboard') }}
                    </flux:button>
                @endif

                @if ($canAccessKitchenDashboard ?? false)
                    <flux:button icon="chef-hat" :href="route('restaurant.kitchen.dashboard')" wire:navigate>
                        {{ __('Kitchen screen') }}
                    </flux:button>
                @endif
            </div>
        </div>
    </header>

    <section class="grid gap-4 md:grid-cols-3">
        @foreach (['Setup', 'Guest flow', 'Waiter workflow'] as $label)
            <div wire:key="restaurant-dashboard-{{ $label }}" class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $label }}</p>
                <p class="mt-3 text-lg font-semibold text-zinc-950 dark:text-white">Available step by step</p>
            </div>
        @endforeach
    </section>

    <section class="min-h-64 rounded-lg border border-dashed border-zinc-300 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <h2 class="text-base font-semibold text-zinc-950 dark:text-white">Current implementation area</h2>
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
            The waiter dashboard shows table flow, and the kitchen screen shows department tickets through Livewire polling.
        </p>
    </section>
</div>
