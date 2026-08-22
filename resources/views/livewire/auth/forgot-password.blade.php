<x-layouts::auth :title="__('ui.auth.forgot_password.forgot_password')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('ui.auth.forgot_password.forgot_password')" :description="__('ui.auth.forgot_password.enter_your_email_to_receive_a_password_reset_link')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="$sessionStatus" />

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('ui.auth.forgot_password.email_address')"
                type="email"
                required
                :placeholder="__('fields.placeholders.email_example')"
            />

            <flux:button variant="primary" type="submit" class="w-full" data-test="email-password-reset-link-button">
                {{ __('ui.auth.forgot_password.email_password_reset_link') }}
            </flux:button>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('ui.auth.forgot_password.or_return_to') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('ui.auth.forgot_password.log_in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
