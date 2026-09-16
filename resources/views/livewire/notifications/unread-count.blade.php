<div
    x-data="notificationPanel" data-component="notifications-unread-count" wire:poll.visible.5s="refreshUnreadCount">
    <flux:modal.trigger name="staff-notifications">
        <flux:button
            x-on:click="$el.focus(); loadPanel()"
            variant="ghost"
            icon="bell"
            class="min-h-touch min-w-touch"
            :aria-label="__('notifications.panel.open', ['count' => $unreadCount])"
            data-notification-label="{{ __('notifications.panel.open', ['count' => ':count']) }}"
            x-bind:aria-label="$el.dataset.notificationLabel.replace(':count', $wire.unreadCount)"
            aria-haspopup="dialog"
            data-notification-trigger
        >
            <flux:badge size="sm" color="zinc" aria-hidden="true" x-show="$wire.unreadCount > 0" x-text="$wire.unreadCount > 99 ? '99+' : $wire.unreadCount" x-cloak>{{ $unreadCount > 99 ? '99+' : $unreadCount }}</flux:badge>
        </flux:button>
    </flux:modal.trigger>

    <flux:modal
        name="staff-notifications"
        flyout
        :closable="false"
        class="w-full min-w-0 max-w-lg px-4 py-6 sm:px-6"
        x-on:close="closePanel()"
    >
        <div class="space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <flux:heading
                        level="2"
                        size="lg"
                        id="staff-notifications-title"
                        class="break-words"
                        x-bind="dialogLabel"
                    >{{ __('ui.notifications.unread_count.notifications') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('notifications.panel.scope') }}</flux:text>
                </div>
                <flux:modal.close>
                    <flux:button variant="ghost" icon="x-mark" class="min-h-touch min-w-touch shrink-0" :aria-label="__('notifications.panel.close')" autofocus />
                </flux:modal.close>
            </div>

            <div wire:offline role="status">
                <flux:callout variant="warning" icon="wifi" class="callout-contrast" :heading="__('notifications.panel.offline_title')" :text="__('notifications.panel.offline_description')" />
            </div>

            @if ($destinationUnavailable)
                <flux:callout variant="warning" icon="information-circle" class="callout-contrast" role="status" :text="__('notifications.panel.destination_unavailable')" />
            @endif

            <div wire:offline.remove class="w-full">
                <div x-show="!panelReady && !panelFailed" role="status" aria-busy="true" aria-label="{{ __('notifications.panel.loading') }}" data-notification-loading>
                    <flux:skeleton.group animate="shimmer" class="space-y-3">
                        <flux:skeleton.line />
                        <flux:skeleton.line />
                        <flux:skeleton.line />
                    </flux:skeleton.group>
                </div>
                <div x-show="panelFailed" x-cloak role="status" class="space-y-3" data-notification-failure>
                    <flux:callout variant="warning" icon="arrow-path" class="callout-contrast" :text="__('notifications.panel.load_failed')" />
                    <flux:button x-on:click="loadPanel(historyDirection)" variant="outline" class="min-h-touch" wire:offline.attr="disabled">{{ __('notifications.panel.retry') }}</flux:button>
                </div>
            </div>

            <div x-show="panelReady" x-cloak class="space-y-4">
                @if ($panelOpen)
                    <fieldset class="flex min-w-0 flex-wrap items-center justify-between gap-3" wire:offline.attr="disabled">
                        <flux:text role="status" aria-live="polite" aria-atomic="true">{{ __('notifications.panel.unread', ['count' => $unreadCount]) }}</flux:text>
                        <flux:button wire:click="markAllRead" variant="filled" icon="check" class="h-auto min-h-touch min-w-0 max-w-full py-2 text-start" :disabled="$unreadCount === 0">
                            <span class="whitespace-normal wrap-anywhere">{{ __('ui.notifications.unread_count.mark_all_as_read') }}</span>
                        </flux:button>
                    </fieldset>
                    <flux:text size="sm">{{ __('notifications.panel.recent_limit', ['count' => $historyLimit]) }}</flux:text>

                    <nav aria-label="{{ __('notifications.panel.history_navigation') }}" class="space-y-2" data-notification-history>
                        <flux:text size="sm" role="status" aria-live="polite" aria-atomic="true" tabindex="-1" data-history-heading>
                            {{ ($history['current'] ?? null) === null ? __('notifications.panel.latest_page') : __('notifications.panel.history_page') }}
                        </flux:text>
                        <fieldset class="flex flex-wrap gap-2" wire:offline.attr="disabled">
                            <flux:button x-on:click="$el.focus(); loadPanel('newer')" data-history-action="newer" variant="outline" icon="chevron-left" class="min-h-touch" :disabled="($history['newer'] ?? null) === null">
                                {{ __('notifications.panel.newer') }}
                            </flux:button>
                            <flux:button x-on:click="$el.focus(); loadPanel('older')" data-history-action="older" variant="outline" icon:trailing="chevron-right" class="min-h-touch" :disabled="($history['older'] ?? null) === null">
                                {{ __('notifications.panel.older') }}
                            </flux:button>
                            <flux:button x-on:click="$el.focus(); loadPanel('latest')" data-history-action="latest" variant="ghost" icon="arrow-path" class="min-h-touch" :disabled="($history['current'] ?? null) === null">
                                {{ __('notifications.panel.latest') }}
                            </flux:button>
                        </fieldset>
                    </nav>

                    <div class="space-y-3" data-notification-list>
                        @forelse ($notifications as $notification)
                            <flux:card wire:key="staff-notification-{{ $notification['id'] }}" data-notification-item="{{ $notification['id'] }}" class="min-w-0 space-y-3 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <flux:heading class="min-w-0 break-words">{{ $notification['title'] }}</flux:heading>
                                    <flux:badge size="sm" :color="$notification['unread'] ? 'blue' : 'zinc'">{{ $notification['unread'] ? __('notifications.panel.unread_status') : __('notifications.panel.read_status') }}</flux:badge>
                                </div>
                                <x-ui.plain-text :text="$notification['body']" class="block text-sm text-text-primary" />
                                @if ($notification['meta'])
                                    <x-ui.plain-text :text="$notification['meta']" class="block text-sm text-text-muted" :preserve-lines="false" />
                                @endif
                                <time datetime="{{ $notification['created_at'] }}" class="block text-sm text-text-muted">{{ $notification['created_label'] }}</time>
                                <div class="flex flex-wrap gap-2">
                                    @if ($notification['can_open'])
                                        <flux:button wire:click="openNotification('{{ $notification['id'] }}')" variant="outline" icon="arrow-top-right-on-square" class="h-auto min-h-touch min-w-0 max-w-full py-2 text-start" wire:offline.attr="disabled">
                                            <span class="whitespace-normal wrap-anywhere">{{ __('notifications.panel.open_table') }}</span>
                                        </flux:button>
                                    @endif
                                    @if ($notification['unread'])
                                        <flux:button wire:click="markNotificationRead('{{ $notification['id'] }}')" variant="ghost" icon="check" class="h-auto min-h-touch min-w-0 max-w-full py-2 text-start" wire:offline.attr="disabled">
                                            <span class="whitespace-normal wrap-anywhere">{{ __('ui.notifications.unread_count.mark_notifications_as_read') }}</span>
                                        </flux:button>
                                    @endif
                                </div>
                            </flux:card>
                        @empty
                            <x-ui.state-panel kind="empty" :title="__('ui.empty.no_notifications')" :description="__('notifications.panel.empty_description')" />
                        @endforelse
                    </div>
                @endif
            </div>
        </div>
    </flux:modal>
</div>
