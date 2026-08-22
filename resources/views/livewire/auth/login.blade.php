<x-layouts::auth :title="__('ui.auth.login.log_in')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('ui.auth.login.log_in_to_your_account')" :description="__('ui.auth.login.enter_your_email_and_password_below_to_log_in')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="$sessionStatus" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('ui.auth.forgot_password.email_address')"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                :placeholder="__('fields.placeholders.email_example')"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('ui.auth.confirm_password.password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('ui.auth.confirm_password.password')"
                    viewable
                />

                <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                    {{ __('ui.auth.login.forgot_your_password') }}
                </flux:link>
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('ui.auth.login.remember_me')" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    {{ __('ui.auth.login.log_in') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
            <span>{{ __('ui.auth.login.don_t_have_an_account') }}</span>
            <flux:link :href="route('register')" wire:navigate>{{ __('ui.auth.login.sign_up') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
