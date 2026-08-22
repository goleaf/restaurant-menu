<section data-page="staff-permissions" class="flex h-full w-full flex-1 flex-col gap-6">
    <header class="flex flex-col gap-3">
        <flux:button icon="arrow-left" :href="route('organizations.staff.index', $organization)" wire:navigate>
            {{ __('staff.organization_access') }}
        </flux:button>

        <div class="flex flex-col gap-1">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $organizationName }}</p>
            <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">{{ __('staff.actions.update_permissions') }}</h1>
        </div>
    </header>

    <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="grid gap-4 md:grid-cols-[1fr_auto] md:items-start">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="truncate text-lg font-semibold text-zinc-950 dark:text-white">{{ $staffMemberName }}</h2>
                    <flux:badge>{{ $membershipRoleName }}</flux:badge>
                    <flux:badge color="{{ $membershipStatus === 'active' ? 'green' : 'zinc' }}">
                        {{ $membershipStatusLabel }}
                    </flux:badge>
                </div>

                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $staffMemberEmail }}</p>
            </div>

            @if ($superadminTarget)
                <flux:badge color="green">{{ __('permissions.messages.superadmin_access') }}</flux:badge>
            @endif
        </div>
    </div>

    @if ($selfEditBlocked)
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
            {{ __('permissions.messages.self_edit_disabled') }}
        </div>
    @endif

    @if ($superadminTarget)
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-100">
            {{ __('permissions.messages.superadmin_full_access') }}
        </div>
    @endif

    @if ($lastCriticalWarning)
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
            {{ $lastCriticalWarning }}
        </div>
    @endif

    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
            <flux:heading size="lg">{{ __('staff.actions.update_permissions') }}</flux:heading>
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
            @forelse ($permissionGroups as $group)
                <section wire:key="permission-group-{{ $group['key'] }}">
                    <div class="flex items-center justify-between gap-3 bg-zinc-50 px-4 py-3 dark:bg-zinc-950/40">
                        <flux:heading size="md">{{ $group['label'] }}</flux:heading>
                        <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ count($group['permissions']) }}</span>
                    </div>

                    <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($group['permissions'] as $row)
                            <div wire:key="permission-override-{{ $row['id'] }}" class="grid gap-4 px-4 py-4 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-center">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="text-base font-semibold text-zinc-950 dark:text-white">{{ $row['label'] }}</h2>

                                        @if ($row['is_critical'])
                                            <flux:badge color="amber">{{ __('permissions.labels.critical_permission') }}</flux:badge>
                                        @endif

                                        <flux:badge color="{{ $row['effective_allowed'] ? 'green' : 'red' }}">
                                            {{ $row['effective_label'] }}
                                        </flux:badge>
                                    </div>

                                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $row['description'] }}</p>

                                    <div class="mt-2 flex flex-wrap gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                                        @if ($showTechnicalPermissionKeys)
                                            <span class="font-mono text-xs">{{ $row['code'] }}</span>
                                        @endif

                                        <span>{{ $row['role_default_label'] }}</span>
                                        <span>{{ $row['override_label'] }}</span>
                                    </div>
                                </div>

                                <div class="flex justify-start xl:justify-end">
                                    @if ($row['is_critical'])
                                        <div class="flex flex-wrap gap-2">
                                            @foreach (['default' => __('permissions.actions.default'), 'allow' => __('permissions.actions.allow'), 'deny' => __('permissions.actions.deny')] as $stateValue => $stateLabel)
                                                <x-dangerous-action-confirmation
                                                    name="critical-permission-{{ $row['id'] }}-{{ $stateValue }}"
                                                    action="change_critical_permission"
                                                    confirm-action="setPermissionState({{ $row['id'] }}, '{{ $stateValue }}')"
                                                    submit-target="setPermissionState({{ $row['id'] }}, '{{ $stateValue }}')"
                                                    confirm-label="ui.actions.confirm"
                                                    reason-model="criticalPermissionChangeReason"
                                                    :reason-label="__('permissions.forms.change_reason')"
                                                    :reason-placeholder="__('permissions.forms.critical_reason_placeholder')"
                                                >
                                                    <x-slot:trigger>
                                                        <flux:button
                                                            type="button"
                                                            size="sm"
                                                            variant="{{ $stateValue === 'deny' ? 'danger' : 'outline' }}"
                                                            :disabled="$selfEditBlocked || $superadminTarget"
                                                        >
                                                            {{ $stateLabel }}
                                                        </flux:button>
                                                    </x-slot:trigger>
                                                </x-dangerous-action-confirmation>
                                            @endforeach
                                        </div>
                                    @else
                                        <flux:radio.group variant="segmented" size="sm">
                                            <flux:radio value="default" :checked="$row['override_state'] === 'default'" :disabled="$selfEditBlocked || $superadminTarget" wire:click="setPermissionState({{ $row['id'] }}, 'default')">
                                                {{ __('permissions.actions.default') }}
                                            </flux:radio>

                                            <flux:radio value="allow" :checked="$row['override_state'] === 'allow'" :disabled="$selfEditBlocked || $superadminTarget" wire:click="setPermissionState({{ $row['id'] }}, 'allow')">
                                                {{ __('permissions.actions.allow') }}
                                            </flux:radio>

                                            <flux:radio value="deny" :checked="$row['override_state'] === 'deny'" :disabled="$selfEditBlocked || $superadminTarget" wire:click="setPermissionState({{ $row['id'] }}, 'deny')">
                                                {{ __('permissions.actions.deny') }}
                                            </flux:radio>
                                        </flux:radio.group>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="px-4 py-4 text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.empty.no_permissions') }}</p>
                        @endforelse
                    </div>
                </section>
            @empty
                <p class="px-4 py-4 text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.empty.no_permissions') }}</p>
            @endforelse
        </div>
    </div>
</section>
