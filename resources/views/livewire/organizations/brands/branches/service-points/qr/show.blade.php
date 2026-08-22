<section data-page="branch-service-point-qr" class="flex h-full w-full flex-1 flex-col gap-6">
    <header class="flex flex-col gap-3">
        <flux:button icon="arrow-left" :href="$servicePointsUrl" wire:navigate>
            {{ __('qr.navigation.service_points') }}
        </flux:button>

        <div class="flex flex-col gap-1">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $contextLabel }}</p>
            <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">{{ __('qr.labels.title') }} {{ $qrShortCode }}</h1>
        </div>
    </header>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('qr.labels.branch') }}</p>
                    <p class="mt-1 text-base font-semibold text-zinc-950 dark:text-white">{{ $branchName }}</p>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $branchLocation }}</p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('qr.labels.current_area') }}</p>
                    <p class="mt-1 text-base font-semibold text-zinc-950 dark:text-white">{{ $areaName }}</p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('qr.labels.current_service_point') }}</p>
                    <p class="mt-1 text-base font-semibold text-zinc-950 dark:text-white">{{ $servicePointName }}</p>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                        {{ $servicePointTypeLabel }} / {{ __('qr.labels.number') }}: {{ $servicePointDisplayNumber }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('qr.labels.status') }}</p>
                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        <flux:badge :color="$qrStatusColor">{{ $qrLocalizedStatus }}</flux:badge>
                        <span class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('qr.labels.created') }}: {{ $qrCreatedAt }}</span>
                    </div>
                </div>
            </div>

            <div class="mt-5 grid gap-2">
                <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('qr.labels.public_url') }}</p>
                <p class="break-all rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-700 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-200">
                    {{ $publicUrl }}
                </p>
            </div>

            <div class="mt-5 flex flex-wrap gap-2">
                <flux:button icon="arrow-top-right-on-square" :href="$publicUrl" target="_blank" rel="noopener">
                    {{ __('qr.actions.open_guest_url') }}
                </flux:button>

                <flux:button icon="arrow-down-tray" type="button" wire:click="downloadQrImage" wire:loading.attr="disabled" wire:target="downloadQrImage">
                    {{ __('qr.actions.download') }}
                </flux:button>

                <flux:button icon="printer" :href="$printUrl" wire:navigate>
                    {{ __('qr.actions.print') }}
                </flux:button>

                @if ($qrIsActive)
                    <x-dangerous-action-confirmation
                        name="qr-disable-{{ $qrCodeId }}"
                        action="disable_qr"
                        confirm-action="disableQr"
                        confirm-label="ui.actions.confirm"
                        reason-model="qrDisableReason"
                        reason-label="qr.labels.disable_reason"
                        reason-placeholder="qr.placeholders.disable_reason"
                    >
                        <x-slot:trigger>
                            <flux:button icon="no-symbol" type="button">
                                {{ __('qr.actions.disable') }}
                            </flux:button>
                        </x-slot:trigger>
                    </x-dangerous-action-confirmation>
                @endif

                <x-dangerous-action-confirmation
                    name="qr-reissue-{{ $qrCodeId }}"
                    action="reissue_qr"
                    confirm-action="reissueQr"
                    confirm-label="ui.actions.confirm"
                    confirmation-model="qrReissueConfirmation"
                    :confirmation-text="$qrShortCode"
                    confirmation-label="qr.labels.current_short_code"
                    confirmation-help="qr.confirmations.reissue.confirmation_help"
                >
                    <x-slot:trigger>
                        <flux:button icon="exclamation-triangle" variant="danger" type="button" wire:click="confirmReissue">
                            {{ __('qr.actions.reissue') }}
                        </flux:button>
                    </x-slot:trigger>
                </x-dangerous-action-confirmation>
            </div>
        </div>

        <aside class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex flex-col items-center gap-4">
                <img
                    src="{{ $qrImageDataUri }}"
                    alt="{{ __('qr.labels.image') }}"
                    width="720"
                    height="720"
                    class="aspect-square w-full max-w-72 rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-800"
                >

                <div class="text-center">
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('qr.labels.short_code') }}</p>
                    <p class="mt-1 text-xl font-semibold tracking-normal text-zinc-950 dark:text-white">{{ $qrShortCode }}</p>
                </div>
            </div>
        </aside>
    </div>
</section>
