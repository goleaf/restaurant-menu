<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Staff;

use App\Actions\Staff\SetUserPermissionOverrideAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\PermissionOverrideState;
use App\Enums\SystemPermission;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Staff\PermissionQueryService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Permissions extends Component
{
    private SetUserPermissionOverrideAction $setUserPermissionOverride;

    private PermissionQueryService $permissionQueries;

    public Organization $organization;

    public User $staffMember;

    #[Locked]
    public int $membershipRoleId;

    public string $membershipRoleName = '';

    public string $membershipStatus = '';

    public string $membershipStatusLabel = '';

    public bool $selfEditBlocked = false;

    public bool $superadminTarget = false;

    public bool $showTechnicalPermissionKeys = false;

    public ?string $lastCriticalWarning = null;

    public string $criticalPermissionChangeReason = '';

    public function boot(
        SetUserPermissionOverrideAction $setUserPermissionOverride,
        PermissionQueryService $permissionQueries,
    ): void {
        $this->setUserPermissionOverride = $setUserPermissionOverride;
        $this->permissionQueries = $permissionQueries;
    }

    public function mount(Organization $organization, User $staffMember): void
    {
        $this->organization = $organization;
        $this->staffMember = $staffMember;

        $currentUser = $this->currentUser();
        Gate::forUser($currentUser)->authorize('managePermissions', $organization);

        $this->showTechnicalPermissionKeys = $currentUser->isSuperadmin();

        $membership = $this->permissionQueries->membership($organization, $staffMember);

        $this->membershipRoleId = $membership->role_id;
        $this->membershipRoleName = $this->roleLabel($membership->role);
        $this->membershipStatus = $membership->status->value;
        $this->membershipStatusLabel = $this->organizationUserStatusLabel($membership->status);
        $this->selfEditBlocked = $currentUser->id === $staffMember->id;
        $this->superadminTarget = $staffMember->isSuperadmin();
    }

    public function setPermissionState(int $permissionId, string $state): void
    {
        $this->authorizeStaffManagement();
        $currentUser = $this->currentUser();
        $this->selfEditBlocked = $currentUser->id === $this->staffMember->id;
        $this->superadminTarget = $this->staffMember->isSuperadmin();

        if ($this->selfEditBlocked) {
            $this->lastCriticalWarning = __('permissions.messages.self_edit_disabled');
            Flux::toast(variant: 'warning', text: $this->lastCriticalWarning);

            return;
        }

        if ($this->superadminTarget) {
            $this->lastCriticalWarning = __('permissions.messages.superadmin_full_access');
            Flux::toast(variant: 'warning', text: $this->lastCriticalWarning);

            return;
        }

        $overrideState = PermissionOverrideState::tryFrom($state);

        if (! $overrideState instanceof PermissionOverrideState) {
            $this->addError('state', __('permissions.errors.invalid_state'));

            return;
        }

        $permission = $this->permissionQueries->permission($permissionId);

        $systemPermission = SystemPermission::tryFrom($permission->code);
        $reason = null;

        if ($systemPermission?->isCritical()) {
            $validated = $this->validate([
                'criticalPermissionChangeReason' => ['required', 'string', 'min:3', 'max:500'],
            ], [
                'criticalPermissionChangeReason.required' => __('permissions.errors.critical_reason_required'),
                'criticalPermissionChangeReason.min' => __('permissions.errors.critical_reason_min'),
            ]);

            $reason = (string) $validated['criticalPermissionChangeReason'];
        }

        $this->setUserPermissionOverride->handle(
            user: $this->staffMember,
            permission: $permission,
            state: $overrideState,
            changedBy: $currentUser,
            organizationId: $this->organization->id,
            reason: $reason,
        );

        if ($systemPermission?->isCritical()) {
            $this->lastCriticalWarning = __('permissions.messages.critical_permission_changed');
            $this->criticalPermissionChangeReason = '';
            Flux::modals()->close();
            Flux::toast(variant: 'warning', text: $this->lastCriticalWarning);
        } else {
            $this->lastCriticalWarning = null;
            Flux::toast(variant: 'success', text: __('permissions.messages.override_saved'));
        }

    }

    /**
     * @return list<array{id: int, code: string, name: string, label: string, description: string, group_key: string, group_label: string, is_critical: bool, role_default: bool, role_default_label: string, override_state: string, override_label: string, effective_allowed: bool, effective_label: string}>
     */
    #[Computed]
    public function permissionRows(): array
    {
        $roleDefaults = $this->permissionQueries->roleDefaults($this->membershipRoleId);
        $overrides = $this->permissionQueries->userOverrides($this->staffMember);

        return $this->permissionQueries->permissions()
            ->map(function (Permission $permission) use ($roleDefaults, $overrides): array {
                $hasOverride = $overrides->has($permission->id);
                $overrideState = match (true) {
                    ! $hasOverride => PermissionOverrideState::Default,
                    (bool) $overrides->get($permission->id) => PermissionOverrideState::Allow,
                    default => PermissionOverrideState::Deny,
                };
                $roleDefault = (bool) $roleDefaults->get($permission->id, false);
                $effectiveAllowed = $this->superadminTarget
                    || ($this->membershipStatus === OrganizationUserStatus::Active->value && ($hasOverride ? (bool) $overrides->get($permission->id) : $roleDefault));
                $systemPermission = SystemPermission::tryFrom($permission->code);
                $fallbackLabel = str($permission->name ?: $permission->code)
                    ->replace('_', ' ')
                    ->headline()
                    ->toString();

                return [
                    'id' => $permission->id,
                    'code' => $permission->code,
                    'name' => $permission->name,
                    'label' => $systemPermission instanceof SystemPermission ? __($systemPermission->uiLabelKey()) : $fallbackLabel,
                    'description' => $systemPermission instanceof SystemPermission ? __($systemPermission->uiDescriptionKey()) : __('permissions.descriptions.custom'),
                    'group_key' => $systemPermission instanceof SystemPermission ? $systemPermission->uiGroupKey() : 'other',
                    'group_label' => $systemPermission instanceof SystemPermission ? __($systemPermission->uiGroupLabelKey()) : __('permissions.groups.other'),
                    'is_critical' => $systemPermission?->isCritical() ?? false,
                    'role_default' => $roleDefault,
                    'role_default_label' => $roleDefault ? __('permissions.states.role_allows') : __('permissions.states.role_denies'),
                    'override_state' => $overrideState->value,
                    'override_label' => __($overrideState->summaryLabelKey()),
                    'effective_allowed' => $effectiveAllowed,
                    'effective_label' => $effectiveAllowed ? __('permissions.states.allowed') : __('permissions.states.denied'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{key: string, label: string, permissions: list<array{id: int, code: string, name: string, label: string, description: string, group_key: string, group_label: string, is_critical: bool, role_default: bool, role_default_label: string, override_state: string, override_label: string, effective_allowed: bool, effective_label: string}>}>
     */
    #[Computed]
    public function permissionGroups(): array
    {
        $groupOrder = array_flip(SystemPermission::uiGroupOrder());

        return collect($this->permissionRows())
            ->groupBy('group_key')
            ->map(fn ($rows, string $key): array => [
                'key' => $key,
                'label' => (string) $rows->first()['group_label'],
                'permissions' => $rows->values()->all(),
            ])
            ->sortBy(fn (array $group): int => $groupOrder[$group['key']] ?? PHP_INT_MAX)
            ->values()
            ->all();
    }

    public function render(): View
    {
        return view('livewire.organizations.staff.permissions', [
            'organizationName' => $this->organization->name,
            'staffMemberName' => $this->staffMember->name,
            'staffMemberEmail' => $this->staffMember->email,
            'permissionGroups' => $this->permissionGroups(),
        ])
            ->title(__('staff.actions.update_permissions'));
    }

    private function roleLabel(?Role $role): string
    {
        if (! $role instanceof Role) {
            return '';
        }

        return $role->code->localizedLabel();
    }

    private function organizationUserStatusLabel(OrganizationUserStatus $status): string
    {
        return __(sprintf('staff.statuses.%s', $status->value));
    }

    private function authorizeStaffManagement(): void
    {
        Gate::forUser($this->currentUser())->authorize('managePermissions', $this->organization);
    }

    private function currentUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }
}
