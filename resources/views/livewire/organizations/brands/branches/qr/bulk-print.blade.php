<main data-page="branch-bulk-qr-print" class="qr-print-page qr-print-page-bulk">
    <div class="qr-print-toolbar">
        <div class="flex flex-col gap-1">
            <p class="text-sm font-medium text-zinc-500">{{ $contextLabel }}</p>
            <h1 class="text-2xl font-semibold text-zinc-950">{{ __('qr.print.bulk_title') }}</h1>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <flux:button icon="arrow-left" :href="$branchesUrl" wire:navigate>
                {{ __('qr.navigation.branches') }}
            </flux:button>

            <flux:button icon="printer" variant="primary" type="button" x-on:click="window.print()" :disabled="count($printItems) === 0">
                {{ __('qr.actions.print') }}
            </flux:button>
        </div>
    </div>

    <section class="qr-print-controls">
        <div class="grid gap-4 md:grid-cols-[1fr_16rem_auto] md:items-end">
            <flux:select wire:model.live="areaNodeId" :label="__('qr.labels.zone')">
                @foreach ($areaOptions as $option)
                    <flux:select.option wire:key="bulk-qr-area-{{ $option['value'] }}" value="{{ $option['value'] }}">
                        {{ $option['label'] }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="preset" :label="__('qr.print.label_design')">
                @foreach ($presetOptions as $option)
                    <flux:select.option wire:key="bulk-qr-preset-{{ $option['value'] }}" value="{{ $option['value'] }}">
                        {{ __($option['label']) }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:switch wire:model.live="printTableNumber" :label="__('qr.print.print_table_number')" />
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            <flux:button icon="check" type="button" wire:click="selectAllVisible">
                {{ __('qr.actions.select_all_with_qr') }}
            </flux:button>

            <flux:button icon="x-mark" type="button" wire:click="clearSelection">
                {{ __('qr.actions.clear_selection') }}
            </flux:button>

            @if ($visibleMissingQrCount > 0)
                <flux:button icon="qr-code" variant="primary" type="button" wire:click="createMissingQrForVisible" wire:loading.attr="disabled" wire:target="createMissingQrForVisible">
                    {{ __('qr.actions.create_missing_for_visible') }}
                </flux:button>
            @endif
        </div>

        <p class="mt-3 text-sm text-zinc-500">
            {{ __('qr.labels.selected_for_print') }}: {{ count($printItems) }} / {{ __('qr.labels.missing_shown') }}: {{ $visibleMissingQrCount }}
        </p>
    </section>

    @if ($printTableNumber)
        <div class="qr-print-warning">
            {{ __('qr.print.table_number_warning') }}
        </div>
    @endif

    <section class="qr-print-list" aria-label="{{ __('qr.labels.list') }}">
        @forelse ($servicePointRows as $servicePoint)
            <article wire:key="bulk-qr-service-point-{{ $servicePoint['id'] }}" class="qr-print-list-row">
                <label class="flex min-w-0 flex-1 items-start gap-3">
                    <input
                        type="checkbox"
                        class="mt-1 size-4 rounded border-zinc-300 text-zinc-950"
                        wire:model.live="selectedServicePointIds"
                        value="{{ $servicePoint['id'] }}"
                        @disabled(! $servicePoint['has_qr'])
                    >

                    <span class="min-w-0">
                        <span class="block truncate font-medium text-zinc-950">{{ $servicePoint['name'] }}</span>
                        <span class="block text-sm text-zinc-500">
                            {{ __('qr.labels.zone') }}: {{ $servicePoint['area_name'] }}
                            / {{ __('qr.labels.number') }}: {{ $servicePoint['display_number'] }}
                        </span>
                    </span>
                </label>

                <div class="flex flex-wrap items-center justify-end gap-2">
                    @if ($servicePoint['has_qr'])
                        <flux:badge color="green">{{ $servicePoint['qr_short_code'] }}</flux:badge>
                    @else
                        <flux:badge color="zinc">{{ __('qr.labels.no_qr') }}</flux:badge>

                        <flux:button icon="qr-code" size="sm" type="button" wire:click="createQrForServicePoint({{ $servicePoint['id'] }})" wire:loading.attr="disabled" wire:target="createQrForServicePoint({{ $servicePoint['id'] }})">
                            {{ __('qr.actions.generate') }}
                        </flux:button>
                    @endif
                </div>
            </article>
        @empty
            <div class="qr-print-list-empty">
                {{ __('qr.empty.no_service_points_in_zone') }}
            </div>
        @endforelse
    </section>

    @if (count($printItems) === 0)
        <section class="qr-print-empty-preview">
            {{ __('qr.print.empty_preview') }}
        </section>
    @else
        <section class="qr-bulk-sticker-grid" aria-label="{{ __('qr.labels.stickers_preview') }}">
            @foreach ($printItems as $item)
                <article
                    wire:key="bulk-print-sticker-{{ $item['service_point_id'] }}"
                    @class(['qr-sticker', $selectedPresetCssClass])
                    data-preset="{{ $selectedPresetValue }}"
                >
                    <div class="qr-sticker-brand">
                        @if ($restaurantLogoUrl)
                            <img src="{{ $restaurantLogoUrl }}" alt="{{ $item['brand_name'] }}" class="qr-sticker-logo">
                        @else
                            <div class="qr-sticker-logotype">{{ $item['brand_name'] }}</div>
                        @endif
                    </div>

                    <p class="qr-sticker-title">{{ __('qr.print.sticker_title') }}</p>

                    <img src="{{ $item['qr_image_data_uri'] }}" alt="{{ __('qr.labels.image') }}" class="qr-sticker-image">

                    <div class="qr-sticker-code">{{ $item['short_code'] }}</div>

                    @if ($printTableNumber)
                        <div class="qr-sticker-table-number">
                            {{ __('qr.labels.table') }}: {{ $item['service_point_label'] }}
                        </div>
                    @endif
                </article>
            @endforeach
        </section>
    @endif
</main>
