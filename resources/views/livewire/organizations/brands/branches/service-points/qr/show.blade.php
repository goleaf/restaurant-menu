<section data-page="branch-service-point-qr" class="flex h-full w-full flex-1 flex-col gap-6">
    <header class="flex flex-col gap-3">
        <flux:button icon="arrow-left" :href="route('organizations.brands.branches.service-points.index', [$organization, $brand, $branch])" wire:navigate>
            {{ __('Service points') }}
        </flux:button>

        <div class="flex flex-col gap-1">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $organization->name }} / {{ $brand->name }} / {{ $branch->name }}</p>
            <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">{{ __('QR code') }} {{ $qrCode->short_code }}</h1>
        </div>
    </header>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('Branch') }}</p>
                    <p class="mt-1 text-base font-semibold text-zinc-950 dark:text-white">{{ $branch->name }}</p>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $branch->city }}, {{ $branch->country }}</p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('Current area') }}</p>
                    <p class="mt-1 text-base font-semibold text-zinc-950 dark:text-white">{{ $servicePoint->areaNode?->name ?? __('No zone') }}</p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('Current service point') }}</p>
                    <p class="mt-1 text-base font-semibold text-zinc-950 dark:text-white">{{ $servicePoint->name }}</p>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                        {{ __($servicePoint->type->label()) }} / {{ __('Number') }}: {{ $servicePoint->display_number ?: __('Not set') }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('QR status') }}</p>
                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        <flux:badge :color="$this->statusColor">{{ __($qrCode->status->label()) }}</flux:badge>
                        <span class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Created') }}: {{ $qrCode->created_at?->format('Y-m-d H:i') }}</span>
                    </div>
                </div>
            </div>

            <div class="mt-5 grid gap-2">
                <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('Public URL') }}</p>
                <p class="break-all rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-700 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-200">
                    {{ $this->publicUrl }}
                </p>
            </div>

            <div class="mt-5 flex flex-wrap gap-2">
                <flux:button icon="arrow-top-right-on-square" :href="$this->publicUrl" target="_blank" rel="noopener">
                    {{ __('Open guest URL') }}
                </flux:button>

                <flux:button icon="arrow-down-tray" type="button" wire:click="downloadQrImage" wire:loading.attr="disabled" wire:target="downloadQrImage">
                    {{ __('Download QR image') }}
                </flux:button>

                <flux:button icon="printer" :href="route('organizations.brands.branches.service-points.qr.print', [$organization, $brand, $branch, $servicePoint, $qrCode])" wire:navigate>
                    {{ __('Print sticker') }}
                </flux:button>

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
        </div>

        <aside class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex flex-col items-center gap-4">
                <img
                    src="{{ $this->qrImageDataUri }}"
                    alt="{{ __('QR image') }}"
                    class="aspect-square w-full max-w-72 rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-800"
                >

                <div class="text-center">
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Short code') }}</p>
                    <p class="mt-1 text-xl font-semibold tracking-normal text-zinc-950 dark:text-white">{{ $qrCode->short_code }}</p>
                </div>
            </div>
        </aside>
    </div>
</section>
