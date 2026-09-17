@pushOnce('page-scripts', 'team-styles')
    @vite('resources/scss/team.scss')
@endPushOnce

@pushOnce('page-module-status', 'staff-status')
    <x-page-module-status module="staff" />
@endPushOnce

<section data-page-module="staff" wire:ignore.self x-ignore inert data-staff-workspace data-page="employee-card" x-data="staffWorkspace" class="rm-team-card">
    <header class="rm-team-card__identity">
        <div>
            <flux:button :href="$listUrl" wire:navigate icon="arrow-left">{{ __('team.card.back') }}</flux:button>
            <h1 data-staff-heading tabindex="-1" class="text-2xl font-semibold">{{ $card['name'] }}</h1>
            <p class="text-sm text-text-muted">{{ $contextLabel }}</p>
            <p class="text-sm text-text-muted">{{ $card['email'] }}</p>
        </div>
        <flux:badge>{{ $isBranch ? __('team.card.branch_scope') : __('team.card.organization_scope') }}</flux:badge>
    </header>
    <nav class="rm-team-card__nav" aria-label="{{ __('team.card.navigation') }}">
        <flux:button class="h-auto! min-h-touch max-w-full whitespace-normal! wrap-anywhere py-2" data-team-section="overview" x-on:click="navigateSection($event, 'overview')" :variant="$activeSection === 'overview' ? 'primary' : 'ghost'" :aria-current="$activeSection === 'overview' ? 'page' : null">{{ __('team.card.overview') }}</flux:button>
        <flux:button class="h-auto! min-h-touch max-w-full whitespace-normal! wrap-anywhere py-2" data-team-section="access" x-on:click="navigateSection($event, 'access')" :variant="$activeSection === 'access' ? 'primary' : 'ghost'" :aria-current="$activeSection === 'access' ? 'page' : null">{{ __('team.card.access') }}</flux:button>
        <flux:button class="h-auto! min-h-touch max-w-full whitespace-normal! wrap-anywhere py-2" data-team-section="areas" x-on:click="navigateSection($event, 'areas')" :variant="$activeSection === 'areas' ? 'primary' : 'ghost'" :aria-current="$activeSection === 'areas' ? 'page' : null">{{ __('team.card.areas') }}</flux:button>
        @if ($card['can_history'])
            <flux:button class="h-auto! min-h-touch max-w-full whitespace-normal! wrap-anywhere py-2" data-team-section="history" x-on:click="navigateSection($event, 'history')" :variant="$activeSection === 'history' ? 'primary' : 'ghost'" :aria-current="$activeSection === 'history' ? 'page' : null">{{ __('team.card.history') }}</flux:button>
        @endif
    </nav>
    <div role="status" aria-live="polite">{{ $successMessage }}</div>
    <div wire:offline role="status">{{ __('ui.connectivity.offline') }}</div>
    @if ($errorMessage !== '')<p role="alert">{{ $errorMessage }}</p>@endif
    @if ($errors->any())
        <div role="alert" tabindex="-1">
            @forelse ($errors->all() as $error)<p>{{ $error }}</p>@empty @endforelse
        </div>
    @endif
    <div class="rm-team-card__layout">
        @if ($activeSection === 'overview')
            <section class="rm-team-card__section">
                <h2 class="text-lg font-semibold">{{ __('team.card.overview') }}</h2>
                <dl class="rm-team-card__facts">
                    <div><dt class="text-sm text-text-muted">{{ __('team.card.organization_role') }}</dt><dd class="mt-1 font-medium">{{ $card['organization_role'] }} · {{ $card['organization_status'] }}</dd></div>
                    @if ($isBranch)
                        <div><dt class="text-sm text-text-muted">{{ __('team.card.branch_role') }}</dt><dd class="mt-1 font-medium">{{ $card['branch_role'] ?? __('team.card.no_assignment') }} @if ($card['branch_status']) · {{ $card['branch_status'] }} @endif</dd></div>
                        <div><dt class="text-sm text-text-muted">{{ __('team.card.actual_access') }}</dt><dd class="mt-1 font-medium">{{ $card['branch_accessible'] ? __('permissions.states.allowed') : __('permissions.states.denied') }}</dd></div>
                    @endif
                    <div><dt class="text-sm text-text-muted">{{ __('team.card.access_mode') }}</dt><dd class="mt-1 font-medium">{{ $card['mode'] === 'inherited' ? __('team.card.inherited') : __('team.card.explicit') }}</dd></div>
                </dl>
                <p>{{ __('team.card.identity_help') }}</p>
                <p>{{ __('team.card.scope_help') }}</p>
                <h3>{{ __('team.card.restaurants') }}</h3>
                <ul class="rm-team-card__branches">
                    @forelse ($card['branches'] as $restaurant)
                        <li wire:key="member-restaurant-{{ $restaurant['id'] }}">
                            <flux:link :href="$restaurant['url']" wire:navigate>{{ $restaurant['name'] }}</flux:link>
                            <span>{{ $restaurant['accessible'] ? __('permissions.states.allowed') : __('permissions.states.denied') }}</span>
                            @if ($restaurant['role'])<span>{{ $restaurant['role'] }} · {{ $restaurant['status'] }}</span>@endif
                        </li>
                    @empty <li>{{ __('staff.workspace.no_results') }}</li> @endforelse
                </ul>
                {{ $card['branches_paginator']->links() }}
            </section>
        @elseif ($activeSection === 'access')
            <section class="rm-team-card__section">
                <h2 class="text-lg font-semibold">{{ __('team.card.access') }}</h2>
                <p>{{ __('team.card.role_contract') }}</p>
                @if ($card['self_edit_blocked'])<p>{{ __('permissions.messages.self_edit_disabled') }}</p>@endif
                @if ($card['protected_account'])<p>{{ __('permissions.messages.superadmin_full_access') }}</p>@endif
                <dl class="rm-team-card__facts">@forelse ($capabilities as $capability)<div><dt class="text-sm text-text-muted">{{ $capability['label'] }}</dt><dd class="mt-1 font-medium">{{ $capability['allowed'] ? __('permissions.states.allowed') : __('permissions.states.denied') }}</dd></div>@empty @endforelse</dl>
                <dl class="rm-team-card__facts">
                    <div><dt class="text-sm text-text-muted">{{ __('team.card.organization_role') }}</dt><dd class="mt-1 font-medium">{{ $card['organization_role'] }} · {{ $card['organization_status'] }}</dd></div>
                    @if ($isBranch)<div><dt class="text-sm text-text-muted">{{ __('team.card.branch_role') }}</dt><dd class="mt-1 font-medium">{{ $card['branch_role'] ?? __('team.card.no_assignment') }} · {{ $card['branch_status'] }}</dd></div>@endif
                </dl>
                @if ($card['open_tasks'] !== null)<p>{{ __('team.card.open_tasks', ['count' => $card['open_tasks']]) }}</p>@endif
                <div class="rm-team-card__nav">
                    @if ($card['can_manage'] && $card['membership_id'] !== null)
                        <flux:button class="h-auto! min-h-touch max-w-full whitespace-normal! wrap-anywhere py-2" wire:click="openMember({{ $card['membership_id'] }}, 'role')" x-bind:disabled="!online">{{ __('staff.actions.edit_role') }}</flux:button>
                        <flux:button class="h-auto! min-h-touch max-w-full whitespace-normal! wrap-anywhere py-2" wire:click="openMember({{ $card['membership_id'] }}, 'status')" x-bind:disabled="!online">{{ __('team.card.change_status') }}</flux:button>
                    @endif
                    @if ($card['can_assign'])<flux:button class="h-auto! min-h-touch max-w-full whitespace-normal! wrap-anywhere py-2" wire:click="openExistingAssignment" x-bind:disabled="!online">{{ __('staff.workspace.assign') }}</flux:button>@endif
                    @if ($card['can_remove'])<flux:button class="h-auto! min-h-touch max-w-full whitespace-normal! wrap-anywhere py-2" wire:click="openAssignmentRemoval" x-bind:disabled="!online">{{ __('team.card.review_removal') }}</flux:button>@endif
                    @if ($card['can_permissions'])<flux:button class="h-auto! min-h-touch max-w-full whitespace-normal! wrap-anywhere py-2" wire:click="openPermissions" x-bind:disabled="!online">{{ __('staff.workspace.organization_permissions') }}</flux:button>@endif
                    @if ($isBranch && $card['organization_url'])<flux:button class="h-auto! min-h-touch max-w-full whitespace-normal! wrap-anywhere py-2" :href="$card['organization_url']" wire:navigate>{{ __('team.card.open_organization') }}</flux:button>@endif
                </div>
                @if ($editor === 'member')
                    <section data-staff-editor class="rm-team-card__section">
                        <h3>{{ $isBranch ? __('team.card.branch_scope') : __('team.card.organization_scope') }}</h3>
                        @if ($memberOperation === 'role' && $isBranch)<p>{{ __('team.card.role_areas_warning') }}</p>@endif
                        @if ($memberOperation === 'status')<p>{{ __('team.card.status_help') }}</p>@endif
                        @include('livewire.organizations.staff.member-fields')
                        <flux:button x-on:click="requestNavigation(() => dismissEditor(), false)">{{ __('staff.workspace.close') }}</flux:button>
                    </section>
                @elseif ($editor === 'remove-assignment')
                    <section data-staff-editor class="rm-team-card__section">
                        <h3>{{ __('team.card.review_removal') }}</h3>
                        <p>{{ __('team.card.removal_help') }}</p><p>{{ __('team.card.invitation_stop_help') }}</p>
                        <form wire:submit="previewAssignmentRemoval" novalidate>
                            <flux:input wire:model="removalForm.reason" :label="__('staff.workspace.reason')" maxlength="500" />
                            <flux:checkbox wire:model="removalForm.confirmed" :label="__('team.card.confirm_scope')" />
                            <flux:button type="submit" x-bind:disabled="!online" wire:loading.attr="disabled">{{ __('staff.workspace.preview_change') }}</flux:button>
                        </form>
                        @if ($preview !== [])
                            <div class="rm-team-card__change">
                                <h4>{{ $preview['operation'] === 'return_to_organization' ? __('team.card.return_rule') : __('team.card.remove_assignment') }}</h4>
                                @if ($preview['operation'] === 'return_to_organization')<p>{{ __('team.card.return_rule_help') }}</p>@endif
                                <h4>{{ __('staff.workspace.current') }}</h4>
                                <ul>@forelse ($preview['before'] as $place)<li>{{ $place['name'] }}</li>@empty <li>{{ __('staff.workspace.no_results') }}</li>@endforelse</ul>
                                <h4>{{ __('staff.workspace.proposed') }}</h4>
                                <ul>@forelse ($preview['after'] as $place)<li>{{ $place['name'] }}</li>@empty <li>{{ __('staff.workspace.no_results') }}</li>@endforelse</ul>
                                <p>{{ __('team.card.removal_areas', ['count' => $preview['area_count']]) }}</p>
                                @if ($preview['can_apply'])
                                    <flux:button wire:click="removeAssignment" variant="primary" x-bind:disabled="!online" wire:loading.attr="disabled">{{ $preview['operation'] === 'return_to_organization' ? __('team.card.return_rule') : __('team.card.remove_assignment') }}</flux:button>
                                @else <p>{{ __('team.card.assignment_restricted') }}</p> @endif
                            </div>
                        @endif
                        <flux:button x-on:click="requestNavigation(() => dismissEditor(), false)">{{ __('staff.workspace.close') }}</flux:button>
                    </section>
                @elseif ($editor === 'assign')
                    <section data-staff-editor class="rm-team-card__section">
                        <h3>{{ __('staff.workspace.assign') }}</h3>
                        <form wire:submit="previewExistingAssignment" novalidate>
                            <flux:select variant="listbox" wire:model="memberForm.roleId" :label="__('team.card.branch_role')">
                                @forelse ($roleOptions as $role)<flux:select.option value="{{ $role['id'] }}">{{ $role['label'] }}</flux:select.option>@empty @endforelse
                            </flux:select>
                            <flux:button type="submit" x-bind:disabled="!online" wire:loading.attr="disabled">{{ __('staff.workspace.preview_change') }}</flux:button>
                        </form>
                        @if ($preview !== [])
                            <div class="rm-team-card__change">
                                <h4>{{ __('staff.workspace.current') }}</h4>
                                <p>{{ $preview['mode'] === 'organization' ? __('team.card.inherited') : __('team.card.explicit') }}</p>
                                <ul>@forelse ($preview['before'] as $place)<li>{{ $place['name'] }}</li>@empty <li>{{ __('staff.workspace.no_results') }}</li>@endforelse</ul>
                                <h4>{{ __('staff.workspace.proposed') }}</h4><p>{{ __('team.card.explicit') }}</p>
                                <ul>@forelse ($preview['after'] as $place)<li>{{ $place['name'] }}</li>@empty <li>{{ __('staff.workspace.no_results') }}</li>@endforelse</ul>
                                <p>{{ __('team.card.first_assignment_warning') }}</p>
                                @if ($preview['can_apply'])
                                    <flux:button variant="primary" wire:click="assignExistingMember" x-bind:disabled="!online" wire:loading.attr="disabled">{{ __('team.card.confirm_assignment') }}</flux:button>
                                @else <p>{{ __('team.card.assignment_restricted') }}</p> @endif
                            </div>
                        @endif
                        <flux:button x-on:click="requestNavigation(() => dismissEditor(), false)">{{ __('staff.workspace.close') }}</flux:button>
                    </section>
                @endif
                <p>{{ __('team.card.permission_scope') }}</p>
                <p>{{ __('team.card.resource_rules') }}</p><p>{{ __('team.card.department_rule') }}</p>
                <section @if ($editor === 'permissions') data-staff-editor @endif class="rm-team-card__section">
                    <flux:accordion transition>
                    @forelse ($permissionGroups as $groupKey => $permissionGroup)
                    <flux:accordion.item wire:key="permission-group-{{ $groupKey }}" expanded>
                        <flux:accordion.heading>{{ $permissionGroup['label'] }}</flux:accordion.heading>
                        <flux:accordion.content>
                    @forelse ($permissionGroup['rows'] as $permission)
                        <div class="rm-team-card__permission" wire:key="permission-{{ $permission['id'] }}">
                            <div><h3>{{ $permission['label'] }}</h3>@if ($showTechnicalPermissionKeys)<code>{{ $permission['code'] }}</code>@endif<p>{{ $permission['group_label'] }}</p><p>{{ $permission['description'] }}</p><p>{{ $permission['role_default'] ? __('permissions.states.role_allows') : __('permissions.states.role_denies') }}</p><p>{{ $permission['effective_allowed'] ? __('permissions.states.allowed') : __('permissions.states.denied') }} · {{ $permission['effective_reason'] }}</p></div>
                            @if ($editor === 'permissions')
                                <flux:select wire:model="permissionForm.states.{{ $permission['id'] }}" :label="$permission['label']">
                                    <option value="default">{{ __('team.card.state_default') }}</option><option value="allow">{{ __('team.card.state_allow') }}</option><option value="deny">{{ __('team.card.state_deny') }}</option>
                                </flux:select>
                            @else
                                <p>{{ $permission['override_state'] === 'default' ? __('team.card.state_default') : ($permission['override_state'] === 'allow' ? __('team.card.state_allow') : __('team.card.state_deny')) }}</p>
                            @endif
                        </div>
                    @empty @endforelse
                        </flux:accordion.content>
                    </flux:accordion.item>
                    @empty <p>{{ __('staff.workspace.no_results') }}</p> @endforelse
                    </flux:accordion>
                    @if ($editor === 'permissions')
                        <flux:input wire:model="permissionForm.reason" :label="__('staff.workspace.reason')" maxlength="500" />
                        <flux:checkbox wire:model="permissionForm.confirmed" :label="__('permissions.draft.confirm')" />
                        <flux:button wire:click="previewPermissions" x-bind:disabled="!online" wire:loading.attr="disabled">{{ __('staff.workspace.preview_change') }}</flux:button>
                        @if ($preview !== [])
                            <div class="rm-team-card__change">
                                @forelse ($preview['changes'] as $change)
                                    <p>{{ $change['label'] }}: @if ($change['indirect']) {{ __('team.card.indirect_change') }} @else {{ $change['before_label'] }} → {{ $change['after_label'] }} @endif · {{ $change['current_allowed'] ? __('permissions.states.allowed') : __('permissions.states.denied') }} → {{ $change['projected_allowed'] ? __('permissions.states.allowed') : __('permissions.states.denied') }} · {{ $change['projected_reason'] }}</p>
                                @empty <p>{{ __('team.card.permissions_unchanged') }}</p> @endforelse
                                <flux:button wire:click="applyPermissions" variant="primary" x-bind:disabled="!online" wire:loading.attr="disabled">{{ __('team.card.apply_permissions') }}</flux:button>
                            </div>
                        @endif
                        <flux:button x-on:click="requestNavigation(() => dismissEditor(), false)">{{ __('staff.workspace.close') }}</flux:button>
                    @endif
                </section>
            </section>
        @elseif ($activeSection === 'areas')
            <section class="rm-team-card__section">
                <h2 class="text-lg font-semibold">{{ __('team.card.areas') }}</h2><p>{{ __('team.card.exact_areas') }}</p>
                @if ($editor === 'areas')
                    <section data-staff-editor class="rm-team-card__section">
                        @include('livewire.organizations.staff.area-fields')
                        <flux:button x-on:click="requestNavigation(() => dismissEditor(), false)">{{ __('staff.workspace.close') }}</flux:button>
                    </section>
                @else
                    <p>{{ __('team.card.areas_unavailable') }}</p>
                    @if ($card['can_areas'])<flux:button wire:click="openAssignments({{ $card['membership_id'] }})" x-bind:disabled="!online">{{ __('team.card.areas') }}</flux:button>@endif
                @endif
            </section>
        @elseif ($activeSection === 'history')
            <section class="rm-team-card__section">
                <h2 class="text-lg font-semibold">{{ __('team.card.history') }}</h2>
                @forelse ($historyRows as $event)
                    <article wire:key="team-event-{{ $event['id'] }}">
                        <h3>{{ $event['action'] }}</h3><p>{{ $event['actor'] }} · {{ $event['date'] }} · {{ $event['scope'] }}</p>
                        <dl class="rm-team-card__facts"><div><dt class="text-sm text-text-muted">{{ __('staff.workspace.current') }}</dt><dd class="mt-1 font-medium">@forelse ($event['before'] as $value)<p>{{ $value }}</p>@empty — @endforelse</dd></div><div><dt class="text-sm text-text-muted">{{ __('staff.workspace.proposed') }}</dt><dd class="mt-1 font-medium">@forelse ($event['after'] as $value)<p>{{ $value }}</p>@empty — @endforelse</dd></div></dl>
                    </article>
                @empty <p>{{ __('team.card.history_empty') }}</p> @endforelse
                @if ($historyPaginator){{ $historyPaginator->links() }}@endif
            </section>
        @endif
    </div>
    <x-staff.unsaved-dialog />
</section>
