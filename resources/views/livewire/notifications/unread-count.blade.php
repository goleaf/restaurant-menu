<div data-component="notifications-unread-count" wire:poll.visible.5s="refreshUnreadCount">
    @if ($compact)
        <button
            type="button"
            wire:click="markAllRead"
            @class([
                'relative inline-flex size-10 items-center justify-center rounded-lg border text-sm font-semibold transition focus:outline-hidden focus:ring-2 focus:ring-amber-500/30',
                'border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100 dark:border-amber-900/70 dark:bg-amber-950/40 dark:text-amber-100' => $unreadCount > 0,
                'border-zinc-200 bg-white text-zinc-500 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300' => $unreadCount === 0,
            ])
            title="{{ $unreadCount > 0 ? __('ui.notifications.unread_count.mark_notifications_as_read') : __('ui.empty.no_notifications') }}"
            aria-label="{{ __('ui.notifications.unread_count.unread_notifications') }}"
        >
            <flux:icon.bell class="size-4" />

            @if ($unreadCount > 0)
                <span class="absolute -right-1 -top-1 inline-flex min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 text-[11px] font-bold leading-5 text-white">
                    {{ $unreadCount > 99 ? '99+' : $unreadCount }}
                </span>
            @endif
        </button>
    @else
        <div class="rounded-lg border border-zinc-200 bg-white p-3 text-sm shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between gap-3">
                <span class="inline-flex min-w-0 items-center gap-2 text-zinc-700 dark:text-zinc-200">
                    <flux:icon.bell class="size-4 shrink-0" />
                    <span class="truncate font-medium">{{ __('ui.notifications.unread_count.notifications') }}</span>
                </span>

                <span @class([
                    'inline-flex min-w-7 justify-center rounded-md px-2 py-0.5 text-xs font-semibold',
                    'bg-red-600 text-white' => $unreadCount > 0,
                    'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-300' => $unreadCount === 0,
                ])>
                    {{ $unreadCount > 99 ? '99+' : $unreadCount }}
                </span>
            </div>

            @if ($unreadCount === 0)
                <p class="mt-3 rounded-lg bg-zinc-50 px-3 py-2 text-xs text-zinc-500 dark:bg-zinc-950 dark:text-zinc-400">
                    {{ __('ui.empty.no_notifications') }}
                </p>
            @else
                <div class="mt-3 space-y-2">
                    @forelse ($notifications as $notification)
                        <article
                            wire:key="staff-notification-{{ $notification['id'] }}"
                            @class([
                                'rounded-lg border p-2.5',
                                'border-sky-200 bg-sky-50/70 dark:border-sky-900 dark:bg-sky-950/20' => $notification['tone'] === 'sky',
                                'border-orange-200 bg-orange-50/70 dark:border-orange-900 dark:bg-orange-950/20' => $notification['tone'] === 'orange',
                                'border-amber-200 bg-amber-50/70 dark:border-amber-900 dark:bg-amber-950/20' => $notification['tone'] === 'amber',
                                'border-emerald-200 bg-emerald-50/70 dark:border-emerald-900 dark:bg-emerald-950/20' => $notification['tone'] === 'emerald',
                                'border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950/50' => $notification['tone'] === 'zinc',
                            ])
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <x-ui.plain-text :text="$notification['title']" class="block text-xs font-semibold text-zinc-950 dark:text-white" :preserve-lines="false" />
                                    <x-ui.plain-text :text="$notification['body']" class="mt-1 block text-xs leading-5 text-zinc-700 dark:text-zinc-200" />

                                    @if ($notification['meta'] || $notification['created_label'])
                                        <p class="mt-1 text-[11px] text-zinc-500 dark:text-zinc-400">
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
                                    class="shrink-0 rounded-md border border-zinc-300 bg-white px-2 py-1 text-[11px] font-semibold text-zinc-700 transition hover:bg-zinc-50 focus:outline-hidden focus:ring-2 focus:ring-zinc-500/30 disabled:cursor-not-allowed disabled:opacity-60 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
                                >
                                    {{ __('ui.notifications.unread_count.read') }}
                                </button>
                            </div>
                        </article>
                    @empty
                        <p class="rounded-lg bg-zinc-50 px-3 py-2 text-xs text-zinc-500 dark:bg-zinc-950 dark:text-zinc-400">
                            {{ __('ui.empty.no_notifications') }}
                        </p>
                    @endforelse
                </div>

                <button
                    type="button"
                    wire:click="markAllRead"
                    wire:loading.attr="disabled"
                    wire:target="markAllRead"
                    class="mt-3 flex h-9 w-full items-center justify-center rounded-lg border border-zinc-300 bg-white px-3 text-xs font-semibold text-zinc-800 transition hover:bg-zinc-50 focus:outline-hidden focus:ring-2 focus:ring-zinc-500/30 disabled:cursor-not-allowed disabled:opacity-60 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
                >
                    {{ __('ui.notifications.unread_count.mark_all_as_read') }}
                </button>
            @endif
        </div>
    @endif
</div>
