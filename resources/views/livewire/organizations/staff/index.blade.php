@pushOnce('page-module-status', 'staff-status')
    <x-page-module-status module="staff" />
@endPushOnce

<section data-page-module="staff" wire:ignore.self x-ignore inert data-staff-workspace data-page="{{ $isBranch ? 'branch-staff' : 'organization-staff' }}" x-data="staffWorkspace" class="flex w-full min-w-0 flex-col gap-5">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            @unless ($isBranch)<p class="text-sm text-text-muted">{{ $contextLabel }}</p>@endunless
            <h1 data-staff-heading tabindex="-1" class="mt-1 text-2xl font-semibold text-text-primary">{{ $isBranch ? __('staff.branch_access') : __('staff.organization_access') }}</h1>
            <p class="mt-2 text-sm text-text-muted">{{ $isBranch ? __('team.card.branch_scope') : __('staff.workspace.organization_scope') }}</p>
            @if ($isBranch && $activeSection === 'employees')
                <p class="mt-2 text-sm text-text-muted">{{ __('team.card.branch_list_scope') }}</p>
            @endif
        </div>
        @if ($canManageStaff)
        <flux:button variant="primary" icon="user-plus" wire:click="openInvitation" wire:loading.attr="disabled" wire:target="openInvitation" x-bind:disabled="!online" class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2">{{ __('team.card.add') }}</flux:button>
        @endif
    </header>

    <nav class="flex flex-wrap gap-2 border-b border-border-subtle pb-3" aria-label="{{ __('staff.list') }}">
        <flux:button x-on:click="navigateSection($event, 'employees')" :variant="$activeSection === 'employees' ? 'primary' : 'ghost'" :aria-current="$activeSection === 'employees' ? 'page' : null" class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2">{{ __('staff.workspace.employees') }}</flux:button>
        <flux:button x-on:click="navigateSection($event, 'invitations')" :variant="$activeSection === 'invitations' ? 'primary' : 'ghost'" :aria-current="$activeSection === 'invitations' ? 'page' : null" class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2">{{ __('staff.invitations') }}</flux:button>
        @if ($isBranch)
            <flux:button x-on:click="navigateSection($event, 'assignments')" :variant="$activeSection === 'assignments' ? 'primary' : 'ghost'" :aria-current="$activeSection === 'assignments' ? 'page' : null" class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2">{{ __('staff.workspace.assignments') }}</flux:button>
        @endif
        <flux:button wire:click="refreshWorkspace" wire:loading.attr="disabled" wire:target="refreshWorkspace" x-bind:disabled="!online" icon="arrow-path" class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2">{{ __('staff.workspace.refresh') }}</flux:button>
    </nav>

    <div role="status" aria-live="polite" aria-atomic="true" @class(['hidden' => $successMessage === ''])>{{ $successMessage }}</div>
    @if ($errorMessage !== '')
        <p role="alert" class="rounded-control border border-warning-border bg-warning-surface p-3 text-warning">{{ $errorMessage }}</p>
    @endif
    <div wire:offline class="rounded-control border border-warning-border bg-warning-surface p-3 text-warning">{{ __('ui.connectivity.offline') }}</div>
    @if ($editor === '' && $errors->any())
        <div role="alert" class="rounded-control border border-danger-border bg-danger-surface p-3 text-sm text-danger">
            @forelse ($errors->all() as $error)
                <p>{{ $error }}</p>
            @empty
            @endforelse
        </div>
    @endif
    @if ($createdInvitationLink !== null)
        <x-staff.invitation-link :value="$createdInvitationLink" />
    @endif

    <div @class(['grid min-w-0 gap-5', 'lg:grid-cols-2 lg:items-start' => $editor !== ''])>
    @if ($editor !== '')
        <x-staff.editor :heading="$selectedMember['user_name'] ?? ($editor === 'invite' ? __('staff.invite') : __('staff.workspace.edit_member'))">
            @if ($errors->any())
                <div role="alert" class="my-3 rounded-control border border-danger-border bg-danger-surface p-3 text-sm text-danger" tabindex="-1">
                    @forelse ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @empty
                    @endforelse
                </div>
            @endif
            @if ($editor === 'invite')
                @if ($isBranch)
                    <flux:button wire:click="openExistingAssignment" x-bind:disabled="!online">{{ __('staff.workspace.existing_colleague') }}</flux:button>
                @endif
                <form wire:submit="previewInvitation" novalidate class="mt-4 grid min-w-0 gap-4 sm:grid-cols-2">
                    <flux:input wire:model="invitationForm.email" name="invitationForm.email" :label="__('ui.auth.reset_password.email')" type="email" autocomplete="email" maxlength="255" required />
                    <flux:input wire:model="invitationForm.phone" name="invitationForm.phone" :label="__('ui.organizations.brands.branches.settings.phone')" type="tel" autocomplete="tel" maxlength="40" />
                    <flux:select wire:model="invitationForm.roleId" name="invitationForm.roleId" :label="__('staff.role')">
                        @forelse ($roleOptions as $role)
                            <option value="{{ $role['id'] }}" wire:key="invite-role-{{ $role['id'] }}">{{ $role['label'] }}</option>
                        @empty
                        @endforelse
                    </flux:select>
                    <flux:input wire:model="invitationForm.expiresInDays" name="invitationForm.expiresInDays" :label="__('staff.fields.invitation_expiry_days')" type="number" min="1" max="30" />
                    <p class="text-sm text-text-muted sm:col-span-2">{{ __('staff.workspace.preview_help') }} {{ $contextLabel }}</p>
                    <flux:button type="submit" wire:loading.attr="disabled" x-bind:disabled="!online" class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2">{{ __('staff.workspace.preview') }}</flux:button>
                </form>
                @if ($preview !== [])
                    <div class="mt-4 space-y-2 border-t border-border-subtle pt-4">
                        <p class="font-medium">{{ $preview['email'] }} · {{ $preview['role'] }}</p>
                        <p class="text-sm text-text-muted">{{ $preview['scope'] }} · {{ $preview['expires'] }}</p>
                        <h3 class="text-sm font-semibold">{{ __('staff.workspace.role_defaults') }}</h3>
                        <p class="text-sm text-text-muted">{{ __('staff.workspace.role_defaults_help') }}</p>
                        <ul class="list-inside list-disc text-sm">
                            @forelse ($preview['role_defaults'] as $capability)
                                <li>{{ $capability }}</li>
                            @empty
                                <li>{{ __('staff.workspace.no_role_defaults') }}</li>
                            @endforelse
                        </ul>
                        <flux:button variant="primary" wire:click="createInviteLink" wire:loading.attr="disabled" wire:target="createInviteLink" x-bind:disabled="!online" class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2">{{ __('staff.workspace.confirm_create') }}</flux:button>
                    </div>
                @endif
            @elseif ($editor === 'assign')
                <p>{{ __('staff.workspace.existing_help') }}</p>
                <flux:input wire:model.live.debounce.300ms="assignmentForm.search" :label="__('staff.workspace.search')" type="search" maxlength="120" />
                <ul class="rm-team-card__branches">
                    @forelse ($candidateRows as $candidate)
                        <li wire:key="candidate-{{ $candidate['id'] }}"><flux:button wire:click="selectExistingMember({{ $candidate['id'] }})" x-bind:disabled="!online">{{ $candidate['label'] }}</flux:button></li>
                    @empty <li>{{ __('staff.workspace.no_results') }}</li> @endforelse
                </ul>
            @elseif ($editor === 'reissue' || $editor === 'cancel')
                <p class="mt-3">{{ $preview['email'] }} · {{ $preview['role'] }}</p>
                <p class="mt-2 text-sm text-text-muted">{{ $preview['scope'] }}</p>
                @if ($editor === 'reissue')
                    <p class="mt-3 rounded-control border border-warning-border bg-warning-surface p-3 text-sm text-warning">{{ __('staff.workspace.reissue_warning') }}</p>
                    <flux:button class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2 mt-3" wire:click="reissueInvitation({{ $confirmInvitationId }})" wire:loading.attr="disabled" x-bind:disabled="!online">{{ __('staff.actions.reissue_invitation') }}</flux:button>
                @else
                    <flux:button class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2 mt-3" wire:click="cancelInvitation({{ $confirmInvitationId }})" wire:loading.attr="disabled" x-bind:disabled="!online">{{ __('staff.actions.cancel_invitation') }}</flux:button>
                @endif
            @endif
        </x-staff.editor>
    @endif

    <section aria-label="{{ $activeSection === 'invitations' ? __('staff.invitations') : __('staff.list') }}" @class(['min-w-0', 'lg:order-first' => $editor !== ''])>
        <div class="min-w-0">
            <flux:input wire:model.live.debounce.300ms="filters.search" name="filters.search" :label="__('staff.workspace.search')" type="search" maxlength="120" />
            <details data-staff-filters class="mt-3" wire:ignore.self>
                <summary class="min-h-touch cursor-pointer py-2 text-sm font-medium">{{ __('menu.guest.filters') }} @if ($hasActiveFilters) · {{ __('staff.workspace.filters_active') }} @endif</summary>
                <div class="grid gap-3 sm:grid-cols-3">
            <flux:select wire:model.live="filters.role" name="filters.role" :label="$isBranch && $activeSection === 'employees' ? __('team.card.organization_role') : __('staff.role')">
                <option value="">{{ __('staff.workspace.all_roles') }}</option>
                @forelse ($filterRoleOptions as $role)
                    <option value="{{ $role['value'] }}" wire:key="filter-role-{{ $role['value'] }}">{{ $role['label'] }}</option>
                @empty
                @endforelse
            </flux:select>
            <flux:select wire:model.live="filters.status" name="filters.status" :label="$isBranch && $activeSection === 'employees' ? __('team.card.organization_status') : __('staff.workspace.status')">
                <option value="">{{ __('staff.workspace.all_statuses') }}</option>
                @forelse ($statusLabels as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @empty
                @endforelse
            </flux:select>
            <flux:select wire:model.live="filters.sort" name="filters.sort" :label="__('staff.workspace.sort')">
                <option value="newest">{{ __('staff.workspace.newest') }}</option>
                <option value="oldest">{{ __('staff.workspace.oldest') }}</option>
                <option value="name">{{ __('staff.workspace.name') }}</option>
            </flux:select>
                </div>
            </details>
        </div>
        <div class="my-3 flex flex-wrap gap-2">
            <flux:button wire:click="resetFilters" class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2">{{ __('staff.workspace.reset_filters') }}</flux:button>
            @if ($isBranch && $activeSection === 'employees')
                <flux:button wire:click="openExistingAssignment" x-bind:disabled="!online" class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2">{{ __('staff.workspace.assign_existing') }}</flux:button>
            @endif
        </div>
        @if ($coverageOverview !== null)
            <section aria-labelledby="staff-coverage-heading" class="my-4 rounded-control border border-border-subtle p-3">
                <h2 id="staff-coverage-heading" class="font-semibold">{{ __('staff.workspace.area_coverage') }}</h2>
                <p class="mt-2 text-sm text-text-muted">{{ __('staff.workspace.unrestricted_waiters', ['count' => $coverageOverview['unrestricted_count']]) }}</p>
                <ul class="mt-1 text-sm">
                    @forelse ($coverageOverview['unrestricted_members'] as $person)
                        <li wire:key="coverage-unrestricted-{{ $person['id'] }}"><flux:link :href="$person['url']" wire:navigate>{{ $person['name'] }}</flux:link></li>
                    @empty
                    @endforelse
                </ul>
                <div class="mt-3 divide-y divide-border-subtle">
                    @forelse ($coverageOverview['rows'] as $area)
                        <article wire:key="coverage-{{ $area['id'] }}" class="py-3">
                            <h3 class="font-medium">{{ $area['name'] }}</h3>
                            <p class="text-sm">{{ __('staff.workspace.area_waiters', ['count' => $area['assigned_count']]) }}</p>
                            @if ($area['assigned_count'] === 0)<flux:badge>{{ __('team.card.uncovered') }}</flux:badge>@endif
                            <ul class="text-sm text-text-muted">
                                @forelse ($area['waiter_members'] as $person)
                                    <li wire:key="coverage-member-{{ $area['id'] }}-{{ $person['id'] }}"><flux:link :href="$person['url']" wire:navigate>{{ $person['name'] }}</flux:link></li>
                                @empty
                                @endforelse
                            </ul>
                        </article>
                    @empty
                        <p class="py-3 text-sm text-text-muted">{{ __('staff.workspace.no_results') }}</p>
                    @endforelse
                </div>
                {{ $coverageOverview['paginator']->links() }}
            </section>
        @endif
        @if ($activeSection === 'invitations')
            <p>{{ $isBranch ? __('team.card.branch_invitations') : __('team.card.organization_invitations') }}</p>
            <section class="my-4" aria-label="{{ __('staff.workspace.invitation_summary') }}">
                <h2 class="text-sm text-text-muted">{{ __('staff.workspace.invitation_summary') }}</h2>
                <dl class="mt-2 flex flex-wrap gap-x-4 gap-y-2 text-sm">
                    @forelse ($invitationSummary as $summary)
                        <div wire:key="invitation-summary-{{ $summary['status'] }}" class="flex gap-1">
                            <dt>{{ $summary['label'] }}</dt><dd class="font-semibold">{{ $summary['count'] }}</dd>
                        </div>
                    @empty
                    @endforelse
                </dl>
            </section>
            <div class="divide-y divide-border-subtle border-y border-border-subtle">
                @forelse ($invitationRows as $invitation)
                    <article wire:key="invitation-{{ $invitation['id'] }}" class="flex min-w-0 flex-wrap items-start justify-between gap-3 py-4">
                        <div class="min-w-0 flex-1 basis-64">
                            <h3 class="break-words font-semibold">{{ $invitation['email'] }}</h3>
                            <p class="text-sm">{{ $invitation['scope'] }}</p>
                            <p class="mt-1 text-sm">{{ $invitation['role_label'] }} · {{ $invitation['localized_status'] }}</p>
                            <p class="mt-1 text-sm text-text-muted">{{ __('staff.invitation_meta.created', ['name' => $invitation['created_by'], 'date' => $invitation['created_at']]) }}</p>
                            <p class="text-sm text-text-muted">{{ __('staff.invitation_meta.expires', ['date' => $invitation['expires_at']]) }}</p>
                            @if ($invitation['accepted_at'] !== null)
                                <p class="text-sm text-text-muted">{{ __('staff.invitation_meta.accepted', ['name' => $invitation['accepted_by'], 'date' => $invitation['accepted_at']]) }}</p>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if ($invitation['card_url'])<flux:button :href="$invitation['card_url']" wire:navigate>{{ __('team.card.open') }}</flux:button>@endif
                            @if ($invitation['can_reissue'] && $canManageStaff)
                                <flux:button wire:click="confirmInvitation({{ $invitation['id'] }}, 'reissue')" x-bind:disabled="!online" class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2">{{ __('staff.actions.reissue_invitation') }}</flux:button>
                            @endif
                            @if ($invitation['can_cancel'] && $canManageStaff)
                                <flux:button wire:click="confirmInvitation({{ $invitation['id'] }}, 'cancel')" x-bind:disabled="!online" class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2">{{ __('staff.actions.cancel_invitation') }}</flux:button>
                            @endif
                        </div>
                    </article>
                @empty
                    <p class="py-6 text-text-muted">{{ __('staff.workspace.invitation_empty') }}</p>
                @endforelse
            </div>
            <div class="mt-4">{{ $invitationsPaginator->links() }}</div>
        @else
            @if ($activeSection === 'assignments')
                <p class="my-3 text-sm text-text-muted">{{ __('team.card.assignment_roster_scope') }}</p>
            @endif
            <div class="divide-y divide-border-subtle border-y border-border-subtle">
                @forelse ($memberRows as $member)
                    <article wire:key="member-{{ $member['id'] }}" class="flex min-w-0 flex-wrap items-start justify-between gap-3 py-4">
                        <div class="min-w-0 flex-1 basis-64">
                            <h3 class="break-words font-semibold">{{ $member['user_name'] }}</h3>
                            <p class="break-words text-sm text-text-muted">{{ $member['user_email'] }}</p>
                            <p class="mt-1 text-sm">{{ $member['role_label'] }} · {{ $member['localized_status'] }}</p>
                            @if ($member['coverage'] !== null)
                                <p class="mt-1 text-sm text-text-muted">{{ $member['coverage'] }}</p>
                            @endif
                        </div>
                        @if ($member['card_url'] !== null)
                            <flux:button :href="$member['card_url']" wire:navigate>{{ __('team.card.open') }}</flux:button>
                        @endif
                    </article>
                @empty
                    <p class="py-6 text-text-muted">{{ __('staff.workspace.employees_empty') }}</p>
                @endforelse
            </div>
            <div class="mt-4">{{ $membersPaginator->links() }}</div>
        @endif
    </section>
    </div>
    <x-staff.unsaved-dialog />
</section>
