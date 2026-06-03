<section data-page="staff-permissions" class="flex h-full w-full flex-1 flex-col gap-6">
    <header class="flex flex-col gap-3">
        <flux:button icon="arrow-left" :href="route('organizations.staff.index', $organization)" wire:navigate>
            {{ __('Organization staff') }}
        </flux:button>

        <div class="flex flex-col gap-1">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $organization->name }}</p>
            <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">{{ __('Permission overrides') }}</h1>
        </div>
    </header>

    <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="grid gap-4 md:grid-cols-[1fr_auto] md:items-start">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="truncate text-lg font-semibold text-zinc-950 dark:text-white">{{ $staffMember->name }}</h2>
                    <flux:badge>{{ $membershipRoleName }}</flux:badge>
                    <flux:badge color="{{ $membershipStatus === 'active' ? 'green' : 'zinc' }}">
                        {{ __(str($membershipStatus)->headline()->toString()) }}
                    </flux:badge>
                </div>

                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $staffMember->email }}</p>
            </div>

            @if ($superadminTarget)
                <flux:badge color="green">{{ __('Superadmin access') }}</flux:badge>
            @endif
        </div>
    </div>

    @if ($selfEditBlocked)
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
            {{ __('Self-edit is disabled. Ask another manager to change your permissions.') }}
        </div>
    @endif

    @if ($superadminTarget)
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-100">
            {{ __('Superadmin always has full access.') }}
        </div>
    @endif

    @if ($lastCriticalWarning)
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
            {{ $lastCriticalWarning }}
        </div>
    @endif

    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
            <flux:heading size="lg">{{ __('Permissions') }}</flux:heading>
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
            @foreach ($this->permissionRows as $row)
                <div wire:key="permission-override-{{ $row['id'] }}" class="grid gap-4 px-4 py-4 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-center">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="truncate text-base font-semibold text-zinc-950 dark:text-white">{{ $row['name'] }}</h2>

                            @if ($row['is_critical'])
                                <flux:badge color="amber">{{ __('Critical permission') }}</flux:badge>
                            @endif

                            <flux:badge color="{{ $row['effective_allowed'] ? 'green' : 'red' }}">
                                {{ $row['effective_label'] }}
                            </flux:badge>
                        </div>

                        <div class="mt-2 flex flex-wrap gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                            <span>{{ $row['code'] }}</span>
                            <span>{{ $row['role_default_label'] }}</span>
                            <span>{{ $row['override_label'] }}</span>
                        </div>
                    </div>

                    <div class="flex justify-start xl:justify-end">
                        <flux:radio.group variant="segmented" size="sm">
                            <flux:radio value="default" :checked="$row['override_state'] === 'default'" :disabled="$selfEditBlocked || $superadminTarget" wire:click="setPermissionState({{ $row['id'] }}, 'default')">
                                {{ __('Default') }}
                            </flux:radio>

                            <flux:radio value="allow" :checked="$row['override_state'] === 'allow'" :disabled="$selfEditBlocked || $superadminTarget" wire:click="setPermissionState({{ $row['id'] }}, 'allow')">
                                {{ __('Allow') }}
                            </flux:radio>

                            <flux:radio value="deny" :checked="$row['override_state'] === 'deny'" :disabled="$selfEditBlocked || $superadminTarget" wire:click="setPermissionState({{ $row['id'] }}, 'deny')">
                                {{ __('Deny') }}
                            </flux:radio>
                        </flux:radio.group>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
