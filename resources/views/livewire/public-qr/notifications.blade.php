<section
    data-component="guest-notifications"
    wire:poll.visible.{{ $pollingIntervalSeconds }}s="refreshNotifications"
    class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-xs font-medium uppercase text-emerald-700 dark:text-emerald-300">{{ __('ui.guest.notifications.uvedomleniia') }}</p>
            <h2 class="mt-1 text-lg font-semibold leading-tight text-zinc-950 dark:text-white">{{ __('ui.guest.notifications.cto_proisxodit_za_stolom') }}</h2>
        </div>

        <span @class([
            'inline-flex min-w-7 justify-center rounded-md px-2 py-1 text-xs font-semibold',
            'bg-red-600 text-white' => $unreadCount > 0,
            'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-300' => $unreadCount === 0,
        ])>
            {{ $unreadCount > 99 ? '99+' : $unreadCount }}
        </span>
    </div>

    @if (! $canRead)
        <p class="mt-3 rounded-lg bg-zinc-50 px-3 py-2 text-sm text-zinc-600 dark:bg-zinc-950 dark:text-zinc-300">
            {{ __('ui.guest.notifications.uvedomleniia_dostupny_aktivnym_gostiam_za_etim_stolo') }}
        </p>
    @elseif ($unreadCount === 0)
        <p class="mt-3 rounded-lg bg-zinc-50 px-3 py-2 text-sm text-zinc-600 dark:bg-zinc-950 dark:text-zinc-300">
            {{ __('ui.empty.no_notifications') }}
        </p>
    @else
        <div class="mt-4 space-y-3">
            @forelse ($notifications as $notification)
                <article
                    wire:key="guest-notification-{{ $notification['id'] }}"
                    @class([
                        'rounded-lg border p-3',
                        'border-amber-200 bg-amber-50/70 dark:border-amber-900 dark:bg-amber-950/20' => $notification['tone'] === 'amber',
                        'border-emerald-200 bg-emerald-50/70 dark:border-emerald-900 dark:bg-emerald-950/20' => $notification['tone'] === 'emerald',
                        'border-rose-200 bg-rose-50/70 dark:border-rose-900 dark:bg-rose-950/20' => $notification['tone'] === 'rose',
                        'border-sky-200 bg-sky-50/70 dark:border-sky-900 dark:bg-sky-950/20' => $notification['tone'] === 'sky',
                        'border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950/50' => $notification['tone'] === 'zinc',
                    ])
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <x-ui.plain-text :text="$notification['title']" class="block text-sm font-semibold text-zinc-950 dark:text-white" :preserve-lines="false" />
                            <x-ui.plain-text :text="$notification['body']" class="mt-1 block text-sm leading-5 text-zinc-700 dark:text-zinc-200" />

                            @if ($notification['meta'] || $notification['created_label'])
                                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                                    <x-ui.plain-text :text="$notification['meta']" class="inline" :preserve-lines="false" />

                                    @if ($notification['meta'] && $notification['created_label'])
                                        ·
                                    @endif

                                    {{ $notification['created_label'] }}
                                </p>
                            @endif
                        </div>

                        <button
                            type="button"
                            wire:click="markNotificationRead('{{ $notification['id'] }}')"
                            wire:loading.attr="disabled"
                            wire:target="markNotificationRead('{{ $notification['id'] }}')"
                            class="shrink-0 rounded-md border border-zinc-300 bg-white px-2.5 py-1 text-xs font-semibold text-zinc-700 transition hover:bg-zinc-50 focus:outline-hidden focus:ring-2 focus:ring-zinc-500/30 disabled:cursor-not-allowed disabled:opacity-60 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
                        >
                            {{ __('ui.guest.notifications.ok') }}
                        </button>
                    </div>
                </article>
            @empty
                <p class="rounded-lg bg-zinc-50 px-3 py-2 text-sm text-zinc-600 dark:bg-zinc-950 dark:text-zinc-300">
                    {{ __('ui.empty.no_notifications') }}
                </p>
            @endforelse
        </div>

        <button
            type="button"
            wire:click="markAllRead"
            wire:loading.attr="disabled"
            wire:target="markAllRead"
            class="mt-4 flex h-10 w-full items-center justify-center rounded-lg border border-zinc-300 bg-white px-3 text-sm font-semibold text-zinc-800 transition hover:bg-zinc-50 focus:outline-hidden focus:ring-2 focus:ring-zinc-500/30 disabled:cursor-not-allowed disabled:opacity-60 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
        >
            {{ __('ui.guest.notifications.otmetit_vse_procitannym') }}
        </button>
    @endif
</section>
