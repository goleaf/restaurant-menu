<main data-page="qr-print-template" class="qr-print-page qr-print-page-single">
    <div class="qr-print-toolbar">
        <div class="flex flex-col gap-1">
            <p class="text-sm font-medium text-zinc-500">{{ $organization->name }} / {{ $brand->name }} / {{ $branch->name }}</p>
            <h1 class="text-2xl font-semibold text-zinc-950">{{ __('Print QR sticker') }}</h1>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <flux:button icon="arrow-left" :href="route('organizations.brands.branches.service-points.qr.show', [$organization, $brand, $branch, $servicePoint, $qrCode])" wire:navigate>
                {{ __('QR page') }}
            </flux:button>

            <flux:switch wire:model.live="printTableNumber" :label="__('Print table number')" />

            <flux:button icon="printer" variant="primary" type="button" x-on:click="window.print()">
                {{ __('Print') }}
            </flux:button>
        </div>
    </div>

    @if ($printTableNumber)
        <div class="qr-print-warning">
            {{ __('Если вы потом переименуете или перенесёте стол, текст на наклейке может устареть.') }}
        </div>
    @endif

    <section class="qr-sticker" aria-label="{{ __('QR sticker preview') }}">
        <div class="qr-sticker-brand">
            @if ($this->restaurantLogoUrl)
                <img src="{{ $this->restaurantLogoUrl }}" alt="{{ $brand->name }}" class="qr-sticker-logo">
            @else
                <div class="qr-sticker-logotype">{{ $brand->name }}</div>
            @endif
        </div>

        <p class="qr-sticker-title">{{ __('Сканируйте, чтобы открыть меню') }}</p>

        <img src="{{ $this->qrImageDataUri }}" alt="{{ __('QR image') }}" class="qr-sticker-image">

        <div class="qr-sticker-code">{{ $qrCode->short_code }}</div>

        @if ($printTableNumber)
            <div class="qr-sticker-table-number">
                {{ __('Стол') }}: {{ $this->tableLabel }}
            </div>
        @endif
    </section>
</main>
