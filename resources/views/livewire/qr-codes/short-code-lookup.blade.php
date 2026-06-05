@php
    $qrCode = $this->qrCode;
    $qrDisableTitle = \App\Enums\DangerousAction::DisableQr->title();
    $qrDisableConsequence = \App\Enums\DangerousAction::DisableQr->consequence();
    $qrDisableRequiresReason = \App\Enums\DangerousAction::DisableQr->requiresReason();
    $qrReissueTitle = \App\Enums\DangerousAction::ReissueQr->title();
    $qrReissueConsequence = \App\Enums\DangerousAction::ReissueQr->consequence();
    $qrReissueConfirmationHelp = 'qr.confirmations.reissue.confirmation_help';
@endphp

<section data-page="qr-short-code-lookup" class="flex h-full w-full flex-1 flex-col gap-5">
    <header class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div>
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('qr.lookup.workspace') }}</p>
            <h1 class="mt-1 text-2xl font-semibold text-zinc-950 dark:text-white">{{ __('qr.lookup.title') }}</h1>
            <p class="mt-1 max-w-2xl text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('qr.lookup.description') }}
            </p>
        </div>

        <flux:button icon="layout-grid" :href="route('restaurant.dashboard')" wire:navigate>
            {{ __('qr.navigation.restaurant_dashboard') }}
        </flux:button>
    </header>

    <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
        <form wire:submit="search" class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
            <flux:input
                wire:model="shortCode"
                :label="__('qr.labels.short_code')"
                type="text"
                maxlength="24"
                placeholder="QR-8F92"
                autocomplete="off"
                required
            />

            <flux:button icon="magnifying-glass" type="submit" variant="primary" wire:loading.attr="disabled" wire:target="search">
                {{ __('qr.actions.find') }}
            </flux:button>
        </form>
    </section>

    @if (! $searched)
        <section class="rounded-lg border border-dashed border-zinc-300 bg-white p-6 text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400">
            {{ __('qr.lookup.help') }}
        </section>
    @elseif (! $qrCode)
        <section class="rounded-lg border border-zinc-200 bg-white p-6 text-sm text-zinc-500 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400">
            {{ __('qr.empty.no_qr_codes') }}
        </section>
    @else
        @php($servicePoint = $qrCode->servicePoint)
        @php($branch = $servicePoint?->branch)
        @php($brand = $branch?->brand)
        @php($organization = $branch?->organization)
        @php($publicUrl = $this->publicUrl)
        @php($adminUrl = $this->adminUrl)

        <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('qr.labels.short_code') }}</p>
                    <h2 class="mt-1 text-xl font-semibold tracking-normal text-zinc-950 dark:text-white">{{ $qrCode->short_code }}</h2>
                </div>

                <flux:badge :color="$this->statusColor($qrCode)">{{ __($qrCode->status->label()) }}</flux:badge>
            </div>

            <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('qr.labels.branch') }}</p>
                    <p class="mt-1 text-base font-semibold text-zinc-950 dark:text-white">{{ $branch?->name ?? __('qr.labels.branch_unavailable') }}</p>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                        {{ collect([$branch?->city, $branch?->country])->filter()->implode(', ') ?: __('qr.labels.location_not_set') }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('qr.labels.organization') }}</p>
                    <p class="mt-1 text-base font-semibold text-zinc-950 dark:text-white">{{ $organization?->name ?? __('qr.labels.organization_unavailable') }}</p>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $brand?->name ?? __('qr.labels.brand_unavailable') }}</p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('qr.labels.zone') }}</p>
                    <p class="mt-1 text-base font-semibold text-zinc-950 dark:text-white">{{ $servicePoint?->areaNode?->name ?? __('qr.labels.no_zone') }}</p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('qr.labels.service_point') }}</p>
                    <p class="mt-1 text-base font-semibold text-zinc-950 dark:text-white">{{ $servicePoint?->name ?? __('qr.labels.service_point_unavailable') }}</p>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                        {{ $servicePoint?->display_number ?: __('qr.labels.number_not_set') }}
                    </p>
                </div>
            </div>

            <div class="mt-5 grid gap-2">
                <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('qr.labels.public_url') }}</p>
                <p class="break-all rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-700 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-200">
                    {{ $publicUrl }}
                </p>
            </div>

            <div class="mt-5 flex flex-wrap gap-2">
                @if ($adminUrl)
                    <flux:button icon="qr-code" :href="$adminUrl" wire:navigate>
                        {{ __('qr.actions.open') }}
                    </flux:button>
                @endif

                @if ($publicUrl)
                    <flux:button icon="arrow-top-right-on-square" :href="$publicUrl" target="_blank" rel="noopener">
                        {{ __('qr.actions.open_guest_url') }}
                    </flux:button>
                @endif

                @if ($qrCode->status === \App\Enums\QrCodeStatus::Active)
                    <x-dangerous-action-confirmation
                        name="qr-lookup-disable-{{ $qrCode->id }}"
                        :title="$qrDisableTitle"
                        :consequence="$qrDisableConsequence"
                        confirm-action="disableQr"
                        confirm-label="ui.actions.confirm"
                        reason-model="qrDisableReason"
                        reason-label="qr.labels.disable_reason"
                        reason-placeholder="qr.placeholders.disable_reason"
                        :reason-required="$qrDisableRequiresReason"
                    >
                        <x-slot:trigger>
                            <flux:button icon="no-symbol" type="button">
                                {{ __('qr.actions.disable') }}
                            </flux:button>
                        </x-slot:trigger>
                    </x-dangerous-action-confirmation>
                @endif

                <x-dangerous-action-confirmation
                    name="qr-lookup-reissue-{{ $qrCode->id }}"
                    :title="$qrReissueTitle"
                    :consequence="$qrReissueConsequence"
                    confirm-action="reissueQr"
                    confirm-label="ui.actions.confirm"
                    confirmation-model="qrReissueConfirmation"
                    :confirmation-text="$qrCode->short_code"
                    confirmation-label="qr.labels.current_short_code"
                    :confirmation-help="$qrReissueConfirmationHelp"
                >
                    <x-slot:trigger>
                        <flux:button icon="exclamation-triangle" variant="danger" type="button" wire:click="confirmReissue">
                            {{ __('qr.actions.reissue') }}
                        </flux:button>
                    </x-slot:trigger>
                </x-dangerous-action-confirmation>
            </div>
        </section>
    @endif
</section>
