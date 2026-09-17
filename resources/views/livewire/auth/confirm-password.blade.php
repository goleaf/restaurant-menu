<div class="flex flex-col gap-6">
    <x-auth-header
        :title="__('ui.auth.confirm_password.confirm_password')"
        :description="__('ui.auth.confirm_password.this_is_a_secure_area_of_the_application_please_co')"
    />

    <form wire:submit="confirm" novalidate class="flex flex-col gap-6">

        <flux:input
            name="password"
            wire:model="form.password" error:name="form.password" :invalid="$errors->has('form.password')"
            :label="__('ui.auth.confirm_password.password')"
            type="password"
            required
            autocomplete="current-password"
            :placeholder="__('ui.auth.confirm_password.password')"
            viewable
        />

        <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:offline.attr="disabled" class="w-full" data-test="confirm-password-button">
            {{ __('ui.actions.confirm') }}
        </flux:button>
    </form>
</div>
