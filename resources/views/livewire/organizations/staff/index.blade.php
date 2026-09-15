<section data-staff-workspace data-page="{{ $isBranch ? 'branch-staff' : 'organization-staff' }}" x-data="staffWorkspace" class="flex w-full min-w-0 flex-col gap-5">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="text-sm text-text-muted">{{ $contextLabel }}</p>
            <h1 data-staff-heading tabindex="-1" class="mt-1 text-2xl font-semibold text-text-primary">{{ $isBranch ? __('staff.branch_access') : __('staff.organization_access') }}</h1>
            <p class="mt-2 text-sm text-text-muted">{{ $isBranch ? __('staff.workspace.branch_scope') : __('staff.workspace.organization_scope') }}</p>
        </div>
        <x-ui.button variant="primary" icon="user-plus" wire:click="openInvitation" wire:loading.attr="disabled" wire:target="openInvitation" x-bind:disabled="!online">{{ __('staff.invite') }}</x-ui.button>
    </header>

    <nav class="flex flex-wrap gap-2 border-b border-border-subtle pb-3" aria-label="{{ __('staff.list') }}">
        <x-ui.button x-on:click="navigateSection($event, 'employees')" :variant="$activeSection === 'employees' ? 'primary' : 'ghost'" :aria-current="$activeSection === 'employees' ? 'page' : null">{{ __('staff.workspace.employees') }}</x-ui.button>
        <x-ui.button x-on:click="navigateSection($event, 'invitations')" :variant="$activeSection === 'invitations' ? 'primary' : 'ghost'" :aria-current="$activeSection === 'invitations' ? 'page' : null">{{ __('staff.invitations') }}</x-ui.button>
        @if ($isBranch)
            <x-ui.button x-on:click="navigateSection($event, 'assignments')" :variant="$activeSection === 'assignments' ? 'primary' : 'ghost'" :aria-current="$activeSection === 'assignments' ? 'page' : null">{{ __('staff.workspace.assignments') }}</x-ui.button>
        @endif
        <x-ui.button wire:click="refreshWorkspace" wire:loading.attr="disabled" wire:target="refreshWorkspace" x-bind:disabled="!online" icon="arrow-path">{{ __('staff.workspace.refresh') }}</x-ui.button>
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

    @if ($editor !== '')
        <section data-staff-editor tabindex="-1" aria-labelledby="staff-editor-heading" x-on:keydown.escape.stop.prevent="requestNavigation(() => $wire.discardEditor())" class="min-w-0 rounded-card border border-border-strong bg-surface p-4 sm:p-5">
            <div class="flex items-start justify-between gap-3">
                <h2 id="staff-editor-heading" class="text-lg font-semibold text-text-primary">{{ $selectedMember['user_name'] ?? ($editor === 'invite' ? __('staff.invite') : __('staff.workspace.edit_member')) }}</h2>
                <x-ui.button x-on:click="requestNavigation(() => $wire.discardEditor())" icon="x-mark">{{ __('staff.workspace.close') }}</x-ui.button>
            </div>
            @if ($errors->any())
                <div role="alert" class="my-3 rounded-control border border-danger-border bg-danger-surface p-3 text-sm text-danger" tabindex="-1">
                    @forelse ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @empty
                    @endforelse
                </div>
            @endif
            @if ($editor === 'invite')
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
                    <x-ui.button type="submit" wire:loading.attr="disabled" x-bind:disabled="!online">{{ __('staff.workspace.preview') }}</x-ui.button>
                </form>
                @if ($preview !== [])
                    <div class="mt-4 space-y-2 border-t border-border-subtle pt-4">
                        <p class="font-medium">{{ $preview['email'] }} · {{ $preview['role'] }}</p>
                        <p class="text-sm text-text-muted">{{ $preview['scope'] }} · {{ $preview['expires'] }}</p>
                        <x-ui.button variant="primary" wire:click="createInviteLink" wire:loading.attr="disabled" wire:target="createInviteLink" x-bind:disabled="!online">{{ __('staff.workspace.confirm_create') }}</x-ui.button>
                    </div>
                @endif
            @elseif ($editor === 'member')
                <p class="mt-2 text-sm text-text-muted">{{ $selectedMember['user_email'] }} · {{ $contextLabel }}</p>
                <form wire:submit="previewMemberChange" novalidate class="mt-4 grid gap-4 sm:grid-cols-2">
                    @if ($memberOperation === 'role')
                        <flux:select wire:model="memberForm.roleId" name="memberForm.roleId" :label="__('staff.role')">
                            @forelse ($roleOptions as $role)
                                <option value="{{ $role['id'] }}" wire:key="member-role-{{ $role['id'] }}">{{ $role['label'] }}</option>
                            @empty
                            @endforelse
                        </flux:select>
                    @else
                        <flux:select wire:model="memberForm.status" name="memberForm.status" :label="__('staff.workspace.status')">
                            <option value="active">{{ __('staff.statuses.active') }}</option>
                            <option value="suspended">{{ __('staff.statuses.suspended') }}</option>
                        </flux:select>
                    @endif
                    <flux:input wire:model="memberForm.reason" name="memberForm.reason" :label="__('staff.workspace.reason')" maxlength="1000" required />
                    <x-ui.button type="submit" wire:loading.attr="disabled" x-bind:disabled="!online">{{ __('staff.workspace.preview_change') }}</x-ui.button>
                </form>
                @if ($preview !== [])
                    <dl class="mt-4 grid gap-1 border-t border-border-subtle pt-4 text-sm">
                        <dt class="text-text-muted">{{ __('staff.workspace.current') }}</dt><dd>{{ $preview['current'] }}</dd>
                        <dt class="text-text-muted">{{ __('staff.workspace.proposed') }}</dt><dd>{{ $preview['proposed'] }}</dd>
                    </dl>
                    <x-ui.button class="mt-3" variant="primary" wire:click="saveMember" wire:loading.attr="disabled" wire:target="saveMember" x-bind:disabled="!online">{{ __('staff.workspace.confirm_change') }}</x-ui.button>
                @endif
            @elseif ($editor === 'assign')
                <p class="mt-3 text-sm text-text-muted">{{ __('staff.workspace.existing_help') }}</p>
                <form wire:submit="assignExistingMember" novalidate class="mt-4 grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model.live.debounce.300ms="assignmentForm.search" name="assignmentForm.search" :label="__('staff.workspace.search')" type="search" maxlength="120" />
                    <flux:select wire:model="memberForm.organizationMemberId" name="memberForm.organizationMemberId" :label="__('staff.workspace.colleague')">
                        <option value="">{{ __('staff.workspace.colleague') }}</option>
                        @forelse ($candidateRows as $candidate)
                            <option value="{{ $candidate['id'] }}" wire:key="candidate-{{ $candidate['id'] }}">{{ $candidate['label'] }}</option>
                        @empty
                        @endforelse
                    </flux:select>
                    <flux:select wire:model="memberForm.roleId" name="memberForm.roleId" :label="__('staff.role')">
                        <option value="">{{ __('staff.role') }}</option>
                        @forelse ($roleOptions as $role)
                            <option value="{{ $role['id'] }}" wire:key="assign-role-{{ $role['id'] }}">{{ $role['label'] }}</option>
                        @empty
                        @endforelse
                    </flux:select>
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="assignExistingMember" x-bind:disabled="!online">{{ __('staff.workspace.assign') }}</x-ui.button>
                </form>
            @elseif ($editor === 'areas')
                <p class="mt-3 text-sm text-text-muted">{{ __('staff.workspace.area_help') }}</p>
                <p class="mt-2 font-medium">{{ $coverageLabel }}</p>
                <form wire:submit="previewAreaAssignments" novalidate class="mt-4 space-y-4">
                    <flux:input wire:model.live.debounce.300ms="assignmentForm.search" name="assignmentForm.search" :label="__('staff.workspace.area_search')" type="search" maxlength="120" />
                    @forelse ($areaEditor['unavailable'] as $areaId)
                        <label class="flex min-h-touch items-center gap-3 rounded-control border border-warning-border p-3">
                            <input type="checkbox" wire:model="assignmentForm.areaIds" value="{{ $areaId }}" class="size-5" />
                            <span>{{ __('staff.workspace.area_unavailable', ['id' => $areaId]) }}</span>
                        </label>
                    @empty
                    @endforelse
                    <div class="grid gap-4 lg:grid-cols-2">
                        @forelse ($areaEditor['groups'] as $group)
                            <fieldset class="min-w-0 rounded-control border border-border-subtle p-3">
                                <legend class="px-1 text-sm font-semibold">{{ $group['label'] }}</legend>
                                @forelse ($group['areas'] as $area)
                                    <label wire:key="area-{{ $area['id'] }}" class="flex min-h-touch items-center gap-3 py-2">
                                        <input type="checkbox" wire:model="assignmentForm.areaIds" value="{{ $area['id'] }}" class="size-5 shrink-0" />
                                        <span class="text-sm">{{ $area['name'] }}</span>
                                    </label>
                                @empty
                                @endforelse
                            </fieldset>
                        @empty
                            <p class="text-sm text-text-muted">{{ __('staff.workspace.no_results') }}</p>
                        @endforelse
                    </div>
                    <div>{{ $areaEditor['paginator']->links() }}</div>
                    @if ($previewFingerprint !== '')
                    <p class="text-sm">{{ __('staff.workspace.added', ['count' => $areasAdded]) }} · {{ __('staff.workspace.removed', ['count' => $areasRemoved]) }}</p>
                    @endif
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="previewAreaAssignments" x-bind:disabled="!online">{{ __('staff.workspace.preview_change') }}</x-ui.button>
                        @if ($previewFingerprint !== '')
                            <x-ui.button variant="primary" wire:click="saveAreaAssignments" wire:loading.attr="disabled" wire:target="saveAreaAssignments" x-bind:disabled="!online">{{ __('staff.save_zones') }}</x-ui.button>
                        @endif
                        <x-ui.button x-on:click="requestNavigation(() => $wire.refreshAssignments(false), false)" wire:loading.attr="disabled" x-bind:disabled="!online">{{ __('staff.workspace.refresh_assignments') }}</x-ui.button>
                        <x-ui.button wire:click="refreshAssignments(true)" wire:loading.attr="disabled" x-bind:disabled="!online">{{ __('staff.workspace.reapply_assignments') }}</x-ui.button>
                    </div>
                </form>
            @else
                <p class="mt-3">{{ $preview['email'] }} · {{ $preview['role'] }}</p>
                <p class="mt-2 text-sm text-text-muted">{{ $preview['scope'] }}</p>
                @if ($editor === 'reissue')
                    <p class="mt-3 rounded-control border border-warning-border bg-warning-surface p-3 text-sm text-warning">{{ __('staff.workspace.reissue_warning') }}</p>
                    <x-ui.button class="mt-3" wire:click="reissueInvitation({{ $confirmInvitationId }})" wire:loading.attr="disabled" x-bind:disabled="!online">{{ __('staff.actions.reissue_invitation') }}</x-ui.button>
                @else
                    <x-ui.button class="mt-3" wire:click="cancelInvitation({{ $confirmInvitationId }})" wire:loading.attr="disabled" x-bind:disabled="!online">{{ __('staff.actions.cancel_invitation') }}</x-ui.button>
                @endif
            @endif
        </section>
    @endif

    <section aria-label="{{ $activeSection === 'invitations' ? __('staff.invitations') : __('staff.list') }}" @class(['min-w-0', 'hidden lg:block' => $editor !== ''])>
        <div class="min-w-0">
            <flux:input wire:model.live.debounce.300ms="filters.search" name="filters.search" :label="__('staff.workspace.search')" type="search" maxlength="120" />
            <details data-staff-filters class="mt-3" wire:ignore.self>
                <summary class="min-h-touch cursor-pointer py-2 text-sm font-medium">{{ __('menu.guest.filters') }} @if ($hasActiveFilters) · {{ __('staff.workspace.filters_active') }} @endif</summary>
                <div class="grid gap-3 sm:grid-cols-3">
            <flux:select wire:model.live="filters.role" name="filters.role" :label="__('staff.role')">
                <option value="">{{ __('staff.workspace.all_roles') }}</option>
                @forelse ($filterRoleOptions as $role)
                    <option value="{{ $role['value'] }}" wire:key="filter-role-{{ $role['value'] }}">{{ $role['label'] }}</option>
                @empty
                @endforelse
            </flux:select>
            <flux:select wire:model.live="filters.status" name="filters.status" :label="__('staff.workspace.status')">
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
            <x-ui.button wire:click="resetFilters">{{ __('staff.workspace.reset_filters') }}</x-ui.button>
            @if ($isBranch && $activeSection === 'employees')
                <x-ui.button wire:click="openExistingAssignment" x-bind:disabled="!online">{{ __('staff.workspace.assign_existing') }}</x-ui.button>
            @endif
        </div>
        @if ($coverageOverview !== null)
            <section aria-labelledby="staff-coverage-heading" class="my-4 rounded-control border border-border-subtle p-3">
                <h2 id="staff-coverage-heading" class="font-semibold">{{ __('staff.workspace.area_coverage') }}</h2>
                <p class="mt-2 text-sm text-text-muted">{{ __('staff.workspace.unrestricted_waiters', ['count' => $coverageOverview['unrestricted_count']]) }}</p>
                <ul class="mt-1 text-sm">
                    @forelse ($coverageOverview['unrestricted_names'] as $name)
                        <li>{{ $name }}</li>
                    @empty
                    @endforelse
                </ul>
                <div class="mt-3 divide-y divide-border-subtle">
                    @forelse ($coverageOverview['rows'] as $area)
                        <article wire:key="coverage-{{ $area['id'] }}" class="py-3">
                            <h3 class="font-medium">{{ $area['name'] }}</h3>
                            <p class="text-sm">{{ __('staff.workspace.area_waiters', ['count' => $area['assigned_count']]) }}</p>
                            <ul class="text-sm text-text-muted">
                                @forelse ($area['waiter_names'] as $name)
                                    <li>{{ $name }}</li>
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
            <div class="divide-y divide-border-subtle border-y border-border-subtle">
                @forelse ($invitationRows as $invitation)
                    <article wire:key="invitation-{{ $invitation['id'] }}" class="flex min-w-0 flex-wrap items-start justify-between gap-3 py-4">
                        <div class="min-w-0 flex-1">
                            <h3 class="break-words font-semibold">{{ $invitation['email'] }}</h3>
                            <p class="mt-1 text-sm">{{ $invitation['role_label'] }} · {{ $invitation['localized_status'] }}</p>
                            <p class="mt-1 text-sm text-text-muted">{{ __('staff.invitation_meta.created', ['name' => $invitation['created_by'], 'date' => $invitation['created_at']]) }}</p>
                            <p class="text-sm text-text-muted">{{ __('staff.invitation_meta.expires', ['date' => $invitation['expires_at']]) }}</p>
                            @if ($invitation['accepted_at'] !== null)
                                <p class="text-sm text-text-muted">{{ __('staff.invitation_meta.accepted', ['name' => $invitation['accepted_by'], 'date' => $invitation['accepted_at']]) }}</p>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if ($invitation['can_reissue'])
                                <x-ui.button wire:click="confirmInvitation({{ $invitation['id'] }}, 'reissue')" x-bind:disabled="!online">{{ __('staff.actions.reissue_invitation') }}</x-ui.button>
                            @endif
                            @if ($invitation['can_cancel'])
                                <x-ui.button wire:click="confirmInvitation({{ $invitation['id'] }}, 'cancel')" x-bind:disabled="!online">{{ __('staff.actions.cancel_invitation') }}</x-ui.button>
                            @endif
                        </div>
                    </article>
                @empty
                    <p class="py-6 text-text-muted">{{ __('staff.workspace.invitation_empty') }}</p>
                @endforelse
            </div>
            <div class="mt-4">{{ $invitationsPaginator->links() }}</div>
        @else
            <div class="divide-y divide-border-subtle border-y border-border-subtle">
                @forelse ($memberRows as $member)
                    <article wire:key="member-{{ $member['id'] }}" class="flex min-w-0 flex-wrap items-start justify-between gap-3 py-4">
                        <div class="min-w-0 flex-1">
                            <h3 class="break-words font-semibold">{{ $member['user_name'] }}</h3>
                            <p class="break-words text-sm text-text-muted">{{ $member['user_email'] }}</p>
                            <p class="mt-1 text-sm">{{ $member['role_label'] }} · {{ $member['localized_status'] }}</p>
                            @if ($member['coverage'] !== null)
                                <p class="mt-1 text-sm text-text-muted">{{ $member['coverage'] }}</p>
                            @endif
                        </div>
                        @if ($member['can_manage'])
                            <div class="flex flex-wrap gap-2">
                                @if ($activeSection === 'assignments')
                                    <x-ui.button wire:click="openAssignments({{ $member['id'] }})" x-bind:disabled="!online">{{ __('staff.workspace.assignments') }}</x-ui.button>
                                @else
                                    <x-ui.button wire:click="openMember({{ $member['id'] }}, 'role')" x-bind:disabled="!online">{{ __('staff.actions.edit_role') }}</x-ui.button>
                                    <x-ui.button wire:click="openMember({{ $member['id'] }}, 'status')" x-bind:disabled="!online">{{ $member['is_active'] ? __('staff.deactivate') : __('staff.reactivate') }}</x-ui.button>
                                    @if ($member['permissions_url'] !== null)
                                        <x-ui.button :href="$member['permissions_url']" wire:navigate>{{ $isBranch ? __('staff.workspace.organization_permissions') : __('staff.workspace.permissions') }}</x-ui.button>
                                    @endif
                                @endif
                            </div>
                        @endif
                    </article>
                @empty
                    <p class="py-6 text-text-muted">{{ __('staff.workspace.no_results') }}</p>
                @endforelse
            </div>
            <div class="mt-4">{{ $membersPaginator->links() }}</div>
        @endif
    </section>
    <x-staff.unsaved-dialog />
</section>
