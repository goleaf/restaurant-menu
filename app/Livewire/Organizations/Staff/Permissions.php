<?php

namespace App\Livewire\Organizations\Staff;

use App\Actions\Staff\SetUserPermissionOverrideAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\PermissionOverrideState;
use App\Enums\SystemPermission;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Staff permissions')]
class Permissions extends Component
{
    public Organization $organization;

    public User $staffMember;

    public int $membershipRoleId;

    public string $membershipRoleName = '';

    public string $membershipStatus = '';

    public bool $selfEditBlocked = false;

    public bool $superadminTarget = false;

    public ?string $lastCriticalWarning = null;

    public function mount(Organization $organization, User $staffMember): void
    {
        $this->organization = $organization;
        $this->staffMember = $staffMember;

        $currentUser = $this->currentUser();

        if (! $currentUser->canAccessOrganization($organization)) {
            abort(403);
        }

        if (! $currentUser->hasPermission(SystemPermission::ManageStaff, $organization)) {
            abort(403);
        }

        $membership = OrganizationUser::query()
            ->select(['id', 'organization_id', 'user_id', 'role_id', 'status', 'joined_at', 'invited_by_user_id', 'created_at', 'updated_at'])
            ->with(['role' => fn ($query) => $query->select(['id', 'code', 'name', 'sort_order'])])
            ->where('organization_id', $organization->id)
            ->where('user_id', $staffMember->id)
            ->firstOrFail();

        $this->membershipRoleId = $membership->role_id;
        $this->membershipRoleName = $membership->role->name;
        $this->membershipStatus = $membership->status->value;
        $this->selfEditBlocked = $currentUser->id === $staffMember->id;
        $this->superadminTarget = $staffMember->isSuperadmin();
    }

    public function setPermissionState(int $permissionId, string $state): void
    {
        $this->authorizeStaffManagement();

        if ($this->selfEditBlocked) {
            $this->lastCriticalWarning = __('Self-edit is disabled. Ask another manager to change your permissions.');
            Flux::toast(variant: 'warning', text: $this->lastCriticalWarning);

            return;
        }

        if ($this->superadminTarget) {
            $this->lastCriticalWarning = __('Superadmin always has full access.');
            Flux::toast(variant: 'warning', text: $this->lastCriticalWarning);

            return;
        }

        $overrideState = PermissionOverrideState::tryFrom($state);

        if (! $overrideState instanceof PermissionOverrideState) {
            $this->addError('state', __('Invalid permission state.'));

            return;
        }

        $permission = Permission::query()
            ->select(['id', 'code', 'name', 'sort_order'])
            ->whereKey($permissionId)
            ->firstOrFail();

        (new SetUserPermissionOverrideAction)->handle($this->staffMember, $permission, $overrideState);

        $systemPermission = SystemPermission::tryFrom($permission->code);

        if ($systemPermission?->isCritical()) {
            $this->lastCriticalWarning = __('Critical permission changed.');
            Flux::toast(variant: 'warning', text: $this->lastCriticalWarning);
        } else {
            $this->lastCriticalWarning = null;
            Flux::toast(variant: 'success', text: __('Permission override saved.'));
        }

        unset($this->permissionRows);
    }

    /**
     * @return list<array{id: int, code: string, name: string, is_critical: bool, role_default: bool, role_default_label: string, override_state: string, override_label: string, effective_allowed: bool, effective_label: string}>
     */
    #[Computed]
    public function permissionRows(): array
    {
        $role = Role::query()
            ->select(['id', 'code', 'name', 'sort_order'])
            ->with([
                'permissions' => fn ($query) => $query
                    ->select(['permissions.id', 'permissions.code'])
                    ->orderBy('permissions.sort_order'),
            ])
            ->whereKey($this->membershipRoleId)
            ->firstOrFail();

        $roleDefaults = $role->permissions
            ->mapWithKeys(fn (Permission $permission): array => [
                $permission->id => (bool) $permission->pivot->enabled,
            ]);

        $overrides = $this->staffMember->permissionOverrides()
            ->select(['permissions.id', 'permissions.code'])
            ->get()
            ->mapWithKeys(fn (Permission $permission): array => [
                $permission->id => (bool) $permission->pivot->enabled,
            ]);

        return Permission::query()
            ->select(['id', 'code', 'name', 'sort_order'])
            ->orderBy('sort_order')
            ->get()
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

                return [
                    'id' => $permission->id,
                    'code' => $permission->code,
                    'name' => $permission->name,
                    'is_critical' => $systemPermission?->isCritical() ?? false,
                    'role_default' => $roleDefault,
                    'role_default_label' => $roleDefault ? __('Role allows') : __('Role denies'),
                    'override_state' => $overrideState->value,
                    'override_label' => __($overrideState->summaryLabel()),
                    'effective_allowed' => $effectiveAllowed,
                    'effective_label' => $effectiveAllowed ? __('Allowed') : __('Denied'),
                ];
            })
            ->values()
            ->all();
    }

    public function render(): View
    {
        return view('livewire.organizations.staff.permissions');
    }

    private function authorizeStaffManagement(): void
    {
        if (! $this->currentUser()->hasPermission(SystemPermission::ManageStaff, $this->organization)) {
            abort(403);
        }
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
