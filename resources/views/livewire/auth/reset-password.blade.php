<div class="flex flex-col gap-6">
    <x-auth-header :title="__('ui.auth.reset_password.reset_password')" :description="__('ui.auth.reset_password.please_enter_your_new_password_below')" />

    <form wire:submit="resetPassword" novalidate class="flex flex-col gap-6">

        <!-- Email Address -->
        <flux:input
            name="email"
            wire:model="form.email" error:name="form.email" :invalid="$errors->has('form.email')"
            :label="__('ui.auth.reset_password.email')"
            type="email"
            required
            autocomplete="email"
        />

        <!-- Password -->
        <flux:input
            name="password"
            wire:model="form.password" error:name="form.password" :invalid="$errors->has('form.password')"
            :label="__('ui.auth.confirm_password.password')"
            type="password"
            required
            autocomplete="new-password"
            :placeholder="__('ui.auth.confirm_password.password')"
            passwordrules="{{ $passwordRules }}"
            viewable
        />

        <!-- Confirm Password -->
        <flux:input
            name="password_confirmation"
            wire:model="form.password_confirmation" error:name="form.password_confirmation" :invalid="$errors->has('form.password_confirmation')"
            :label="__('ui.auth.confirm_password.confirm_password')"
            type="password"
            required
            autocomplete="new-password"
            :placeholder="__('ui.auth.confirm_password.confirm_password')"
            passwordrules="{{ $passwordRules }}"
            viewable
        />

        <div class="flex items-center justify-end">
            <flux:button type="submit" wire:loading.attr="disabled" wire:offline.attr="disabled" variant="primary" class="w-full" data-test="reset-password-button">
                {{ __('ui.auth.reset_password.reset_password') }}
            </flux:button>
        </div>
    </form>
</div>
