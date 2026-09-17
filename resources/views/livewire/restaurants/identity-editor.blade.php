<div class="rm-restaurant-center__identity">
    <h2>{{ $title }}</h2>
    @if ($logoUrl)<img src="{{ $logoUrl }}" alt="{{ $title }}" width="96" height="96" />@endif
    <flux:callout wire:offline variant="warning" :heading="__('menu.workspace.offline')" :text="__('center.offline')" />
    @if ($canEdit)
        <form novalidate wire:submit="save" class="rm-restaurant-center__form">
            <flux:input wire:model="form.name" :label="__('center.name')" required />
            @if ($isRestaurant)
                <flux:input wire:model="form.address" :label="__('center.address')" required />
                <flux:input wire:model="form.city" :label="__('center.city')" required />
                <flux:input wire:model="form.country" :label="__('center.country')" required />
                <flux:select wire:model="form.timezone" variant="listbox" searchable :label="__('center.timezone')">@forelse ($timezones as $key => $label)<flux:select.option :value="$key">{{ $label }}</flux:select.option>@empty @endforelse</flux:select>
                <flux:select wire:model="form.currency" :label="__('center.currency')">@forelse ($currencies as $key => $label)<flux:select.option :value="$key">{{ $label }}</flux:select.option>@empty @endforelse</flux:select>
                <flux:checkbox wire:model="form.isActive" :label="__('center.administrative_active')" />
                <flux:text>{{ __('center.activity_notice') }}</flux:text>
            @endif
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('center.save') }}</flux:button>
        </form>
        <form novalidate wire:submit="saveLogo" class="rm-restaurant-center__form" x-data="catalogUpload({ field: 'logo', key: 'restaurant-logo' })"
            x-on:change="selectionChanged($event)" x-on:livewire-upload-start="startUpload()" x-on:livewire-upload-finish="finishUpload()"
            x-on:livewire-upload-error="failUpload()" x-on:livewire-upload-cancel="cancelUpload()" x-on:livewire-upload-progress="updateProgress($event)"
            x-on:restaurant-logo-saved.window="clearSelection()">
            <flux:file-upload wire:model="logo" :label="__('center.logo')"><flux:file-upload.dropzone :heading="__('center.choose_logo')" /></flux:file-upload>
            <progress x-cloak x-show="uploading" x-bind:value="progress" max="100" aria-label="{{ __('uploads.editor.uploading') }}"></progress>
            @if ($logoPreview)<img src="{{ $logoPreview }}" alt="{{ __('center.logo') }}" width="96" height="96" />@endif
            @if ($logo)<flux:button type="button" x-on:click="$wire.$set('logo', null, false); clearSelection()">{{ __('uploads.editor.remove_pending') }}</flux:button>@endif
            <flux:error name="logo" />
            <flux:text>{{ __('center.logo_independent') }}</flux:text>
            <flux:button type="submit" wire:loading.attr="disabled" wire:offline.attr="disabled" x-bind:disabled="uploading">{{ __('center.save_logo') }}</flux:button>
        </form>
        @if ($logoUrl)
            <flux:modal.trigger name="remove-restaurant-logo"><flux:button>{{ __('uploads.actions.remove') }}: {{ __('center.logo') }}</flux:button></flux:modal.trigger>
            <flux:modal name="remove-restaurant-logo">
                <flux:heading>{{ __('uploads.actions.remove') }}: {{ __('center.logo') }}</flux:heading>
                <flux:text>{{ __('center.logo_independent') }}</flux:text>
                <flux:button wire:click="removeLogo" wire:offline.attr="disabled">{{ __('uploads.actions.remove') }}</flux:button>
            </flux:modal>
        @endif
    @elseif ($archived)
        <flux:callout :heading="__('center.archived')" />
    @else
        <flux:text>{{ __('center.read_only') }}</flux:text>
    @endif
    @if ($isRestaurant && $canEdit)<flux:button wire:click="continueSetup" wire:offline.attr="disabled">{{ __('center.continue') }}</flux:button>@endif
    @if ($readiness)<x-restaurants.readiness :readiness="$readiness" />@endif
    @if ($canChangeLifecycle)
        <flux:button wire:click="$set('confirming', true)" variant="danger">{{ $archived ? __('center.restore') : __('center.archive') }}</flux:button>
        <flux:modal wire:model="confirming" name="structure-lifecycle">
            <flux:heading>{{ $scopeLabel }}: {{ $title }}</flux:heading>
            <flux:text>{{ $archived ? __('center.restore_notice') : __('center.archive_notice') }}</flux:text>
            <form novalidate wire:submit="changeLifecycle" class="rm-restaurant-center__form">
                <flux:input wire:model="confirmation" :label="__('center.confirm_name')" />
                <flux:error name="structureDeletion" />
                <flux:button type="submit" variant="danger" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ $archived ? __('center.restore') : __('center.archive') }}</flux:button>
            </form>
        </flux:modal>
    @endif
</div>
