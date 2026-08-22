<x-layouts::auth :title="__('ui.auth.register.register')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('ui.auth.register.create_an_account')" :description="__('ui.auth.register.enter_your_details_below_to_create_your_account')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="$sessionStatus" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf
            <!-- Name -->
            <flux:input
                name="name"
                :label="__('reports.csv.name')"
                :value="old('name')"
                type="text"
                required
                autocomplete="name"
                :placeholder="__('ui.auth.register.full_name')"
            />

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
                <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                    {{ __('ui.auth.register.create_account') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('ui.auth.register.already_have_an_account') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('ui.auth.login.log_in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
