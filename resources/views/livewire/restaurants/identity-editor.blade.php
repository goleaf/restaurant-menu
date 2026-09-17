<div class="rm-restaurant-center__identity">
    <h2>{{ $title }}</h2>
    @if ($organizationName || $brandName)
        <dl class="rm-restaurant-center__context">
            @if ($organizationName)
                <dt>{{ __('center.organization') }}</dt><dd data-center-parent="organization">{{ $organizationName }}</dd>
            @endif
            @if ($brandName)
                <dt>{{ __('center.brand') }}</dt><dd data-center-parent="brand">{{ $brandName }}</dd>
            @endif
        </dl>
    @endif
    @if ($logoUrl)<img src="{{ $logoUrl }}" alt="{{ $title }}" width="96" height="96" />@endif
    <flux:callout wire:offline variant="warning" :heading="__('menu.workspace.offline')" :text="__('center.offline')" />
    @if ($canEdit)
        <form novalidate wire:submit="save" class="rm-restaurant-center__form">
            <flux:input wire:model="form.name" :label="__('center.name')" required error:id="center-identity-form-name-error" error:class="text-danger!" aria-describedby="center-identity-form-name-error" />
            @if ($isRestaurant)
                <flux:input wire:model="form.address" :label="__('center.address')" required error:id="center-identity-form-address-error" error:class="text-danger!" aria-describedby="center-identity-form-address-error" />
                <flux:input wire:model="form.city" :label="__('center.city')" required error:id="center-identity-form-city-error" error:class="text-danger!" aria-describedby="center-identity-form-city-error" />
                <flux:input wire:model="form.country" :label="__('center.country')" required error:id="center-identity-form-country-error" error:class="text-danger!" aria-describedby="center-identity-form-country-error" />
                <flux:select wire:model="form.timezone" variant="listbox" searchable :label="__('center.timezone')" error:id="center-identity-form-timezone-error" error:class="text-danger!" aria-describedby="center-identity-form-timezone-error">
                    <x-slot name="trigger"><flux:select.button :invalid="$errors->has('form.timezone')" :aria-invalid="$errors->has('form.timezone') ? 'true' : 'false'" aria-describedby="center-identity-form-timezone-error" /></x-slot>
                    @forelse ($timezones as $key => $label)<flux:select.option :value="$key">{{ $label }}</flux:select.option>@empty @endforelse</flux:select>
                <flux:select wire:model="form.currency" :label="__('center.currency')" error:id="center-identity-form-currency-error" error:class="text-danger!" aria-describedby="center-identity-form-currency-error">@forelse ($currencies as $key => $label)<flux:select.option :value="$key">{{ $label }}</flux:select.option>@empty @endforelse</flux:select>
                <flux:checkbox wire:model="form.isActive" :label="__('center.administrative_active')" error:id="center-identity-form-isActive-error" error:class="text-danger!" aria-describedby="center-identity-form-isActive-error" />
                @if ($canSuspend)
                    <div x-cloak x-show="!$wire.form.isActive">
                        <flux:textarea wire:model="form.suspensionReason" :label="__('validation.attributes.suspension_reason')" error:id="center-identity-form-suspensionReason-error" error:class="text-danger!" aria-describedby="center-identity-form-suspensionReason-error" />
                    </div>
                @endif
                <flux:text>{{ __('center.activity_notice') }}</flux:text>
            @endif
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('center.save') }}</flux:button>
        </form>
        <form novalidate wire:submit="saveLogo" class="rm-restaurant-center__form" x-data="catalogUpload({ field: 'logo', key: 'restaurant-logo' })"
            x-on:change="selectionChanged($event)" x-on:livewire-upload-start="startUpload()" x-on:livewire-upload-finish="finishUpload()"
            x-on:livewire-upload-error="failUpload()" x-on:livewire-upload-cancel="cancelUpload()" x-on:livewire-upload-progress="updateProgress($event)"
            x-on:restaurant-logo-saved.window="clearSelection()">
            <flux:file-upload wire:model="logo" :label="__('center.logo')" error:id="center-identity-logo-error" error:class="text-danger!">
                <flux:file-upload.dropzone :heading="__('center.choose_logo')" role="button" :aria-label="__('center.choose_logo')" aria-describedby="center-identity-logo-error" :aria-invalid="$errors->has('logo') ? 'true' : 'false'" />
            </flux:file-upload>
            <progress x-cloak x-show="uploading" x-bind:value="progress" max="100" aria-label="{{ __('uploads.editor.uploading') }}"></progress>
            @if ($logoPreview)<img src="{{ $logoPreview }}" alt="{{ __('center.logo') }}" width="96" height="96" />@endif
            @if ($logo)<flux:button type="button" x-on:click="$wire.$set('logo', null, false); clearSelection()">{{ __('uploads.editor.remove_pending') }}</flux:button>@endif
            <flux:text>{{ __('center.logo_independent') }}</flux:text>
            <flux:button type="submit" wire:loading.attr="disabled" wire:offline.attr="disabled" x-bind:disabled="uploading">{{ __('center.save_logo') }}</flux:button>
        </form>
        @if ($logoUrl)
            <flux:modal.trigger name="remove-restaurant-logo"><flux:button>{{ __('uploads.actions.remove') }}: {{ __('center.logo') }}</flux:button></flux:modal.trigger>
            <flux:modal name="remove-restaurant-logo" :closable="false">
                <x-modal-close-button :autofocus="true" />
                <flux:heading id="center-remove-logo-heading" x-bind="dialogLabel">{{ __('uploads.actions.remove') }}: {{ __('center.logo') }}</flux:heading>
                <flux:text>{{ __('center.logo_independent') }}</flux:text>
                <flux:button wire:click="removeLogo" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('uploads.actions.remove') }}</flux:button>
            </flux:modal>
        @endif
    @elseif ($archived)
        <flux:callout :heading="__('center.archived')" />
    @else
        <flux:text>{{ __('center.read_only') }}</flux:text>
    @endif
    @if ($canContinueSetup)<flux:button wire:click="continueSetup" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('center.continue') }}</flux:button>@endif
    @if ($readiness)<x-restaurants.readiness :readiness="$readiness" />@endif
    @if ($canChangeLifecycle)
        <flux:button data-center-lifecycle-trigger wire:click="$set('confirming', true)" variant="danger">{{ $archived ? __('center.restore') : __('center.archive') }}</flux:button>
        <flux:modal wire:model="confirming" name="structure-lifecycle" :closable="false">
            <x-modal-close-button :autofocus="true" />
            <flux:heading id="center-lifecycle-heading" x-bind="dialogLabel">{{ $scopeLabel }}: {{ $title }}</flux:heading>
            <flux:text>{{ $archived ? __('center.restore_notice') : __('center.archive_notice') }}</flux:text>
            <form novalidate wire:submit="changeLifecycle" class="rm-restaurant-center__form">
                <flux:input wire:model="confirmation" :label="__('center.confirm_name')" error:id="center-identity-confirmation-error" error:class="text-danger!" aria-describedby="center-identity-confirmation-error" />
                <flux:error name="structureDeletion" class="text-danger!" />
                <flux:button type="submit" variant="danger" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ $archived ? __('center.restore') : __('center.archive') }}</flux:button>
            </form>
        </flux:modal>
    @endif
</div>
