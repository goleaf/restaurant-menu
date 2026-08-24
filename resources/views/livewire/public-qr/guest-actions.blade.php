<div class="contents">
    <x-ui.card data-component="guest-request-waiter" tone="warning">
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
                <x-ui.alert tone="warning">
                    {{ $waiterCallMessage }}
                </x-ui.alert>
            @endif

            <x-ui.button
                type="button"
                wire:click="requestWaiter"
                wire:loading.attr="disabled"
                wire:target="requestWaiter"
                variant="warning"
                full-width
                icon="bell"
            >
                <span wire:loading.remove wire:target="requestWaiter">{{ __('guest.table.request_waiter') }}</span>
                <span wire:loading wire:target="requestWaiter">{{ __('guest.table.sending_waiter_call') }}</span>
            </x-ui.button>
        </div>
    </x-ui.card>

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

    <x-ui.card data-component="guest-invite-share">
        <div class="space-y-1">
            <p class="text-xs font-medium uppercase text-emerald-700 dark:text-emerald-300">{{ __('guest.table.guests') }}</p>
            <h2 class="text-lg font-semibold leading-tight text-zinc-950 dark:text-white">{{ __('guest.table.invite_guest') }}</h2>
        </div>

        @if ($guestInviteMessage)
            <x-ui.alert tone="info" class="mt-3">
                {{ $guestInviteMessage }}
            </x-ui.alert>
        @endif

        @if ($guestInviteUrl === '')
            <x-ui.button
                type="button"
                wire:click="createGuestInviteLink"
                wire:loading.attr="disabled"
                wire:target="createGuestInviteLink"
                variant="dark"
                full-width
                class="mt-4"
            >
                <span wire:loading.remove wire:target="createGuestInviteLink">{{ __('guest.table.invite_guest') }}</span>
                <span wire:loading wire:target="createGuestInviteLink">{{ __('guest.table.preparing_link') }}</span>
            </x-ui.button>
        @else
            <div
                class="mt-4 space-y-2"
                x-data="{
                    copied: false,
                    supportsNativeShare: typeof navigator !== 'undefined' && typeof navigator.share === 'function',
                    async shareInvite() {
                        try {
                            await navigator.share({
                                title: @js($guestInviteTitle),
                                text: @js($guestInviteText),
                                url: @js($guestInviteUrl),
                            });
                        } catch (error) {}
                    },
                    async copyInvite() {
                        const link = @js($guestInviteUrl);

                        if (navigator.clipboard && window.isSecureContext) {
                            await navigator.clipboard.writeText(link);
                        } else {
                            this.$refs.inviteLink.focus();
                            this.$refs.inviteLink.select();
                            document.execCommand('copy');
                        }

                        this.copied = true;
                    },
                }"
            >
                <input x-ref="inviteLink" type="text" readonly value="{{ $guestInviteUrl }}" class="sr-only" tabindex="-1" aria-hidden="true">

                <x-ui.button x-show="supportsNativeShare" type="button" x-on:click="shareInvite" variant="dark" full-width>
                    {{ __('guest.table.share_link') }}
                </x-ui.button>

                <x-ui.button x-show="! supportsNativeShare" type="button" x-on:click="copyInvite" variant="dark" full-width>
                    {{ __('guest.table.copy_link') }}
                </x-ui.button>

                <x-ui.button x-show="supportsNativeShare" type="button" x-on:click="copyInvite" variant="secondary" size="sm" full-width>
                    {{ __('guest.table.copy_link') }}
                </x-ui.button>

                <x-ui.alert x-cloak x-show="copied" tone="success">
                    {{ __('guest.table.link_copied') }}
                </x-ui.alert>
            </div>
        @endif
    </x-ui.card>

    <x-ui.card data-component="guest-leave-table" tone="danger">
        <div class="space-y-3">
            <div>
                <p class="text-xs font-medium uppercase text-red-700 dark:text-red-300">{{ __('guest.table.session_controls') }}</p>
                <h2 class="mt-1 text-lg font-semibold leading-tight text-zinc-950 dark:text-white">{{ __('guest.table.leave_title') }}</h2>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ __('guest.table.leave_description') }}</p>
            </div>

            @if ($leaveTableMessage)
                <x-ui.alert tone="danger">
                    {{ $leaveTableMessage }}
                </x-ui.alert>
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
                    <x-ui.button type="button" variant="danger" full-width icon="arrow-right-start-on-rectangle">
                        {{ __('guest.table.leave_action') }}
                    </x-ui.button>
                </x-slot:trigger>
            </x-dangerous-action-confirmation>
        </div>
    </x-ui.card>
</div>
