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
                    <flux:input wire:model="memberForm.reason" name="memberForm.reason" :label="__('staff.workspace.reason')" maxlength="500" required />
                    <flux:button type="submit" wire:loading.attr="disabled" x-bind:disabled="!online" class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2">{{ __('staff.workspace.preview_change') }}</flux:button>
                </form>
                @if ($preview !== [])
                    <dl class="mt-4 grid gap-1 border-t border-border-subtle pt-4 text-sm">
                        <dt class="text-text-muted">{{ __('staff.workspace.current') }}</dt><dd>{{ $preview['current'] }}</dd>
                        <dt class="text-text-muted">{{ __('staff.workspace.proposed') }}</dt><dd>{{ $preview['proposed'] }}</dd>
                    </dl>
                    @if (isset($preview['impact']))
                        <p>{{ __('team.card.role_contract') }}</p>
                        @if ($preview['impact']['removes_area_restrictions'])<p>{{ __('team.card.role_areas_warning') }}</p>@endif
                        <flux:accordion>
                            <flux:accordion.item expanded>
                                <flux:accordion.heading>{{ __('team.card.projected_permissions') }}</flux:accordion.heading>
                                <flux:accordion.content>
                                    @forelse ($preview['impact']['changes'] as $change)
                                        <div class="rm-team-card__permission" wire:key="role-impact-{{ $change['permission_id'] }}">
                                            <h4>{{ $change['label'] }}</h4>
                                            <p>{{ $change['projected_role_default'] ? __('permissions.states.role_allows') : __('permissions.states.role_denies') }} · {{ $change['override_state'] === 'default' ? __('team.card.state_default') : ($change['override_state'] === 'allow' ? __('team.card.state_allow') : __('team.card.state_deny')) }}</p>
                                            <p>{{ $change['current_allowed'] ? __('permissions.states.allowed') : __('permissions.states.denied') }} → {{ $change['projected_allowed'] ? __('permissions.states.allowed') : __('permissions.states.denied') }} · {{ $change['projected_reason'] }}</p>
                                        </div>
                                    @empty <p>{{ __('team.card.permissions_unchanged') }}</p>@endforelse
                                </flux:accordion.content>
                            </flux:accordion.item>
                        </flux:accordion>
                        @forelse ($preview['impact']['department_effects'] as $department)
                            <p>{{ $department['key'] === 'kitchen' ? __('workspace.kitchen') : __('workspace.bar') }}: {{ $department['current_eligible'] ? __('permissions.states.allowed') : __('permissions.states.denied') }} → {{ $department['projected_eligible'] ? __('permissions.states.allowed') : __('permissions.states.denied') }}</p>
                        @empty @endforelse
                        <p>{{ __('team.card.resource_rules') }}</p>
                    @endif
                    <flux:button class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2 mt-3" variant="primary" wire:click="saveMember" wire:loading.attr="disabled" wire:target="saveMember" x-bind:disabled="!online">{{ __('staff.workspace.confirm_change') }}</flux:button>
                @endif
