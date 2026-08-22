<x-layouts::auth :title="$title">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="$title"
            :description="$isAuthenticated ? __('invitations.description') : __('invitations.description_guest')"
        />

        <dl class="grid gap-4 rounded-xl border border-zinc-200 bg-white p-5 text-sm shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="grid gap-1">
                <dt class="font-medium text-zinc-600 dark:text-zinc-300">{{ __('invitations.organization') }}</dt>
                <dd class="text-zinc-950 dark:text-white">{{ $organizationName }}</dd>
            </div>

            @if ($branchName)
                <div class="grid gap-1">
                    <dt class="font-medium text-zinc-600 dark:text-zinc-300">{{ __('invitations.branch') }}</dt>
                    <dd class="text-zinc-950 dark:text-white">{{ $branchName }}</dd>
                </div>
            @endif

            <div class="grid gap-1">
                <dt class="font-medium text-zinc-600 dark:text-zinc-300">{{ __('invitations.role') }}</dt>
                <dd class="text-zinc-950 dark:text-white">{{ $roleName }}</dd>
            </div>

            <div class="grid gap-1">
                <dt class="font-medium text-zinc-600 dark:text-zinc-300">{{ __('invitations.expires_at') }}</dt>
                <dd class="text-zinc-950 dark:text-white">{{ $expiresAt }}</dd>
            </div>
        </dl>

        @if ($isAuthenticated)
            <form method="POST" action="{{ $acceptUrl }}">
                @csrf

                <flux:button type="submit" variant="primary" class="w-full">
                    {{ __('invitations.actions.accept') }}
                </flux:button>
            </form>
        @else
            <div class="grid gap-4">
                <div>
                    <flux:heading size="lg">{{ __('invitations.account.create_title') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('invitations.account.create_description') }}</flux:text>
                </div>

                <form method="POST" action="{{ $registerUrl }}" class="flex flex-col gap-6">
                    @csrf

                    <flux:input
                        name="name"
                        :label="__('reports.csv.name')"
                        :value="old('name')"
                        type="text"
                        required
                        autocomplete="name"
                        :placeholder="__('ui.auth.register.full_name')"
                    />

                    <flux:input
                        name="email"
                        :label="__('ui.auth.forgot_password.email_address')"
                        :value="$invitationEmail ?? old('email')"
                        type="email"
                        required
                        autocomplete="email"
                        :placeholder="__('fields.placeholders.email_example')"
                        :readonly="$invitationEmail !== null"
                    />

                    @if ($invitationEmail !== null)
                        <flux:text size="sm">{{ __('invitations.account.email_locked') }}</flux:text>
                    @endif

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

                    <flux:button type="submit" variant="primary" class="w-full" data-test="register-invitation-button">
                        {{ __('invitations.actions.create_account_and_accept') }}
                    </flux:button>
                </form>

                <div class="space-x-1 text-center text-sm text-zinc-600 dark:text-zinc-400">
                    <span>{{ __('invitations.account.already_have') }}</span>
                    <flux:link :href="$loginUrl">{{ __('ui.auth.login.log_in') }}</flux:link>
                </div>
            </div>
        @endif
    </div>
</x-layouts::auth>
