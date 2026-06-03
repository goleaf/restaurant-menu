<section data-page="branch-staff" class="flex h-full w-full flex-1 flex-col gap-6">
    <header class="flex flex-col gap-3">
        <flux:button icon="arrow-left" :href="route('organizations.brands.branches.index', [$organization, $brand])" wire:navigate>
            {{ __('Branches') }}
        </flux:button>

        <div class="flex flex-col gap-1">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $organization->name }} / {{ $brand->name }} / {{ $branch->name }}</p>
            <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">{{ __('Branch staff') }}</h1>
        </div>
    </header>

    <div class="grid gap-4 xl:grid-cols-2">
        <form wire:submit="addManualStaffMember" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex flex-col gap-4">
                <flux:heading size="lg">{{ __('Add branch staff manually') }}</flux:heading>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="manualName" :label="__('Name')" type="text" required maxlength="120" />
                    <flux:input wire:model="manualEmail" :label="__('Email')" type="email" required maxlength="255" />

                    <flux:select wire:model="manualRoleId" :label="__('Role')" required>
                        @foreach ($this->roles as $role)
                            <flux:select.option wire:key="branch-manual-role-{{ $role->id }}" value="{{ $role->id }}">
                                {{ $role->name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="flex justify-end">
                    <flux:button icon="user-plus" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="addManualStaffMember">
                        {{ __('Add staff') }}
                    </flux:button>
                </div>
            </div>
        </form>

        <form wire:submit="createInviteLink" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex flex-col gap-4">
                <flux:heading size="lg">{{ __('Create branch invitation') }}</flux:heading>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="inviteEmail" :label="__('Email')" type="email" maxlength="255" />
                    <flux:input wire:model="invitePhone" :label="__('Phone')" type="text" maxlength="40" />

                    <flux:select wire:model="inviteRoleId" :label="__('Role')" required>
                        @foreach ($this->roles as $role)
                            <flux:select.option wire:key="branch-invite-role-{{ $role->id }}" value="{{ $role->id }}">
                                {{ $role->name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="flex flex-wrap justify-end gap-2">
                    <flux:button icon="link" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createInviteLink">
                        {{ __('Create invite link') }}
                    </flux:button>

                    <flux:button icon="key" type="button" wire:click="createInviteCode" wire:loading.attr="disabled" wire:target="createInviteCode">
                        {{ __('Create invite code') }}
                    </flux:button>
                </div>

                @if ($lastInviteLink || $lastInviteCode)
                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                        @if ($lastInviteLink)
                            <p class="font-medium text-zinc-950 dark:text-white">{{ __('Invite link') }}</p>
                            <p class="mt-1 break-all text-zinc-600 dark:text-zinc-300">{{ $lastInviteLink }}</p>
                        @endif

                        @if ($lastInviteCode)
                            <p class="mt-3 font-medium text-zinc-950 dark:text-white">{{ __('Invite code') }}</p>
                            <p class="mt-1 break-all text-zinc-600 dark:text-zinc-300">{{ $lastInviteCode }}</p>
                        @endif
                    </div>
                @endif
            </div>
        </form>
    </div>

    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
            <flux:heading size="lg">{{ __('Branch staff members') }}</flux:heading>
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
            @forelse ($this->members as $member)
                <div wire:key="branch-staff-{{ $member->id }}" class="grid gap-4 px-4 py-4 md:grid-cols-[1fr_auto] md:items-center">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="truncate text-base font-semibold text-zinc-950 dark:text-white">{{ $member->user->name }}</h2>

                            @if ($member->status->value === 'active')
                                <flux:badge color="green">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge color="zinc">{{ __('Inactive') }}</flux:badge>
                            @endif
                        </div>

                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $member->user->email }}</p>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $member->role->name }}</p>
                    </div>

                    <div class="flex flex-wrap gap-2 md:justify-end">
                        @if ($member->status->value === 'active')
                            <flux:button icon="pause" type="button" wire:click="deactivateMember({{ $member->id }})">
                                {{ __('Deactivate') }}
                            </flux:button>
                        @else
                            <flux:button icon="play" variant="primary" type="button" wire:click="activateMember({{ $member->id }})">
                                {{ __('Activate') }}
                            </flux:button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="px-4 py-8 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('No branch staff members yet.') }}
                </div>
            @endforelse
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
            <flux:heading size="lg">{{ __('Branch invitations') }}</flux:heading>
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
            @forelse ($this->invitations as $invitation)
                <div wire:key="branch-invitation-{{ $invitation->id }}" class="px-4 py-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="font-medium text-zinc-950 dark:text-white">{{ $invitation->role->name }}</p>
                        <flux:badge>{{ __(str($invitation->status->value)->headline()->toString()) }}</flux:badge>
                    </div>

                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $invitation->email ?: __('No email') }} / {{ $invitation->phone ?: __('No phone') }}</p>
                    <p class="mt-2 break-all text-sm text-zinc-600 dark:text-zinc-300">{{ __('Invite link') }}: {{ $invitation->inviteLink() }}</p>
                    <p class="mt-1 break-all text-sm text-zinc-600 dark:text-zinc-300">{{ __('Invite code') }}: {{ $invitation->invite_code }}</p>
                </div>
            @empty
                <div class="px-4 py-8 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('No branch invitations yet.') }}
                </div>
            @endforelse
        </div>
    </div>
</section>
