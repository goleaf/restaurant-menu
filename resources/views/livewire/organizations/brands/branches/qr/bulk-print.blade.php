<main data-page="branch-bulk-qr-print" class="qr-print-page qr-print-page-bulk">
    <div class="qr-print-toolbar">
        <div class="flex flex-col gap-1">
            <p class="text-sm font-medium text-zinc-500">{{ $organization->name }} / {{ $brand->name }} / {{ $branch->name }}</p>
            <h1 class="text-2xl font-semibold text-zinc-950">{{ __('Bulk QR print') }}</h1>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <flux:button icon="arrow-left" :href="route('organizations.brands.branches.index', [$organization, $brand])" wire:navigate>
                {{ __('Branches') }}
            </flux:button>

            <flux:button icon="printer" variant="primary" type="button" x-on:click="window.print()" :disabled="count($this->printItems) === 0">
                {{ __('Print') }}
            </flux:button>
        </div>
    </div>

    <section class="qr-print-controls">
        <div class="grid gap-4 md:grid-cols-[1fr_16rem_auto] md:items-end">
            <flux:select wire:model.live="areaNodeId" :label="__('Zone')">
                @foreach ($this->areaOptions as $option)
                    <flux:select.option wire:key="bulk-qr-area-{{ $option['value'] }}" value="{{ $option['value'] }}">
                        {{ $option['label'] }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="preset" :label="__('Label design')">
                @foreach ($this->presetOptions as $option)
                    <flux:select.option wire:key="bulk-qr-preset-{{ $option['value'] }}" value="{{ $option['value'] }}">
                        {{ __($option['label']) }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:switch wire:model.live="printTableNumber" :label="__('Print table number')" />
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            <flux:button icon="check" type="button" wire:click="selectAllVisible">
                {{ __('Select all with QR') }}
            </flux:button>

            <flux:button icon="x-mark" type="button" wire:click="clearSelection">
                {{ __('Clear') }}
            </flux:button>

            @if ($this->visibleMissingQrCount > 0)
                <flux:button icon="qr-code" variant="primary" type="button" wire:click="createMissingQrForVisible" wire:loading.attr="disabled" wire:target="createMissingQrForVisible">
                    {{ __('Create missing QR for shown') }}
                </flux:button>
            @endif
        </div>

        <p class="mt-3 text-sm text-zinc-500">
            {{ __('Selected for print') }}: {{ count($this->printItems) }} / {{ __('Missing QR shown') }}: {{ $this->visibleMissingQrCount }}
        </p>
    </section>

    @if ($printTableNumber)
        <div class="qr-print-warning">
            {{ __('Если вы потом переименуете или перенесёте стол, текст на наклейке может устареть.') }}
        </div>
    @endif

    <section class="qr-print-list" aria-label="{{ __('QR list') }}">
        @forelse ($this->servicePoints as $servicePoint)
            <article wire:key="bulk-qr-service-point-{{ $servicePoint->id }}" class="qr-print-list-row">
                <label class="flex min-w-0 flex-1 items-start gap-3">
                    <input
                        type="checkbox"
                        class="mt-1 size-4 rounded border-zinc-300 text-zinc-950"
                        wire:model.live="selectedServicePointIds"
                        value="{{ $servicePoint->id }}"
                        @disabled(! $servicePoint->activeQrCode)
                    >

                    <span class="min-w-0">
                        <span class="block truncate font-medium text-zinc-950">{{ $servicePoint->name }}</span>
                        <span class="block text-sm text-zinc-500">
                            {{ __('Zone') }}: {{ $servicePoint->areaNode?->name ?? __('No zone') }}
                            / {{ __('Number') }}: {{ $servicePoint->display_number ?: __('Not set') }}
                        </span>
                    </span>
                </label>

                <div class="flex flex-wrap items-center justify-end gap-2">
                    @if ($servicePoint->activeQrCode)
                        <flux:badge color="green">{{ $servicePoint->activeQrCode->short_code }}</flux:badge>
                    @else
                        <flux:badge color="zinc">{{ __('No QR') }}</flux:badge>

                        <flux:button icon="qr-code" size="sm" type="button" wire:click="createQrForServicePoint({{ $servicePoint->id }})" wire:loading.attr="disabled" wire:target="createQrForServicePoint({{ $servicePoint->id }})">
                            {{ __('Create QR') }}
                        </flux:button>
                    @endif
                </div>
            </article>
        @empty
            <div class="qr-print-list-empty">
                {{ __('No service points in this zone.') }}
            </div>
        @endforelse
    </section>

    @if (count($this->printItems) === 0)
        <section class="qr-print-empty-preview">
            {{ __('Select service points with active QR codes to print.') }}
        </section>
    @else
        <section class="qr-bulk-sticker-grid" aria-label="{{ __('QR stickers preview') }}">
            @foreach ($this->printItems as $item)
                <article
                    wire:key="bulk-print-sticker-{{ $item['service_point_id'] }}"
                    @class(['qr-sticker', $this->selectedPreset->cssClass()])
                    data-preset="{{ $this->selectedPreset->value }}"
                >
                    <div class="qr-sticker-brand">
                        @if ($this->restaurantLogoUrl)
                            <img src="{{ $this->restaurantLogoUrl }}" alt="{{ $item['brand_name'] }}" class="qr-sticker-logo">
                        @else
                            <div class="qr-sticker-logotype">{{ $item['brand_name'] }}</div>
                        @endif
                    </div>

                    <p class="qr-sticker-title">{{ __('Сканируйте, чтобы открыть меню') }}</p>

                    <img src="{{ $item['qr_image_data_uri'] }}" alt="{{ __('QR image') }}" class="qr-sticker-image">

                    <div class="qr-sticker-code">{{ $item['short_code'] }}</div>

                    @if ($printTableNumber)
                        <div class="qr-sticker-table-number">
                            {{ __('Стол') }}: {{ $item['service_point_label'] }}
                        </div>
                    @endif
                </article>
            @endforeach
        </section>
    @endif
</main>
