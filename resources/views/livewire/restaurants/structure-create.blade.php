<div class="rm-restaurant-center__identity" data-structure-create>
    <h2>{{ $title }}</h2>
    @if ($organizationName)<p>{{ __('center.organization') }}: {{ $organizationName }}</p>@endif
    <flux:text>{{ __('center.structure_create_notice') }}</flux:text>
    <flux:callout wire:offline variant="warning" :heading="__('menu.workspace.offline')" :text="__('center.offline')" />
    @error('creation')<flux:callout variant="danger" :heading="$message" role="alert" tabindex="-1" />@enderror
    <form novalidate wire:submit="save" wire:target="save" wire:loading.attr="aria-busy" class="rm-restaurant-center__form">
        <flux:input wire:model="form.name" name="structure_name" autocomplete="organization" error:name="form.name" :invalid="$errors->has('form.name')" :label="$nameLabel" required error:id="center-create-form-name-error" error:class="text-danger!" aria-describedby="center-create-form-name-error" />
        <flux:button type="submit" wire:target="save" wire:loading.attr="disabled" wire:offline.attr="disabled" variant="primary">{{ $title }}</flux:button>
    </form>
</div>
