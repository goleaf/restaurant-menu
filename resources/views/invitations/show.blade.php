<x-layouts::auth :title="$title">
    <div class="flex min-w-0 flex-col gap-6" data-invitation-review>
        <x-auth-header
            :title="$title"
            :description="$isAuthenticated ? __('invitations.description') : __('invitations.description_guest')"
        />

        <dl class="grid gap-4 rounded-card border border-border-subtle bg-surface-muted p-4 text-sm">
            <div class="grid gap-1">
                <dt class="font-medium text-zinc-600 dark:text-zinc-300">{{ __('invitations.organization') }}</dt>
                <dd class="wrap-anywhere text-text-primary">{{ $organizationName }}</dd>
            </div>

            @if ($brandName)
                <div class="grid gap-1">
                    <dt class="font-medium text-text-muted">{{ __('invitations.brand') }}</dt>
                    <dd class="wrap-anywhere text-text-primary">{{ $brandName }}</dd>
                </div>
            @endif

            @if ($branchName)
                <div class="grid gap-1">
                    <dt class="font-medium text-zinc-600 dark:text-zinc-300">{{ __('invitations.branch') }}</dt>
                    <dd class="wrap-anywhere text-text-primary">{{ $branchName }}</dd>
                </div>
            @endif

            <div class="grid gap-1">
                <dt class="font-medium text-zinc-600 dark:text-zinc-300">{{ __('invitations.role') }}</dt>
                <dd class="wrap-anywhere text-text-primary">{{ $roleName }}</dd>
            </div>

            <div class="grid gap-1">
                <dt class="font-medium text-zinc-600 dark:text-zinc-300">{{ __('invitations.expires_at') }}</dt>
                <dd class="text-zinc-950 dark:text-white">{{ $expiresAt }}</dd>
            </div>
        </dl>

        <p class="text-sm leading-6 text-text-muted">{{ $accessExplanation }}</p>

        @if ($isAuthenticated)
            <form method="POST" action="{{ $acceptUrl }}" x-data="{ submitting: false, offline: !navigator.onLine }" @online.window="offline = false" @offline.window="offline = true" @submit="if (offline || submitting) { $event.preventDefault(); } else { submitting = true; }">
                @csrf
                <input type="hidden" name="invitation_version" value="{{ $invitationVersion }}">

                <flux:button type="submit" variant="primary" class="w-full" x-bind:disabled="submitting || offline">
                    {{ __('invitations.actions.accept') }}
                </flux:button>
                <p x-cloak x-show="offline" role="status" class="mt-3 text-sm text-text-muted">{{ __('invitations.offline') }}</p>
            </form>
        @elseif ($hasExistingAccount)
            <div class="grid gap-4">
                <flux:heading size="lg">{{ __('invitations.account.existing_title') }}</flux:heading>
                <flux:text>{{ __('invitations.account.existing_description') }}</flux:text>
                <flux:button :href="$loginUrl" variant="primary" class="w-full">{{ __('ui.auth.login.log_in') }}</flux:button>
            </div>
        @else
            <div class="grid gap-4">
                <div>
                    <flux:heading size="lg">{{ __('invitations.account.create_title') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('invitations.account.create_description') }}</flux:text>
                </div>

                <form method="POST" action="{{ $registerUrl }}" novalidate class="flex flex-col gap-6" x-data="{ submitting: false, offline: !navigator.onLine }" @online.window="offline = false" @offline.window="offline = true" @submit="if (offline || submitting) { $event.preventDefault(); } else { submitting = true; }">
                    @csrf
                    <input type="hidden" name="invitation_version" value="{{ $invitationVersion }}">

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

                    <flux:button type="submit" variant="primary" class="w-full" data-test="register-invitation-button" x-bind:disabled="submitting || offline">
                        {{ __('invitations.actions.create_account_and_accept') }}
                    </flux:button>
                    <p x-cloak x-show="offline" role="status" class="text-sm text-text-muted">{{ __('invitations.offline') }}</p>
                </form>

                <div class="space-x-1 text-center text-sm text-zinc-600 dark:text-zinc-400">
                    <span>{{ __('invitations.account.already_have') }}</span>
                    <flux:link :href="$loginUrl">{{ __('ui.auth.login.log_in') }}</flux:link>
                </div>
            </div>
        @endif
    </div>
</x-layouts::auth>
