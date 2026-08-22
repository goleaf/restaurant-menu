<section data-page="branch-staff" class="flex h-full w-full flex-1 flex-col gap-6">
    <header class="flex flex-col gap-3">
        <flux:button icon="arrow-left" :href="route('organizations.brands.branches.index', [$organization, $brand])" wire:navigate>
            {{ __('navigation.branches') }}
        </flux:button>

        <div class="flex flex-col gap-1">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $contextLabel }}</p>
            <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">{{ __('staff.branch_access') }}</h1>
        </div>
    </header>

    <div class="grid gap-4 xl:grid-cols-2">
        <form wire:submit="addManualStaffMember" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex flex-col gap-4">
                <flux:heading size="lg">{{ __('staff.create_manual') }}</flux:heading>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="manualName" :label="__('reports.csv.name')" type="text" required maxlength="120" />
                    <flux:input wire:model="manualEmail" :label="__('ui.auth.reset_password.email')" type="email" required maxlength="255" />

                    <flux:select wire:model="manualRoleId" :label="__('staff.role')" required>
                        @foreach ($roleOptions as $role)
                            <flux:select.option wire:key="branch-manual-role-{{ $role['id'] }}" value="{{ $role['id'] }}">
                                {{ $role['label'] }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="flex justify-end">
                    <flux:button icon="user-plus" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="addManualStaffMember">
                        {{ __('staff.add') }}
                    </flux:button>
                </div>
            </div>
        </form>

        <form wire:submit="createInviteLink" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex flex-col gap-4">
                <flux:heading size="lg">{{ __('staff.invite') }}</flux:heading>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="inviteEmail" :label="__('ui.auth.reset_password.email')" type="email" maxlength="255" />
                    <flux:input wire:model="invitePhone" :label="__('ui.organizations.brands.branches.settings.phone')" type="text" maxlength="40" />

                    <flux:select wire:model="inviteRoleId" :label="__('staff.role')" required>
                        @foreach ($roleOptions as $role)
                            <flux:select.option wire:key="branch-invite-role-{{ $role['id'] }}" value="{{ $role['id'] }}">
                                {{ $role['label'] }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="flex flex-wrap justify-end gap-2">
                    <flux:button icon="link" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createInviteLink">
                        {{ __('staff.invite_link') }}
                    </flux:button>

                    <flux:button icon="key" type="button" wire:click="createInviteCode" wire:loading.attr="disabled" wire:target="createInviteCode">
                        {{ __('staff.invite_code') }}
                    </flux:button>
                </div>

                @if ($lastInviteLink || $lastInviteCode)
                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                        @if ($lastInviteLink)
                            <p class="font-medium text-zinc-950 dark:text-white">{{ __('staff.invite_link') }}</p>
                            <p class="mt-1 break-all text-zinc-600 dark:text-zinc-300">{{ $lastInviteLink }}</p>
                        @endif

                        @if ($lastInviteCode)
                            <p class="mt-3 font-medium text-zinc-950 dark:text-white">{{ __('staff.invite_code') }}</p>
                            <p class="mt-1 break-all text-zinc-600 dark:text-zinc-300">{{ $lastInviteCode }}</p>
                        @endif
                    </div>
                @endif
            </div>
        </form>
    </div>

    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
            <flux:heading size="lg">{{ __('staff.list') }}</flux:heading>
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
            @forelse ($memberRows as $member)
                <div wire:key="branch-staff-{{ $member['id'] }}" class="grid gap-4 px-4 py-4 md:grid-cols-[1fr_auto] md:items-center">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="truncate text-base font-semibold text-zinc-950 dark:text-white">{{ $member['user_name'] }}</h2>

                            @if ($member['is_active'])
                                <flux:badge color="green">{{ $member['localized_status'] }}</flux:badge>
                            @else
                                <flux:badge color="zinc">{{ $member['localized_status'] }}</flux:badge>
                            @endif
                        </div>

                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $member['user_email'] }}</p>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $member['role_label'] }}</p>
                    </div>

                    <div class="flex flex-wrap gap-2 md:justify-end">
                        <flux:button icon="shield-check" type="button" :href="route('organizations.staff.permissions', [$organization, $member['user_id']])" wire:navigate>
                            {{ __('staff.actions.update_permissions') }}
                        </flux:button>

                        @if ($member['is_active'])
                            <x-dangerous-action-confirmation
                                name="deactivate-branch-staff-{{ $member['id'] }}"
                                action="deactivate_staff"
                                confirm-action="deactivateMember({{ $member['id'] }})"
                                submit-target="deactivateMember({{ $member['id'] }})"
                                confirm-label="ui.actions.confirm"
                                reason-model="staffDeactivationReason"
                                :reason-label="__('staff.forms.deactivation_reason')"
                                :reason-placeholder="__('staff.forms.deactivation_reason_branch_placeholder')"
                            >
                                <x-slot:trigger>
                                    <flux:button icon="pause" type="button">
                                        {{ __('staff.deactivate') }}
                                    </flux:button>
                                </x-slot:trigger>
                            </x-dangerous-action-confirmation>
                        @else
                            <flux:button icon="play" variant="primary" type="button" wire:click="activateMember({{ $member['id'] }})">
                                {{ __('staff.reactivate') }}
                            </flux:button>
                        @endif
                    </div>

                    @if ($member['is_waiter'])
                        <form
                            wire:key="branch-staff-zones-{{ $member['user_id'] }}"
                            wire:submit="saveAreaAssignments({{ $member['user_id'] }})"
                            class="md:col-span-2"
                        >
                            <div class="rounded-md bg-zinc-50 p-3 ring-1 ring-zinc-200 dark:bg-zinc-950/40 dark:ring-zinc-800">
                                <div class="flex flex-col gap-1">
                                    <p class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('staff.waiter_zones') }}</p>
                                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ __('staff.waiter_zones_hint') }}
                                    </p>
                                </div>

                                <div class="mt-3 grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                                    @forelse ($areaNodeOptions as $areaNode)
                                        <label wire:key="branch-staff-zone-option-{{ $member['user_id'] }}-{{ $areaNode['id'] }}" class="flex items-center gap-2 rounded-md bg-white px-3 py-2 text-sm text-zinc-700 ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-zinc-200 dark:ring-zinc-800">
                                            <input
                                                type="checkbox"
                                                class="h-4 w-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500 dark:border-zinc-700"
                                                wire:model="areaAssignments.{{ $member['user_id'] }}"
                                                value="{{ $areaNode['id'] }}"
                                            >
                                            <span>{{ $areaNode['name'] }}</span>
                                        </label>
                                    @empty
                                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.empty.no_areas') }}</p>
                                    @endforelse
                                </div>

                                @error('areaAssignments.'.$member['user_id'])
                                    <p class="mt-3 text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror

                                <div class="mt-3 flex justify-end">
                                    <flux:button
                                        icon="map-pin"
                                        type="submit"
                                        wire:loading.attr="disabled"
                                        wire:target="saveAreaAssignments({{ $member['user_id'] }})"
                                    >
                                        {{ __('staff.save_zones') }}
                                    </flux:button>
                                </div>
                            </div>
                        </form>
                    @endif
                </div>
            @empty
                <div class="px-4 py-8 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('staff.empty.no_branch_staff') }}
                </div>
            @endforelse
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
            <flux:heading size="lg">{{ __('staff.branch_invitations') }}</flux:heading>
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
            @forelse ($invitationRows as $invitation)
                <div wire:key="branch-invitation-{{ $invitation['id'] }}" class="px-4 py-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="font-medium text-zinc-950 dark:text-white">{{ $invitation['role_label'] }}</p>
                        <flux:badge>{{ $invitation['localized_status'] }}</flux:badge>
                    </div>

                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $invitation['email'] ?: __('staff.no_email') }} / {{ $invitation['phone'] ?: __('staff.no_phone') }}</p>
                </div>
            @empty
                <div class="px-4 py-8 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('staff.empty.no_branch_invitations') }}
                </div>
            @endforelse
        </div>
    </div>
</section>
