<x-layouts::auth :title="__('ui.auth.reset_password.reset_password')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('ui.auth.reset_password.reset_password')" :description="__('ui.auth.reset_password.please_enter_your_new_password_below')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="$sessionStatus" />

        <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-6">
            @csrf
            <!-- Token -->
            <input type="hidden" name="token" value="{{ $resetToken }}">

            <!-- Email Address -->
            <flux:input
                name="email"
                value="{{ $resetEmail }}"
                :label="__('ui.auth.reset_password.email')"
                type="email"
                required
                autocomplete="email"
            />

            <!-- Password -->
            <flux:input
                name="password"
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
                :label="__('ui.auth.confirm_password.confirm_password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('ui.auth.confirm_password.confirm_password')"
                passwordrules="{{ $passwordRules }}"
                viewable
            />

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full" data-test="reset-password-button">
                    {{ __('ui.auth.reset_password.reset_password') }}
                </flux:button>
            </div>
        </form>
    </div>
</x-layouts::auth>
