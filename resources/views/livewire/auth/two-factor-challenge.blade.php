<div class="flex flex-col gap-6" x-data="twoFactorChallenge" data-recovery="{{ $recovery ? 'true' : 'false' }}">
    @if ($recovery)
        <x-auth-header :title="__('ui.auth.two_factor_challenge.recovery_code')" :description="__('ui.auth.two_factor_challenge.please_confirm_access_to_your_account_by_enter')" />
    @else
        <x-auth-header :title="__('ui.auth.two_factor_challenge.authentication_code')" :description="__('ui.auth.two_factor_challenge.enter_the_authentication_code_provided_by_your')" />
    @endif

    <form wire:submit="authenticate" novalidate class="space-y-5">
        @if ($recovery)
            <flux:input wire:key="recovery-code" name="recovery_code" x-ref="recovery_code" wire:model="form.recovery_code" error:name="form.recovery_code" :invalid="$errors->has('form.recovery_code')" :label="__('ui.auth.two_factor_challenge.recovery_code')" autocomplete="one-time-code" required />
        @else
            <div x-ref="otp">
                <flux:otp wire:key="authentication-code" name="code" wire:model="form.code" error:name="form.code" :invalid="$errors->has('form.code')" length="6" :label="__('ui.auth.two_factor_challenge.authentication_code')" class="mx-auto" />
            </div>
        @endif
        <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled" wire:offline.attr="disabled">
            {{ __('ui.actions.continue') }}
        </flux:button>
    </form>

    <div class="text-center text-sm">
        <span>{{ __('ui.auth.two_factor_challenge.or_you_can') }}</span>
        <flux:button type="button" variant="ghost" wire:click="toggleRecovery" wire:loading.attr="disabled" wire:offline.attr="disabled">
            @if ($recovery)
                {{ __('ui.auth.two_factor_challenge.login_using_an_authentication_code') }}
            @else
                {{ __('ui.auth.two_factor_challenge.login_using_a_recovery_code') }}
            @endif
        </flux:button>
    </div>
</div>
