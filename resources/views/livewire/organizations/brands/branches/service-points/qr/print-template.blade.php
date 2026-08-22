<main data-page="qr-print-template" class="qr-print-page qr-print-page-single">
    <div class="qr-print-toolbar">
        <div class="flex flex-col gap-1">
            <p class="text-sm font-medium text-zinc-500">{{ $contextLabel }}</p>
            <h1 class="text-2xl font-semibold text-zinc-950">{{ __('qr.print.single_title') }}</h1>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <flux:button icon="arrow-left" :href="$qrPageUrl" wire:navigate>
                {{ __('qr.navigation.qr_page') }}
            </flux:button>

            <flux:select wire:model.live="preset" :label="__('qr.print.label_design')">
                @foreach ($presetOptions as $option)
                    <flux:select.option wire:key="single-qr-preset-{{ $option['value'] }}" value="{{ $option['value'] }}">
                        {{ __($option['label']) }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:switch wire:model.live="printTableNumber" :label="__('qr.print.print_table_number')" />

            <flux:button icon="printer" variant="primary" type="button" x-on:click="window.print()">
                {{ __('qr.actions.print') }}
            </flux:button>
        </div>
    </div>

    @if ($printTableNumber)
        <div class="qr-print-warning">
            {{ __('qr.print.table_number_warning') }}
        </div>
    @endif

    <section
        @class(['qr-sticker', $selectedPresetCssClass])
        data-preset="{{ $selectedPresetValue }}"
        aria-label="{{ __('qr.labels.sticker_preview') }}"
    >
        <div class="qr-sticker-brand">
            @if ($restaurantLogoUrl)
                <img src="{{ $restaurantLogoUrl }}" alt="{{ $brandName }}" width="208" height="48" class="qr-sticker-logo">
            @else
                <div class="qr-sticker-logotype">{{ $brandName }}</div>
            @endif
        </div>

        <p class="qr-sticker-title">{{ __('qr.print.sticker_title') }}</p>

        <img src="{{ $qrImageDataUri }}" alt="{{ __('qr.labels.image') }}" width="720" height="720" class="qr-sticker-image">

        <div class="qr-sticker-code">{{ $qrShortCode }}</div>

        @if ($printTableNumber)
            <div class="qr-sticker-table-number">
                {{ __('qr.labels.table') }}: {{ $tableLabel }}
            </div>
        @endif
    </section>
</main>
