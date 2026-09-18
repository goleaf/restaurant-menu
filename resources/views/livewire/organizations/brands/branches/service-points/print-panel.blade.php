<section class="rm-floor__editor" data-floor-print-panel>
    @assets
        @vite('resources/scss/qr-print.scss')
    @endassets
    <div class="qr-print-controls">
        <flux:heading size="lg">{{ __('floor.print.title') }}</flux:heading>
        <flux:text>{{ __('floor.print.selection_count', ['count' => $selectedCount]) }}</flux:text>
        <ul class="qr-print-availability" aria-label="{{ __('floor.print.availability') }}">
            @forelse ($availability as $target)
                <li data-floor-print-target="{{ $target['id'] }}" data-qr-state="{{ $target['state'] }}" wire:key="floor-print-target-{{ $target['id'] }}">
                    <span>{{ $target['name'] }} · {{ $target['label'] }}</span>
                    @if ($target['state'] !== 'ready')
                        <flux:button data-floor-transition wire:click="reviewQr({{ $target['id'] }})" wire:offline.attr="disabled">{{ __('floor.print.review_qr') }}</flux:button>
                    @endif
                </li>
            @empty
            @endforelse
        </ul>
        <form wire:submit="preparePrint" data-floor-editor data-menu-draft-form class="rm-floor__form" novalidate>
            <flux:select wire:model="form.preset" :label="__('floor.print.preset')">
                @forelse ($presetOptions as $option)<flux:select.option :value="$option['value']">{{ __($option['label']) }}</flux:select.option>@empty @endforelse
            </flux:select>
            <flux:select wire:model="form.locale" :label="__('floor.print.locale')">
                <flux:select.option value="en">{{ __('ui.languages.en') }}</flux:select.option>
                <flux:select.option value="lt">{{ __('ui.languages.lt') }}</flux:select.option>
                <flux:select.option value="ru">{{ __('ui.languages.ru') }}</flux:select.option>
            </flux:select>
            <flux:checkbox wire:model="form.printTableNumber" :label="__('floor.print.number')" />
            <flux:callout variant="warning" :text="__('floor.print.mutable_number_warning')" />
            <div class="rm-floor__actions">
                <flux:button variant="primary" type="submit" wire:offline.attr="disabled" wire:loading.attr="disabled">{{ __('floor.print.prepare') }}</flux:button>
                <flux:button type="button" wire:click="cancelPrint" data-floor-cancel>{{ __('ui.actions.cancel') }}</flux:button>
            </div>
        </form>
        @if ($errors->any())
            <flux:callout variant="danger" role="alert" tabindex="-1">
                @forelse ($errors->all() as $error)<p>{{ $error }}</p>@empty @endforelse
            </flux:callout>
        @endif
    </div>
    @if ($snapshot)
        <div class="qr-print-controls rm-floor__actions" data-floor-print-preview>
            <flux:button icon="printer" wire:click="printLabels" wire:offline.attr="disabled" wire:loading.attr="disabled">{{ __('qr.actions.print') }}</flux:button>
            <flux:button icon="arrow-down-tray" wire:click="downloadPdf" wire:offline.attr="disabled" wire:loading.attr="disabled">{{ __('qr.actions.download_pdf') }}</flux:button>
        </div>
        <div class="qr-bulk-sticker-grid" data-floor-print-labels data-floor-print-preview aria-label="{{ $printHeading }}" lang="{{ $snapshot['locale'] }}">
            @forelse ($snapshot['items'] as $item)
                <article class="qr-sticker {{ $presetClass }}" wire:key="floor-label-{{ $item['service_point_id'] }}" data-qr-preset="{{ $snapshot['preset'] }}">
                    <p class="qr-sticker-logotype">{{ $snapshot['branch_name'] }}</p>
                    <p class="qr-sticker-title">{{ $stickerTitle }}</p>
                    <img class="qr-sticker-image" src="{{ $item['qr_image_data_uri'] }}" alt="{{ __('qr.labels.image') }}" width="420" height="420">
                    <p class="qr-sticker-code">{{ $item['short_code'] }}</p>
                    @if ($snapshot['print_table_number'])<p class="qr-sticker-table-number">{{ $tableLabel }}: {{ $item['service_point_label'] }}</p>@endif
                </article>
            @empty
            @endforelse
        </div>
    @endif
</section>
