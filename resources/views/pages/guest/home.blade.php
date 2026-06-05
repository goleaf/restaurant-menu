<?php

use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.guest')] class extends Component
{
    //
};
?>

<div data-layout="guest" class="min-h-svh">
    <header class="border-b border-zinc-200 bg-white/90 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-950/90">
        <div class="mx-auto flex w-full max-w-5xl items-center justify-between gap-3">
            <a href="{{ route('guest.home') }}" class="flex items-center gap-2 font-semibold" wire:navigate>
                <x-app-logo-icon class="size-8 text-zinc-900 dark:text-white" />
                <span>{{ config('app.name', 'Laravel') }}</span>
            </a>

            <a
                href="{{ route('login') }}"
                class="inline-flex min-h-10 items-center justify-center rounded-lg border border-zinc-300 px-3 text-sm font-medium text-zinc-800 dark:border-zinc-700 dark:text-zinc-100"
                wire:navigate
            >
                {{ __('navigation.staff') }}
            </a>
        </div>
    </header>

    <main class="mx-auto flex w-full max-w-md flex-col gap-5 px-4 py-6 sm:max-w-5xl sm:py-10">
        <section class="flex flex-col gap-4 rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-start justify-between gap-4">
                <div class="flex flex-col gap-2">
                    <p class="text-sm font-medium text-emerald-700 dark:text-emerald-300">{{ __('ui.pages.guest.home.guest_interface') }}</p>
                    <h1 class="text-2xl font-semibold leading-tight text-zinc-950 dark:text-white">
                        {{ __('ui.pages.guest.home.scan_a_table_qr_code_to_join_your_table') }}
                    </h1>
                </div>
                <span class="rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200">
                    {{ __('ui.pages.guest.home.mobile_first') }}
                </span>
            </div>

            <p class="text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                {{ __('ui.pages.guest.home.the_public_guest_flow_opens_from_a_permanent_q_token_li') }}
            </p>
        </section>

        <section class="grid gap-3 sm:grid-cols-3">
            @foreach ([
                __('ui.pages.guest.home.permanent_qr'),
                __('ui.pages.guest.home.table_session'),
                __('ui.pages.guest.home.shared_cart'),
            ] as $label)
                <div wire:key="guest-entry-{{ $loop->index }}" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                    <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ $label }}</p>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.pages.guest.home.handled_through_the_active_qr_table_flow') }}</p>
                </div>
            @endforeach
        </section>
    </main>
</div>
