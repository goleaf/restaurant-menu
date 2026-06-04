<div data-component="notifications-unread-count" wire:poll.5s="refreshUnreadCount">
    @if ($compact)
        <button
            type="button"
            wire:click="markAllRead"
            @class([
                'relative inline-flex size-10 items-center justify-center rounded-lg border text-sm font-semibold transition focus:outline-hidden focus:ring-2 focus:ring-amber-500/30',
                'border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100 dark:border-amber-900/70 dark:bg-amber-950/40 dark:text-amber-100' => $unreadCount > 0,
                'border-zinc-200 bg-white text-zinc-500 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300' => $unreadCount === 0,
            ])
            title="{{ $unreadCount > 0 ? __('Mark notifications as read') : __('No unread notifications') }}"
            aria-label="{{ __('Unread notifications') }}"
        >
            <flux:icon.bell class="size-4" />

            @if ($unreadCount > 0)
                <span class="absolute -right-1 -top-1 inline-flex min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 text-[11px] font-bold leading-5 text-white">
                    {{ $unreadCount > 99 ? '99+' : $unreadCount }}
                </span>
            @endif
        </button>
    @else
        <button
            type="button"
            wire:click="markAllRead"
            @class([
                'flex w-full items-center justify-between gap-3 rounded-lg border px-3 py-2 text-sm transition focus:outline-hidden focus:ring-2 focus:ring-amber-500/30',
                'border-amber-200 bg-amber-50 text-amber-900 hover:bg-amber-100 dark:border-amber-900/70 dark:bg-amber-950/40 dark:text-amber-100' => $unreadCount > 0,
                'border-zinc-200 bg-white text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300' => $unreadCount === 0,
            ])
            title="{{ $unreadCount > 0 ? __('Mark notifications as read') : __('No unread notifications') }}"
        >
            <span class="inline-flex min-w-0 items-center gap-2">
                <flux:icon.bell class="size-4 shrink-0" />
                <span class="truncate font-medium">{{ __('Notifications') }}</span>
            </span>

            <span @class([
                'inline-flex min-w-7 justify-center rounded-md px-2 py-0.5 text-xs font-semibold',
                'bg-red-600 text-white' => $unreadCount > 0,
                'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-300' => $unreadCount === 0,
            ])>
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        </button>
    @endif
</div>
