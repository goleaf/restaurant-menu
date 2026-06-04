@php($qrCode = $this->qrCode)

<section data-page="qr-short-code-lookup" class="flex h-full w-full flex-1 flex-col gap-5">
    <header class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div>
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Restaurant workspace') }}</p>
            <h1 class="mt-1 text-2xl font-semibold text-zinc-950 dark:text-white">{{ __('QR lookup') }}</h1>
            <p class="mt-1 max-w-2xl text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('Find a printed QR sticker by its short code.') }}
            </p>
        </div>

        <flux:button icon="layout-grid" :href="route('restaurant.dashboard')" wire:navigate>
            {{ __('Restaurant dashboard') }}
        </flux:button>
    </header>

    <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
        <form wire:submit="search" class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
            <flux:input
                wire:model="shortCode"
                :label="__('Short code')"
                type="text"
                maxlength="24"
                placeholder="QR-8F92"
                autocomplete="off"
                required
            />

            <flux:button icon="magnifying-glass" type="submit" variant="primary" wire:loading.attr="disabled" wire:target="search">
                {{ __('Find QR') }}
            </flux:button>
        </form>
    </section>

    @if (! $searched)
        <section class="rounded-lg border border-dashed border-zinc-300 bg-white p-6 text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400">
            {{ __('Enter the short code printed on the QR sticker.') }}
        </section>
    @elseif (! $qrCode)
        <section class="rounded-lg border border-zinc-200 bg-white p-6 text-sm text-zinc-500 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400">
            {{ __('No QR code was found for accessible branches.') }}
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
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Short code') }}</p>
                    <h2 class="mt-1 text-xl font-semibold tracking-normal text-zinc-950 dark:text-white">{{ $qrCode->short_code }}</h2>
                </div>

                <flux:badge :color="$this->statusColor($qrCode)">{{ __($qrCode->status->label()) }}</flux:badge>
            </div>

            <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('Branch') }}</p>
                    <p class="mt-1 text-base font-semibold text-zinc-950 dark:text-white">{{ $branch?->name ?? __('Branch unavailable') }}</p>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                        {{ collect([$branch?->city, $branch?->country])->filter()->implode(', ') ?: __('Location not set') }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('Organization') }}</p>
                    <p class="mt-1 text-base font-semibold text-zinc-950 dark:text-white">{{ $organization?->name ?? __('Organization unavailable') }}</p>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $brand?->name ?? __('Brand unavailable') }}</p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('Zone') }}</p>
                    <p class="mt-1 text-base font-semibold text-zinc-950 dark:text-white">{{ $servicePoint?->areaNode?->name ?? __('No zone') }}</p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('Service point') }}</p>
                    <p class="mt-1 text-base font-semibold text-zinc-950 dark:text-white">{{ $servicePoint?->name ?? __('Service point unavailable') }}</p>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                        {{ $servicePoint?->display_number ?: __('Number not set') }}
                    </p>
                </div>
            </div>

            <div class="mt-5 grid gap-2">
                <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('Public URL') }}</p>
                <p class="break-all rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-700 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-200">
                    {{ $publicUrl }}
                </p>
            </div>

            <div class="mt-5 flex flex-wrap gap-2">
                @if ($adminUrl)
                    <flux:button icon="qr-code" :href="$adminUrl" wire:navigate>
                        {{ __('Open QR') }}
                    </flux:button>
                @endif

                @if ($publicUrl)
                    <flux:button icon="arrow-top-right-on-square" :href="$publicUrl" target="_blank" rel="noopener">
                        {{ __('Open guest URL') }}
                    </flux:button>
                @endif

                @if ($qrCode->status === \App\Enums\QrCodeStatus::Active)
                    <flux:button icon="no-symbol" type="button" wire:click="disableQr" wire:loading.attr="disabled" wire:target="disableQr">
                        {{ __('Disable QR') }}
                    </flux:button>
                @endif

                <flux:button icon="exclamation-triangle" variant="danger" type="button" wire:click="confirmReissue">
                    {{ __('Reissue QR') }}
                </flux:button>
            </div>

            @if ($confirmingReissue)
                <div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950/40">
                    <div class="grid gap-2">
                        <p class="font-semibold text-red-900 dark:text-red-100">{{ __('Reissuing this QR is dangerous.') }}</p>
                        <p class="text-sm leading-6 text-red-800 dark:text-red-200">
                            {{ __('The current public URL will stop working and guests will need the newly printed QR code.') }}
                        </p>
                    </div>

                    <div class="mt-4 flex flex-wrap justify-end gap-2">
                        <flux:button icon="x-mark" type="button" wire:click="cancelReissue">
                            {{ __('Cancel') }}
                        </flux:button>

                        <flux:button icon="exclamation-triangle" variant="danger" type="button" wire:click="reissueQr" wire:loading.attr="disabled" wire:target="reissueQr">
                            {{ __('Confirm reissue') }}
                        </flux:button>
                    </div>
                </div>
            @endif
        </section>
    @endif
</section>
