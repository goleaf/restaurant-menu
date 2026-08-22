<section data-layout="platform-dashboard" class="flex h-full w-full flex-1 flex-col gap-6">
    <header class="flex flex-col gap-2">
        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('ui.superadmin.dashboard.platform_workspace') }}</p>
        <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">{{ __('ui.superadmin.dashboard.platform_dashboard') }}</h1>
        <p class="max-w-2xl text-sm text-zinc-600 dark:text-zinc-300">
            {{ __('ui.superadmin.dashboard.saas_level_overview_for_organizations_brands_branch') }}
        </p>
    </header>

    <section class="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
        @foreach ($statRows as $stat)
            <div wire:key="platform-stat-{{ $stat['key'] }}" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $stat['label'] }}</p>
                <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-white">{{ $stat['value'] }}</p>
            </div>
        @endforeach
    </section>

    <section class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <flux:heading size="lg">{{ __('navigation.system_health') }}</flux:heading>
                <p class="mt-2 max-w-3xl text-sm text-zinc-600 dark:text-zinc-300">
                    {{ __('ui.superadmin.dashboard.production_safety_checks_show_only_safe_labels_and') }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('ui.superadmin.dashboard.environment') }}</span>
                <flux:badge :color="$productionSafetyReport['is_production'] ? 'amber' : 'zinc'">
                    {{ $productionSafetyReport['environment_label'] }}
                </flux:badge>
            </div>
        </div>

        <div class="mt-4 space-y-2">
            @forelse ($productionSafetyReport['warnings'] as $warning)
                <div wire:key="production-safety-warning-{{ $warning['code'] }}" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-900 dark:border-amber-700/60 dark:bg-amber-950/30 dark:text-amber-100">
                    {{ __($warning['message']) }}
                </div>
            @empty
                <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-900 dark:border-emerald-800/70 dark:bg-emerald-950/30 dark:text-emerald-100">
                    {{ __('superadmin.empty.no_safety_warnings') }}
                </p>
            @endforelse
        </div>
    </section>

    <section class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-700/60 dark:bg-amber-950/30">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <flux:heading size="lg">{{ __('ui.superadmin.dashboard.local_backups') }}</flux:heading>
                <p class="mt-2 max-w-3xl text-sm text-amber-900 dark:text-amber-100">
                    {{ __('ui.superadmin.dashboard.sqlite_backup_contains_sensitive_data_users_staff_a') }}
                </p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row">
                <x-dangerous-action-confirmation
                    name="sqlite-backup-download"
                    action="download_backup"
                    confirm-action="downloadBackup"
                    submit-target="downloadBackup"
                    confirm-label="ui.actions.i_understand"
                    reason-model="backupDownloadReason"
                    reason-required
                    confirmation-model="backupDownloadConfirmation"
                    confirmation-text="BACKUP"
                    confirmation-label="ui.confirmations.confirmation_text.label"
                    confirmation-help="ui.confirmations.typed_confirmation_help"
                >
                    <x-slot:trigger>
                        <flux:button icon="arrow-down-tray" variant="primary" type="button">
                            {{ __('ui.superadmin.dashboard.download_sqlite') }}
                        </flux:button>
                    </x-slot:trigger>
                </x-dangerous-action-confirmation>

                <flux:button icon="archive-box" disabled>
                    {{ __('ui.superadmin.dashboard.media_zip_later') }}
                </flux:button>
            </div>
        </div>
    </section>

    <section class="rounded-lg border border-sky-200 bg-sky-50 p-4 dark:border-sky-800/70 dark:bg-sky-950/30">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <flux:heading size="lg">{{ __('ui.superadmin.dashboard.session_cleanup') }}</flux:heading>
                <p class="mt-2 max-w-3xl text-sm text-sky-900 dark:text-sky-100">
                    {{ __('ui.superadmin.dashboard.scheduler_can_run_this_cleanup_through_cron_if_cron') }}
                </p>

                @if ($cleanupMessage)
                    <p class="mt-3 rounded-lg bg-white/80 px-3 py-2 text-sm font-medium text-sky-900 ring-1 ring-sky-200 dark:bg-sky-950/50 dark:text-sky-100 dark:ring-sky-800">
                        {{ $cleanupMessage }}
                    </p>
                @endif
            </div>

            <flux:button
                icon="arrow-path"
                type="button"
                wire:click="runSessionInactivityCleanup"
                wire:loading.attr="disabled"
                wire:target="runSessionInactivityCleanup"
            >
                <span wire:loading.remove wire:target="runSessionInactivityCleanup">{{ __('ui.organizations.brands.branches.settings.run_cleanup_now') }}</span>
                <span wire:loading wire:target="runSessionInactivityCleanup">{{ __('ui.organizations.brands.branches.settings.running') }}</span>
            </flux:button>
        </div>
    </section>

    <section class="grid gap-6 xl:grid-cols-2">
        <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                <flux:heading size="lg">{{ __('navigation.organizations') }}</flux:heading>
            </div>

            <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @forelse ($organizationRows as $organization)
                    <div wire:key="platform-organization-{{ $organization['id'] }}" class="px-4 py-3">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-medium text-zinc-950 dark:text-white">{{ $organization['name'] }}</p>

                                    @if ($organization['has_subscription'])
                                        <flux:badge :color="$organization['subscription_color']">
                                            {{ $organization['subscription_label'] }}
                                        </flux:badge>
                                    @else
                                        <flux:badge color="amber">{{ __('ui.superadmin.dashboard.subscription_not_initialized') }}</flux:badge>
                                    @endif

                                    @if ($organization['is_active'])
                                        <flux:badge color="green">{{ __('ui.superadmin.dashboard.activity_active') }}</flux:badge>
                                    @else
                                        <flux:badge color="zinc">{{ __('ui.superadmin.dashboard.activity_suspended') }}</flux:badge>
                                    @endif
                                </div>

                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $organization['owner_email'] }}</p>

                                <div class="mt-2 grid gap-2 text-xs text-zinc-500 dark:text-zinc-400 sm:grid-cols-2 xl:grid-cols-4">
                                    <span>
                                        {{ __('ui.superadmin.dashboard.started') }}:
                                        {{ $organization['started_at'] }}
                                    </span>
                                    <span>
                                        {{ __('ui.superadmin.dashboard.next_payment') }}:
                                        {{ $organization['next_payment_at'] }}
                                    </span>
                                    <span>
                                        {{ __('ui.superadmin.dashboard.payment') }}:
                                        <span class="font-medium">{{ $organization['payment_label'] }}</span>
                                    </span>
                                    <span>
                                        {{ __('navigation.branches') }}:
                                        <span class="font-medium">{{ $organization['branches_count'] }}</span>
                                        <span class="text-zinc-400 dark:text-zinc-500">({{ __('ui.superadmin.dashboard.active') }} {{ $organization['active_branches_count'] }})</span>
                                    </span>
                                    <span>
                                        {{ __('navigation.service_points') }}:
                                        <span class="font-medium">{{ $organization['service_points_count'] }}</span>
                                    </span>
                                    <span>
                                        {{ __('navigation.orders') }}:
                                        <span class="font-medium">{{ $organization['orders_count'] }}</span>
                                    </span>
                                    <span>
                                        {{ __('navigation.brands') }}:
                                        <span class="font-medium">{{ $organization['brands_count'] }}</span>
                                    </span>
                                </div>
                            </div>

                            <div class="flex shrink-0 flex-wrap gap-2">
                                <flux:button
                                    icon="building-storefront"
                                    type="button"
                                    size="sm"
                                    :href="$organization['details_url']"
                                    wire:navigate
                                >
                                    {{ __('ui.superadmin.dashboard.open_details') }}
                                </flux:button>

                                <flux:button
                                    icon="shield-check"
                                    type="button"
                                    size="sm"
                                    :href="$organization['audit_url']"
                                    wire:navigate
                                >
                                    {{ __('navigation.audit_log') }}
                                </flux:button>

                                @if ($organization['is_active'])
                                    <x-dangerous-action-confirmation
                                        name="suspend-organization-{{ $organization['id'] }}"
                                        action="suspend_organization"
                                        confirm-action="suspendOrganization({{ $organization['id'] }})"
                                        submit-target="suspendOrganization({{ $organization['id'] }})"
                                        confirm-label="ui.actions.confirm"
                                        reason-model="organizationSuspendReason"
                                        reason-label="ui.confirmations.reason.label"
                                        reason-placeholder="ui.confirmations.reason.placeholder"
                                    >
                                        <x-slot:trigger>
                                            <flux:button
                                                icon="pause"
                                                type="button"
                                                size="sm"
                                                variant="danger"
                                            >
                                                {{ __('ui.actions.suspend') }}
                                            </flux:button>
                                        </x-slot:trigger>
                                    </x-dangerous-action-confirmation>
                                @else
                                    <flux:button
                                        icon="play"
                                        type="button"
                                        size="sm"
                                        variant="primary"
                                        wire:click="activateOrganization({{ $organization['id'] }})"
                                        wire:loading.attr="disabled"
                                        wire:target="activateOrganization({{ $organization['id'] }})"
                                    >
                                        {{ __('ui.superadmin.dashboard.activate') }}
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-6 text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.empty.no_organizations') }}</div>
                @endforelse
            </div>

            @if ($organizationPaginator->hasPages())
                <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">
                    {{ $organizationPaginator->links() }}
                </div>
            @endif
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                <flux:heading size="lg">{{ __('navigation.brands') }}</flux:heading>
            </div>

            <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @forelse ($brandRows as $brand)
                    <div wire:key="platform-brand-{{ $brand['id'] }}" class="px-4 py-3">
                        <p class="font-medium text-zinc-950 dark:text-white">{{ $brand['name'] }}</p>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $brand['organization_name'] }}</p>
                    </div>
                @empty
                    <div class="px-4 py-6 text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.empty.no_brands') }}</div>
                @endforelse
            </div>

            @if ($brandPaginator->hasPages())
                <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">
                    {{ $brandPaginator->links() }}
                </div>
            @endif
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                <flux:heading size="lg">{{ __('navigation.branches') }}</flux:heading>
            </div>

            <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @forelse ($branchRows as $branch)
                    <div wire:key="platform-branch-{{ $branch['id'] }}" class="px-4 py-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium text-zinc-950 dark:text-white">{{ $branch['name'] }}</p>

                            @if ($branch['is_active'])
                                <flux:badge color="green">{{ __('qr.status.active') }}</flux:badge>
                            @else
                                <flux:badge color="zinc">{{ __('staff.statuses.suspended') }}</flux:badge>
                            @endif
                        </div>

                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $branch['organization_name'] }} / {{ $branch['brand_name'] }}
                        </p>

                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $branch['location'] }}</p>
                    </div>
                @empty
                    <div class="px-4 py-6 text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.empty.no_branches') }}</div>
                @endforelse
            </div>

            @if ($branchPaginator->hasPages())
                <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">
                    {{ $branchPaginator->links() }}
                </div>
            @endif
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                <flux:heading size="lg">{{ __('ui.superadmin.dashboard.users') }}</flux:heading>
            </div>

            <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @forelse ($userRows as $user)
                    <div wire:key="platform-user-{{ $user['id'] }}" class="px-4 py-3">
                        <p class="font-medium text-zinc-950 dark:text-white">{{ $user['name'] }}</p>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $user['email'] }}</p>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $user['roles'] }}
                        </p>
                    </div>
                @empty
                    <div class="px-4 py-6 text-sm text-zinc-500 dark:text-zinc-400">{{ __('staff.empty.no_staff') }}</div>
                @endforelse
            </div>

            @if ($userPaginator->hasPages())
                <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">
                    {{ $userPaginator->links() }}
                </div>
            @endif
        </div>
    </section>
</section>
