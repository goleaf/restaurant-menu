<div class="contents">
    <flux:card data-component="guest-request-waiter"  class="rounded-card border-warning-border bg-warning-surface p-4">
        <div class="flex flex-col gap-3">
            <div class="flex items-start gap-3">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-white/80 text-amber-800 shadow-sm ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-100 dark:ring-amber-900/70">
                    <flux:icon name="bell" variant="mini" class="size-5" />
                </div>

                <div class="min-w-0">
                    <p class="text-xs font-medium uppercase text-amber-700 dark:text-amber-300">{{ __('guest.table.help') }}</p>
                    <h2 class="mt-1 text-lg font-semibold leading-tight text-zinc-950 dark:text-white">{{ __('guest.table.request_waiter') }}</h2>
                </div>
            </div>

            @if ($waiterCallMessage)
                <flux:callout variant="warning" icon="exclamation-triangle" role="status" class="callout-contrast content-safe">
                    <flux:callout.text>
                        {{ $waiterCallMessage }}
                    </flux:callout.text>
                </flux:callout>
            @endif

            <flux:button
                type="button"
                wire:click="requestWaiter"
                wire:loading.attr="disabled"
                wire:target="requestWaiter"
                variant="primary" color="amber"
                icon="bell"
                class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2 w-full"
            >
                <span wire:loading.remove wire:target="requestWaiter">{{ __('guest.table.request_waiter') }}</span>
                <span wire:loading wire:target="requestWaiter">{{ __('guest.table.sending_waiter_call') }}</span>
            </flux:button>
        </div>
    </flux:card>

    <livewire:public-qr.table-guests
        :table-session-id="$tableSessionId"
        :current-guest-id="$currentGuestId"
        :public-token="$publicToken"
        :polling-interval-seconds="$pollingIntervalSeconds"
        :language="$language"
        wire:key="guest-table-guests-{{ $tableSessionId }}-{{ $currentGuestId }}"
    />

    <livewire:public-qr.notifications
        :table-session-id="$tableSessionId"
        :current-guest-id="$currentGuestId"
        :public-token="$publicToken"
        :polling-interval-seconds="$pollingIntervalSeconds"
        wire:key="guest-notifications-{{ $tableSessionId }}-{{ $currentGuestId }}"
    />

    <flux:card data-component="guest-invite-share" class="rounded-card border-border-subtle bg-surface p-4">
        <div class="space-y-1">
            <p class="text-xs font-medium uppercase text-emerald-700 dark:text-emerald-300">{{ __('guest.table.guests') }}</p>
            <h2 class="text-lg font-semibold leading-tight text-zinc-950 dark:text-white">{{ __('guest.table.invite_guest') }}</h2>
        </div>

        @if ($guestInviteMessage)
            <flux:callout color="blue" class="callout-contrast content-safe mt-3" icon="information-circle" role="status">
                <flux:callout.text>
                    {{ $guestInviteMessage }}
                </flux:callout.text>
            </flux:callout>
        @endif

        @if ($guestInviteUrl === '')
            <flux:button
                type="button"
                wire:click="createGuestInviteLink"
                wire:loading.attr="disabled"
                wire:target="createGuestInviteLink"
                variant="primary" color="zinc"
                class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2 w-full mt-4"
            >
                <span wire:loading.remove wire:target="createGuestInviteLink">{{ __('guest.table.invite_guest') }}</span>
                <span wire:loading wire:target="createGuestInviteLink">{{ __('guest.table.preparing_link') }}</span>
            </flux:button>
        @else
            <div
                class="mt-4 space-y-2"
                x-data="guestInvite" data-invite-title="{{ $guestInviteTitle }}" data-invite-text="{{ $guestInviteText }}"
            >
                <input x-ref="inviteLink" type="text" readonly value="{{ $guestInviteUrl }}" class="w-full min-h-touch rounded-control border-border-subtle bg-surface text-sm" x-show="copyFailed" x-cloak :aria-hidden="!copyFailed" :tabindex="copyFailed ? 0 : -1" aria-label="{{ __('guest.table.copy_link') }}">

                <flux:button x-show="supportsNativeShare" type="button" x-on:click="shareInvite" x-bind:disabled="busy" variant="primary" color="zinc" class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2 w-full">
                    {{ __('guest.table.share_link') }}
                </flux:button>

                <flux:button x-show="! supportsNativeShare" type="button" x-on:click="copyInvite" x-bind:disabled="busy" variant="primary" color="zinc" class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2 w-full">
                    {{ __('guest.table.copy_link') }}
                </flux:button>

                <flux:button x-show="supportsNativeShare" type="button" x-on:click="copyInvite" x-bind:disabled="busy" variant="outline" size="sm" class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2 w-full">
                    {{ __('guest.table.copy_link') }}
                </flux:button>

                <p x-show="copyFailed" x-cloak role="status" class="text-sm text-warning">{{ __('browser.clipboard_failed') }}</p>
                <p x-show="shareFailed" x-cloak role="status" class="text-sm text-warning">{{ __('browser.share_failed') }}</p>

                <flux:callout x-cloak x-show="copied" variant="success" icon="check-circle" role="status" class="callout-contrast content-safe">
                    <flux:callout.text>
                        {{ __('guest.table.link_copied') }}
                    </flux:callout.text>
                </flux:callout>
            </div>
        @endif
    </flux:card>

    <flux:card data-component="guest-leave-table"  class="rounded-card border-danger-border bg-danger-surface p-4">
        <div class="space-y-3">
            <div>
                <p class="text-xs font-medium uppercase text-red-700 dark:text-red-300">{{ __('guest.table.session_controls') }}</p>
                <h2 class="mt-1 text-lg font-semibold leading-tight text-zinc-950 dark:text-white">{{ __('guest.table.leave_title') }}</h2>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ __('guest.table.leave_description') }}</p>
            </div>

            @if ($leaveTableMessage)
                <flux:callout variant="danger" icon="x-circle" role="status" class="callout-contrast content-safe">
                    <flux:callout.text>
                        {{ $leaveTableMessage }}
                    </flux:callout.text>
                </flux:callout>
            @endif

            <x-dangerous-action-confirmation
                name="leave-table"
                title="guest.table.leave_title"
                consequence="guest.table.leave_consequence"
                confirm-action="leaveTable"
                confirm-label="guest.table.leave_action"
                loading-label="guest.table.leaving"
            >
                <x-slot:trigger>
                    <flux:button type="button" variant="primary" color="red" icon="arrow-right-start-on-rectangle" class="bg-danger! hover:bg-danger/90! dark:text-text-inverse! h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2 w-full">
                        {{ __('guest.table.leave_action') }}
                    </flux:button>
                </x-slot:trigger>
            </x-dangerous-action-confirmation>
        </div>
    </flux:card>
</div>
