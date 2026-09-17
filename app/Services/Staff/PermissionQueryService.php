<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Actions\Bar\ResolveBarAccessibleDepartmentIdsAction;
use App\Actions\Kitchen\ResolveKitchenAccessibleDepartmentIdsAction;
use App\Enums\PermissionOverrideState;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\PermissionRole;
use App\Models\PermissionUserOverride;
use App\Models\Role;
use App\Models\User;
use App\Support\Validation\PermissionDraftRules;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** @phpstan-type PermissionSnapshot array{fingerprint: string, states: array<int, string>, rows: list<array{id: int, code: string, label: string, description: string, group_key: string, group_label: string, is_critical: bool, role_default: bool, default_allowed: bool, default_source: string, override_state: string, effective_allowed: bool, effective_source: string, effective_reason: string}>, decisions: array<string, array{allowed: bool, source: string}>, branch_accessible: bool, critical_permission_ids: list<int>} */
final class PermissionQueryService
{
    /** @var PermissionSnapshot|null */
    private ?array $cachedSnapshot = null;

    private string $cachedSnapshotKey = '';

    /**
     * Only capabilities for this subject are prepared. Resource policies still apply
     * when a particular order, kitchen ticket, staff member or report is opened.
     *
     * @return PermissionSnapshot
     */
    public function draftSnapshot(User $actor, Organization $organization, User $subject, ?Branch $branch = null): array
    {
        $actor = $actor->fresh(['roles:id,code']) ?? throw new AuthorizationException;
        $subject = $subject->fresh(['roles:id,code']) ?? throw new AuthorizationException;
        $organization = $organization->fresh() ?? abort(404);
        $membership = $this->membership($organization, $subject);
        $gate = Gate::forUser($actor);
        if (! $gate->allows('manageStaff', $organization) && ! $gate->allows('managePermissions', $organization)
            && ! ($actor->is($subject) && $gate->allows('view', $organization))) {
            throw new AuthorizationException;
        }
        if ($branch instanceof Branch) {
            $branch = Branch::query()->whereKey($branch->id)->where('organization_id', $organization->id)->firstOrFail();
            $gate->authorize('view', $branch);
        }
        $fingerprint = $this->draftFingerprint($actor, $organization, $subject, $branch);
        $cacheKey = $fingerprint.':'.app()->getLocale();
        if ($this->cachedSnapshot !== null && $this->cachedSnapshotKey === $cacheKey) {
            return $this->cachedSnapshot;
        }
        $permissions = $this->permissions();
        $defaults = $this->roleDefaults($membership->role_id);
        $overrides = $this->userOverrides($subject, $organization->id);
        $legacyRows = PermissionUserOverride::query()->select(['id', 'permission_id', 'organization_id', 'scope_key', 'enabled'])
            ->where('user_id', $subject->id)->whereNull('organization_id')->where('scope_key', 'legacy')->get();
        $legacy = PermissionUserOverride::effectiveForOrganization($legacyRows, $organization->id, $subject->organizationMemberships()->count() === 1);
        $decisions = $this->organizationAccessDecisions($subject, $organization, $permissions->pluck('code')->all());
        $branchAccessible = $branch instanceof Branch ? Gate::forUser($subject)->allows('view', $branch) : $subject->canAccessOrganization($organization);
        if ($branch instanceof Branch) {
            $decisions = $this->branchAccessDecisions($subject, $branch, $decisions, $branchAccessible);
        }
        $states = [];
        $rows = [];
        $criticalIds = [];
        foreach ($permissions as $permission) {
            $system = SystemPermission::tryFrom($permission->code);
            $state = ! $overrides->has($permission->id) ? PermissionOverrideState::Default
                : ($overrides->get($permission->id) ? PermissionOverrideState::Allow : PermissionOverrideState::Deny);
            $states[$permission->id] = $state->value;
            $decision = $decisions[$permission->code];
            $critical = $system?->isCritical() ?? false;
            if ($critical) {
                $criticalIds[] = $permission->id;
            }
            $rows[] = [
                'id' => $permission->id, 'code' => $permission->code,
                'label' => $system === null ? $permission->name : __($system->uiLabelKey()),
                'description' => $system === null ? __('permissions.descriptions.custom') : __($system->uiDescriptionKey()),
                'group_key' => $system?->uiGroupKey() ?? 'other',
                'group_label' => __($system?->uiGroupLabelKey() ?? 'permissions.groups.other'),
                'is_critical' => $critical, 'role_default' => (bool) $defaults->get($permission->id, false),
                'default_allowed' => (bool) $legacy->get($permission->id, $defaults->get($permission->id, false)),
                'default_source' => $legacy->has($permission->id) ? 'legacy' : 'role',
                'override_state' => $state->value, 'effective_allowed' => $decision['allowed'],
                'effective_source' => $decision['source'], 'effective_reason' => $this->sourceLabel($decision['source']),
            ];
        }
        if (! hash_equals($fingerprint, $this->draftFingerprint($actor, $organization, $subject, $branch))) {
            throw ValidationException::withMessages(['states' => __('permissions.errors.stale_draft')]);
        }

        $this->cachedSnapshotKey = $cacheKey;

        return $this->cachedSnapshot = ['fingerprint' => $fingerprint, 'states' => $states, 'rows' => $rows, 'decisions' => $decisions,
            'branch_accessible' => $branchAccessible, 'critical_permission_ids' => $criticalIds];
    }

    /**
     * Projected capability is distinct from authorization for a concrete resource.
     * No model is changed or saved to obtain this preview.
     *
     * @param  array<array-key, mixed>  $changes
     * @return array{fingerprint: string, changes: list<array{permission_id: int, label: string, group_label: string, before: string, after: string, before_label: string, after_label: string, current_allowed: bool, projected_allowed: bool, projected_source: string, projected_reason: string, is_critical: bool, indirect: bool}>, requires_confirmation: bool}
     */
    public function draftPreview(User $actor, Organization $organization, User $subject, array $changes, string $expectedFingerprint, ?Branch $branch = null): array
    {
        $actor = $actor->fresh(['roles:id,code']) ?? throw new AuthorizationException;
        Gate::forUser($actor)->authorize('managePermissions', $organization);
        Gate::forUser($actor)->authorize('managePermissions', $this->membership($organization, $subject));
        if ($subject->fresh()?->isSuperadmin()) {
            throw new AuthorizationException;
        }
        /** @var array{changes: list<array{permission_id: int|string, state: string}>} $validated */
        $validated = Validator::make(['changes' => $changes], PermissionDraftRules::changes())->validate();
        $snapshot = $this->draftSnapshot($actor, $organization, $subject, $branch);
        if (! hash_equals($snapshot['fingerprint'], $expectedFingerprint)) {
            throw ValidationException::withMessages(['states' => __('permissions.errors.stale_draft')]);
        }
        $rows = array_column($snapshot['rows'], null, 'id');
        $states = $snapshot['states'];
        foreach ($validated['changes'] as $change) {
            $id = (int) $change['permission_id'];
            if (! isset($rows[$id])) {
                throw ValidationException::withMessages(['states' => __('permissions.errors.invalid_state')]);
            }
            $states[$id] = $change['state'];
        }
        $projected = $this->projectedDecisions($snapshot['rows'], $states, $branch);
        $preview = [];
        $critical = false;
        foreach ($rows as $id => $row) {
            $state = PermissionOverrideState::from($states[$id]);
            $decision = $projected[$row['code']];
            $indirect = $row['override_state'] === $state->value;
            if ($indirect && $row['effective_allowed'] === $decision['allowed']) {
                continue;
            }
            $critical = $critical || $row['is_critical'];
            $preview[] = ['permission_id' => $id, 'label' => $row['label'], 'group_label' => $row['group_label'],
                'before' => $row['override_state'], 'after' => $state->value,
                'before_label' => __(PermissionOverrideState::from($row['override_state'])->summaryLabelKey()), 'after_label' => __($state->summaryLabelKey()),
                'current_allowed' => $row['effective_allowed'], 'projected_allowed' => $decision['allowed'],
                'projected_source' => $decision['source'], 'projected_reason' => $this->sourceLabel($decision['source']),
                'is_critical' => $row['is_critical'], 'indirect' => $indirect];
        }

        return ['fingerprint' => $snapshot['fingerprint'], 'changes' => $preview, 'requires_confirmation' => $critical];
    }

    /**
     * Read-only role impact uses the same scoped overrides and policy exceptions as
     * the permission draft. It never impersonates or persists the proposed subject.
     *
     * @return array{fingerprint:string,scope:string,rows:list<array<string,mixed>>,changes:list<array<string,mixed>>,role_permission_defaults_change:bool,removes_area_restrictions:bool,department_effects:list<array<string,mixed>>}
     */
    public function rolePreview(User $actor, Organization $organization, User $subject, Role $proposedRole, ?Branch $branch = null, bool $branchRole = false): array
    {
        $actor = $actor->fresh(['roles:id,code']) ?? throw new AuthorizationException;
        $subject = $subject->fresh(['roles:id,code']) ?? throw new AuthorizationException;
        $membership = $this->membership($organization, $subject);
        $proposedRole = Role::query()->select(['id', 'code', 'name', 'sort_order', 'updated_at'])->whereKey($proposedRole->id)->firstOrFail();
        $currentRole = $membership->role;
        $assignment = null;
        if ($branchRole) {
            abort_unless($branch instanceof Branch && $branch->organization_id === $organization->id, 404);
            $assignment = BranchUser::query()->select(['id', 'role_id'])->with('role:id,code,name,sort_order')
                ->where('organization_id', $organization->id)->where('branch_id', $branch->id)->where('user_id', $subject->id)->firstOrFail();
            $currentRole = $assignment->role;
        }
        if ($actor->is($subject) || $subject->isSuperadmin() || ! $currentRole instanceof Role) {
            throw new AuthorizationException;
        }
        $gate = Gate::forUser($actor);
        $gate->authorize('manageStaff', $branchRole ? $branch : $organization);
        $gate->authorize('assign', [$currentRole, $organization]);
        $gate->authorize('assign', [$proposedRole, $organization]);
        $snapshot = $this->draftSnapshot($actor, $organization, $subject, $branch);
        $roleState = $this->proposedRoleState($proposedRole);
        $nextDefaults = $this->roleDefaults($branchRole ? $membership->role_id : $proposedRole->id);
        $projectedRows = $snapshot['rows'];
        foreach ($projectedRows as &$row) {
            $row['role_default'] = (bool) $nextDefaults->get($row['id'], false);
            if ($row['default_source'] === 'role') {
                $row['default_allowed'] = $row['role_default'];
            }
        }
        unset($row);
        $nextOrganizationRole = $branchRole ? $membership->role->code : $proposedRole->code;
        $projected = $this->projectedDecisions($projectedRows, $snapshot['states'], $branch, $nextOrganizationRole);
        $rows = [];
        $changes = [];
        foreach ($snapshot['rows'] as $row) {
            $decision = $projected[$row['code']];
            $nextDefault = (bool) $nextDefaults->get($row['id'], false);
            $result = ['permission_id' => $row['id'], 'code' => $row['code'], 'label' => $row['label'], 'group_label' => $row['group_label'],
                'current_role_default' => $row['role_default'], 'projected_role_default' => $nextDefault,
                'override_state' => $row['override_state'], 'current_allowed' => $row['effective_allowed'],
                'projected_allowed' => $decision['allowed'], 'projected_source' => $decision['source'],
                'projected_reason' => $this->sourceLabel($decision['source'])];
            $rows[] = $result;
            if ($row['role_default'] !== $nextDefault || $row['effective_allowed'] !== $decision['allowed']) {
                $changes[] = $result;
            }
        }
        $departmentEffects = $this->departmentRoleEffects($membership->role->code, $nextOrganizationRole, $snapshot['decisions'], $projected, $snapshot['branch_accessible']);
        if (! hash_equals($snapshot['fingerprint'], $this->draftFingerprint($actor, $organization, $subject, $branch))
            || $roleState !== $this->proposedRoleState($proposedRole)) {
            throw ValidationException::withMessages(['editingRoleId' => __('staff.errors.stale_membership')]);
        }

        return ['fingerprint' => hash('sha256', $snapshot['fingerprint'].json_encode([$roleState, $branchRole], JSON_THROW_ON_ERROR)),
            'scope' => $branchRole ? 'branch' : 'organization', 'rows' => $rows, 'changes' => $changes,
            'role_permission_defaults_change' => ! $branchRole,
            'removes_area_restrictions' => $assignment instanceof BranchUser && $currentRole->code === SystemRole::Waiter && $proposedRole->code !== SystemRole::Waiter,
            'department_effects' => $departmentEffects];
    }

    public function assertRolePreviewCurrent(User $actor, Organization $organization, User $subject, Role $proposedRole, string $expectedFingerprint, ?Branch $branch = null, bool $branchRole = false): void
    {
        $preview = $this->rolePreview($actor, $organization, $subject, $proposedRole, $branch, $branchRole);
        if (! hash_equals($preview['fingerprint'], $expectedFingerprint)) {
            throw ValidationException::withMessages(['editingRoleId' => __('staff.errors.stale_membership')]);
        }
    }

    /** @return array{role:array<string,mixed>,permissions:array<int,array<string,mixed>>} */
    private function proposedRoleState(Role $role): array
    {
        return ['role' => Role::query()->select(['id', 'code', 'sort_order', 'updated_at'])->whereKey($role->id)->firstOrFail()->toArray(),
            'permissions' => PermissionRole::query()->select(['id', 'role_id', 'permission_id', 'enabled', 'updated_at'])
                ->where('role_id', $role->id)->orderBy('id')->get()->toArray()];
    }

    /**
     * @param  list<array<string,mixed>>  $rows
     * @param  array<int,string>  $states
     * @return array<string,array{allowed:bool,source:string}>
     */
    private function projectedDecisions(array $rows, array $states, ?Branch $branch, ?SystemRole $organizationRole = null): array
    {
        $result = [];
        foreach ($rows as $row) {
            $state = PermissionOverrideState::from($states[$row['id']]);
            $result[$row['code']] = ['allowed' => $state->enabledValue() ?? $row['default_allowed'], 'source' => match ($state) {
                PermissionOverrideState::Allow => 'explicit_allow', PermissionOverrideState::Deny => 'explicit_deny',
                PermissionOverrideState::Default => $row['default_source'],
            }];
        }
        if ($branch instanceof Branch && isset($result[SystemPermission::ManageSettings->value])
            && ($result[SystemPermission::ManageBranches->value]['allowed'] ?? false)) {
            $result[SystemPermission::ManageSettings->value] = ['allowed' => true, 'source' => 'policy_allowed'];
        }
        foreach ($rows as $row) {
            $code = $row['code'];
            if (in_array($row['effective_source'], ['inactive_membership', 'scope_restricted', 'branch_restricted'], true)) {
                $result[$code] = ['allowed' => false, 'source' => $row['effective_source']];
            } elseif ($code === SystemPermission::ViewRestaurant->value || $code === SystemPermission::EditRestaurant->value) {
                $allowed = $code === SystemPermission::EditRestaurant->value && $organizationRole !== null
                    ? $organizationRole === SystemRole::Owner : $row['effective_allowed'];
                $result[$code] = ['allowed' => $allowed, 'source' => $allowed ? 'policy_allowed' : 'policy_denied'];
            }
        }

        return $result;
    }

    /**
     * Eligibility is the shared resolver's role-or-permission entry rule. A concrete
     * active kitchen/bar department and its resource policies are still required.
     *
     * @param  array<string,array{allowed:bool,source:string}>  $current
     * @param  array<string,array{allowed:bool,source:string}>  $projected
     * @return list<array<string,mixed>>
     */
    private function departmentRoleEffects(SystemRole $currentRole, SystemRole $nextRole, array $current, array $projected, bool $accessible): array
    {
        $rows = [];
        foreach (['kitchen' => ResolveKitchenAccessibleDepartmentIdsAction::accessRules(), 'bar' => ResolveBarAccessibleDepartmentIdsAction::accessRules()] as $key => $rules) {
            $currentRoleAllows = in_array($currentRole, $rules['roles'], true);
            $nextRoleAllows = in_array($nextRole, $rules['roles'], true);
            $currentAllowed = $currentRoleAllows;
            $nextAllowed = $nextRoleAllows;
            foreach ($rules['permissions'] as $permission) {
                $currentAllowed = $currentAllowed || ($current[$permission->value]['allowed'] ?? false);
                $nextAllowed = $nextAllowed || ($projected[$permission->value]['allowed'] ?? false);
            }
            $rows[] = ['key' => $key, 'current_role_allows' => $currentRoleAllows, 'projected_role_allows' => $nextRoleAllows,
                'current_eligible' => $accessible && $currentAllowed, 'projected_eligible' => $accessible && $nextAllowed, 'resource_specific' => true];
        }

        return $rows;
    }

    /**
     * Access versions distinguish a change followed by a return to its original value.
     * The complete override/default rows additionally cover pre-existing writers.
     */
    private function draftFingerprint(User $actor, Organization $organization, User $subject, ?Branch $branch): string
    {
        $ids = array_values(array_unique([$actor->id, $subject->id]));
        $memberships = OrganizationUser::query()->select(['id', 'organization_id', 'user_id', 'role_id', 'status', 'access_version'])
            ->whereIn('user_id', $ids)->orderBy('id')->get();
        $roles = PermissionRole::query()->select(['id', 'role_id', 'permission_id', 'enabled', 'updated_at'])
            ->whereIn('role_id', $memberships->where('organization_id', $organization->id)->pluck('role_id'))->orderBy('id')->get();
        $roleDefinitions = Role::query()->select(['id', 'code', 'sort_order', 'updated_at'])
            ->whereIn('id', $memberships->where('organization_id', $organization->id)->pluck('role_id'))->orderBy('id')->get();
        $permissions = Permission::query()->select(['id', 'code', 'updated_at'])->orderBy('id')->get();
        $overrides = PermissionUserOverride::query()->select(['id', 'user_id', 'permission_id', 'organization_id', 'scope_key', 'enabled', 'updated_at'])
            ->whereIn('user_id', $ids)->where(fn ($query) => $query->where('organization_id', $organization->id)->orWhereNull('organization_id'))->orderBy('id')->get();
        $assignments = BranchUser::query()->select(['id', 'user_id', 'organization_id', 'branch_id', 'role_id', 'status', 'access_version'])
            ->whereIn('user_id', $ids)->where('organization_id', $organization->id)->orderBy('id')->get();
        $users = User::query()->select(['id'])->whereIn('id', $ids)->with('roles:id,code,sort_order')->orderBy('id')->get();
        $subscription = OrganizationSubscription::query()->select(['id', 'organization_id', 'status', 'updated_at'])->where('organization_id', $organization->id)->first();
        $branches = Branch::withTrashed()->select(['id', 'organization_id', 'deleted_at', 'updated_at'])->where('organization_id', $organization->id)->orderBy('id')->get();
        $organizationState = Organization::withTrashed()->select(['id', 'deleted_at', 'updated_at'])->whereKey($organization->id)->first();

        return hash('sha256', json_encode([$actor->id, $subject->id, $organization->id, $branch?->id, $memberships->toArray(), $roles->toArray(),
            $overrides->toArray(), $assignments->toArray(), $users->toArray(), $subscription?->toArray(), $branches->toArray(),
            $roleDefinitions->toArray(), $permissions->toArray(), $organizationState?->toArray()], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, array{allowed: bool, source: string}>  $decisions
     * @return array<string, array{allowed: bool, source: string}>
     */
    private function branchAccessDecisions(User $subject, Branch $branch, array $decisions, bool $accessible): array
    {
        if (! $accessible) {
            foreach ($decisions as $code => $decision) {
                if (! in_array($decision['source'], ['inactive_membership', 'scope_restricted'], true)) {
                    $decisions[$code] = ['allowed' => false, 'source' => 'branch_restricted'];
                }
            }

            return $decisions;
        }
        $abilities = [SystemPermission::ManageMenu->value => 'manageMenu', SystemPermission::ChangePrices->value => 'changeMenuPrices',
            SystemPermission::ChangeAvailability->value => 'changeMenuAvailability', SystemPermission::ManageSettings->value => 'manageSettings',
            SystemPermission::ManageStaff->value => 'manageStaff', SystemPermission::ManageZones->value => 'manageZones',
            SystemPermission::ManageServicePoints->value => 'manageServicePoints', SystemPermission::GenerateQr->value => 'generateQr',
            SystemPermission::ExportData->value => 'export'];
        $gate = Gate::forUser($subject);
        foreach ($abilities as $code => $ability) {
            $allowed = $gate->allows($ability, $branch);
            if (isset($decisions[$code]) && $decisions[$code]['allowed'] !== $allowed) {
                $decisions[$code] = ['allowed' => $allowed, 'source' => $allowed ? 'policy_allowed' : 'policy_denied'];
            }
        }

        return $decisions;
    }

    /**
     * Resource policies remain authoritative when a capability is not sufficient.
     *
     * @param  list<string>  $codes
     * @return array<string, array{allowed: bool, source: 'superadmin'|'inactive_membership'|'scope_restricted'|'role'|'explicit_allow'|'explicit_deny'|'legacy'|'policy_allowed'|'policy_denied'}>
     */
    public function organizationAccessDecisions(User $user, Organization $organization, array $codes): array
    {
        $decisions = $user->organizationPermissionDecisions($organization, $codes);
        $abilities = [
            SystemPermission::ViewRestaurant->value => 'view',
            SystemPermission::EditRestaurant->value => 'update',
            SystemPermission::ManageBranches->value => 'manageBranches',
            SystemPermission::ManageStaff->value => 'manageStaff',
            SystemPermission::ManagePermissions->value => 'managePermissions',
        ];
        $gate = Gate::forUser($user);

        foreach (array_intersect_key($abilities, $decisions) as $code => $ability) {
            $response = $gate->inspect($ability, $organization);
            if ($response->allowed() !== $decisions[$code]['allowed']) {
                $decisions[$code] = [
                    'allowed' => $response->allowed(),
                    'source' => $response->allowed() ? 'policy_allowed' : 'policy_denied',
                ];
            }
        }

        return $decisions;
    }

    public function membership(Organization $organization, User $user): OrganizationUser
    {
        return OrganizationUser::query()
            ->select(['id', 'organization_id', 'user_id', 'role_id', 'status', 'access_version', 'joined_at', 'invited_by_user_id', 'created_at', 'updated_at'])
            ->with(['role' => fn ($query) => $query->select(['id', 'code', 'name', 'sort_order'])])
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->firstOrFail();
    }

    public function permission(int $permissionId): Permission
    {
        return Permission::query()
            ->select(['id', 'code', 'name', 'sort_order'])
            ->whereKey($permissionId)
            ->firstOrFail();
    }

    /** @return Collection<int, bool> */
    public function roleDefaults(int $roleId): Collection
    {
        return PermissionRole::query()
            ->select(['permission_id', 'enabled'])
            ->where('role_id', $roleId)
            ->get()
            ->mapWithKeys(fn (PermissionRole $assignment): array => [
                $assignment->permission_id => $assignment->enabled,
            ]);
    }

    /** @return Collection<int, bool> */
    public function userOverrides(User $user, int $organizationId): Collection
    {
        return PermissionUserOverride::query()
            ->select(['permission_id', 'enabled'])
            ->where('user_id', $user->id)
            ->where('organization_id', $organizationId)
            ->where('scope_key', 'organization:'.$organizationId)
            ->get()
            ->mapWithKeys(fn (PermissionUserOverride $override): array => [
                $override->permission_id => $override->enabled,
            ]);
    }

    public function hasLegacyOverrides(User $user): bool
    {
        return PermissionUserOverride::query()->where('user_id', $user->id)
            ->whereNull('organization_id')->where('scope_key', 'legacy')->exists();
    }

    /** @return EloquentCollection<int, Permission> */
    public function permissions(): EloquentCollection
    {
        return Permission::query()
            ->select(['id', 'code', 'name', 'sort_order'])
            ->orderBy('sort_order')
            ->get();
    }

    private function sourceLabel(string $source): string
    {
        return match ($source) {
            'explicit_allow' => __('permissions.sources.explicit_allow'),
            'explicit_deny' => __('permissions.sources.explicit_deny'),
            'inactive_membership' => __('permissions.sources.inactive_membership'),
            'legacy' => __('permissions.sources.legacy'),
            'policy_allowed' => __('permissions.sources.policy_allowed'),
            'policy_denied' => __('permissions.sources.policy_denied'),
            'role' => __('permissions.sources.role'),
            'scope_restricted' => __('permissions.sources.scope_restricted'),
            'superadmin' => __('permissions.sources.superadmin'),
            default => __('permissions.sources.branch_restricted'),
        };
    }
}
