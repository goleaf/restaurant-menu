<section class="rm-floor__editor" data-floor-qr-panel>
    <flux:heading size="lg">{{ __('qr.labels.title') }} · {{ $qr['point_name'] }}</flux:heading>
    @if ($message !== '')<flux:callout variant="success" role="status" :text="$message" />@endif
    <flux:text>{{ $qr['area_name'] }} · {{ $qr['point_number'] }}</flux:text>
    <flux:badge>{{ __($qr['status_label']) }}</flux:badge>
    @if ($qr['created_at'])<flux:text>{{ __('qr.labels.created') }}: {{ $qr['created_at'] }}</flux:text>@endif
    @if ($qr['short_code'])<flux:text>{{ $qr['short_code'] }}</flux:text>@endif
    @if ($qr['image_url'])
        <img src="{{ $qr['image_url'] }}" alt="{{ __('qr.labels.image') }}" width="320" height="320" class="qr-sticker-image">
    @elseif ($qr['short_code'])
        <flux:callout variant="warning" :text="__('floor.qr.image_missing')" />
    @endif
    @if ($qr['public_url'])
        <flux:button :href="$qr['public_url']" target="_blank" rel="noopener" icon="arrow-top-right-on-square">{{ __('qr.actions.open_guest_url') }}</flux:button>
    @endif
    @if ($operation === '')
        <div class="rm-floor__actions">
            @if ($qr['has_other_current'])<flux:button wire:click="openCurrentQr" wire:offline.attr="disabled">{{ __('floor.qr.open_current') }}</flux:button>@endif
            @if ($qr['can_repair'])<flux:button wire:click="downloadQrImage" icon="arrow-down-tray" wire:offline.attr="disabled">{{ __('qr.actions.download') }}</flux:button>@endif
            @if ($qr['can_repair'])<flux:button wire:click="requestPrint" icon="printer" wire:offline.attr="disabled">{{ __('qr.actions.print') }}</flux:button>@endif
            @if ($qr['can_generate'])<flux:button wire:click="prepareOperation('generate')" wire:offline.attr="disabled">{{ __('floor.qr.operation.generate') }}</flux:button>@endif
            @if ($qr['can_repair'])<flux:button wire:click="prepareOperation('repair')" wire:offline.attr="disabled">{{ __('floor.qr.repair') }}</flux:button>@endif
            @if ($qr['can_disable'])<flux:button wire:click="prepareOperation('disable')" wire:offline.attr="disabled">{{ __('qr.actions.disable') }}</flux:button>@endif
            @if ($qr['can_reissue'])<flux:button wire:click="prepareOperation('reissue')" wire:offline.attr="disabled">{{ __('qr.actions.reissue') }}</flux:button>@endif
        </div>
        @if ($errors->any())
            <flux:callout variant="danger" role="alert" tabindex="-1">@forelse ($errors->all() as $error)<p>{{ $error }}</p>@empty @endforelse</flux:callout>
        @endif
    @else
        <form wire:submit="applyOperation" data-floor-editor data-menu-draft-form data-floor-qr-operation class="rm-floor__form" novalidate>
            <flux:heading>{{ $operationLabel }}</flux:heading>
            @if ($operation === 'reissue')
                <flux:callout variant="warning" :text="__('floor.qr.reissue_warning')" />
                <flux:input wire:model="form.confirmation" :label="__('qr.labels.current_short_code')" :description="$qr['short_code']" maxlength="24" />
                <flux:textarea wire:model="form.reason" :label="__('floor.fields.reason')" :description="__('floor.qr.optional_reason')" maxlength="500" rows="2" />
            @elseif ($operation === 'disable')
                <flux:callout variant="warning" :text="__('floor.qr.disable_warning')" />
                <flux:textarea wire:model="form.reason" :label="__('qr.labels.disable_reason')" maxlength="500" rows="3" />
            @else
                <flux:text>{{ __('floor.qr.identity_preserved') }}</flux:text>
            @endif
            @if ($errors->any())
                <flux:callout variant="danger" role="alert" tabindex="-1">
                    @forelse ($errors->all() as $error)<p>{{ $error }}</p>@empty @endforelse
                </flux:callout>
            @endif
            <div class="rm-floor__actions">
                <flux:button variant="primary" type="submit" wire:offline.attr="disabled" wire:loading.attr="disabled" wire:target="applyOperation">{{ __('ui.actions.confirm') }}</flux:button>
                <flux:button type="button" wire:click="cancelOperation" data-floor-cancel>{{ __('ui.actions.cancel') }}</flux:button>
            </div>
        </form>
    @endif
</section>
