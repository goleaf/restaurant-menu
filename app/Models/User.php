<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\OrganizationSubscriptionStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\SupportedLocale;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password', 'locale'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return HasMany<Organization, $this>
     */
    public function ownedOrganizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'owner_user_id');
    }

    /** @return HasMany<RestaurantOnboarding, $this> */
    public function restaurantOnboardings(): HasMany
    {
        return $this->hasMany(RestaurantOnboarding::class);
    }

    /**
     * @return BelongsToMany<Organization, $this>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_users')
            ->withPivot(['role_id', 'status', 'joined_at', 'invited_by_user_id'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<OrganizationUser, $this>
     */
    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationUser::class);
    }

    /**
     * @return HasMany<BranchUser, $this>
     */
    public function branchAssignments(): HasMany
    {
        return $this->hasMany(BranchUser::class);
    }

    /**
     * @return HasMany<AreaNodeWaiter, $this>
     */
    public function areaNodeAssignments(): HasMany
    {
        return $this->hasMany(AreaNodeWaiter::class);
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function sentInvitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'invited_by_user_id');
    }

    /**
     * @return HasMany<TableSession, $this>
     */
    public function openedTableSessions(): HasMany
    {
        return $this->hasMany(TableSession::class, 'opened_by_user_id');
    }

    /**
     * @return HasMany<TableSession, $this>
     */
    public function closedTableSessions(): HasMany
    {
        return $this->hasMany(TableSession::class, 'closed_by_user_id');
    }

    /**
     * @return HasMany<OrderStatusLog, $this>
     */
    public function orderStatusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class, 'actor_user_id')
            ->orderBy('occurred_at')
            ->orderBy('id');
    }

    /**
     * @return HasMany<WaiterCall, $this>
     */
    public function handledWaiterCalls(): HasMany
    {
        return $this->hasMany(WaiterCall::class, 'handled_by_user_id')
            ->orderBy('handled_at')
            ->orderBy('id');
    }

    /**
     * @return HasMany<ManualPayment, $this>
     */
    public function manualPayments(): HasMany
    {
        return $this->hasMany(ManualPayment::class, 'recorded_by_user_id')
            ->orderBy('paid_at')
            ->orderBy('id');
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<Permission, $this, PermissionUserOverride, 'pivot'>
     */
    public function permissionOverrides(?int $organizationId = null): BelongsToMany
    {
        $relation = $this->belongsToMany(Permission::class, 'permission_user_overrides')
            ->using(PermissionUserOverride::class)
            ->wherePivot('scope_key', $organizationId === null ? 'legacy' : 'organization:'.$organizationId)
            ->withPivotValue('scope_key', $organizationId === null ? 'legacy' : 'organization:'.$organizationId)
            ->withPivot(['enabled', 'organization_id', 'scope_key'])
            ->withTimestamps();

        if ($organizationId !== null) {
            $relation->withPivotValue('organization_id', $organizationId);
        }

        return $relation;
    }

    /** @return HasMany<PermissionUserOverride, $this> */
    public function permissionOverrideRecords(): HasMany
    {
        return $this->hasMany(PermissionUserOverride::class);
    }

    public function hasPermission(SystemPermission|string $permission, Organization|int|null $organization = null): bool
    {
        if ($this->isSuperadmin()) {
            return true;
        }

        $permissionCode = SystemPermission::resolveCode($permission);

        if ($organization !== null) {
            return $this->hasOrganizationPermission($organization, $permissionCode);
        }

        $override = $this->permissionOverrides()
            ->where('permissions.code', $permissionCode)
            ->first();

        if ($override instanceof Permission) {
            return (bool) $override->pivot->enabled;
        }

        return $this->roles()
            ->whereHas('permissions', function ($query) use ($permissionCode): void {
                $query
                    ->where('permissions.code', $permissionCode)
                    ->where('permission_role.enabled', true);
            })
            ->exists();
    }

    public function canAccessOrganization(Organization|int $organization, bool $withTrashed = false): bool
    {
        if ($this->isSuperadmin()) {
            return true;
        }

        return $this->organizationHasActiveSubscription($organization, $withTrashed)
            && $this->activeOrganizationMembershipQuery($organization)->exists();
    }

    public function canAccessBranch(
        Branch|int $branch,
        Organization|int|null $organization = null,
        bool $withTrashed = false,
    ): bool {
        $branch = $branch instanceof Branch
            ? $branch
            : Branch::query()
                ->when($withTrashed, fn ($query) => $query->withTrashed())
                ->select(['id', 'organization_id'])
                ->whereKey($branch)
                ->first();

        if (! $branch instanceof Branch) {
            return false;
        }

        $organizationId = $organization instanceof Organization ? $organization->id : $organization;

        if ($organizationId !== null && (int) $branch->organization_id !== (int) $organizationId) {
            return false;
        }

        $organizationId = (int) $branch->organization_id;

        if (! $this->canAccessOrganization($organizationId, $withTrashed)) {
            return false;
        }

        $branches = Branch::query()
            ->select(['id'])
            ->whereKey($branch->id)
            ->where('organization_id', $organizationId)
            ->when(! $withTrashed, fn (Builder $query): Builder => $query->whereHas('brand', fn (Builder $brand): Builder => $brand
                ->whereNull('brands.deleted_at')->whereColumn('brands.organization_id', 'branches.organization_id')));

        if ($this->isSuperadmin()) {
            return $branches
                ->when($withTrashed, fn (Builder $query): Builder => $query->withTrashed())
                ->exists();
        }

        $assignments = $this->branchAssignments()
            ->select(['branch_users.id'])
            ->where('organization_id', $organizationId)
            ->getQuery();

        return $branches
            ->withTrashed()
            ->where(function (Builder $query) use ($assignments, $branch, $withTrashed): void {
                $query
                    ->whereExists((clone $assignments)
                        ->where('branch_id', $branch->id)
                        ->where('status', OrganizationUserStatus::Active->value))
                    ->orWhere(function (Builder $fallback) use ($assignments, $withTrashed): void {
                        $fallback
                            ->whereNotExists($assignments)
                            ->when(! $withTrashed, fn (Builder $query): Builder => $query->whereNull('branches.deleted_at'));
                    });
            })
            ->exists();
    }

    /**
     * @return Collection<int, int>
     */
    public function accessibleBranchIdsForOrganization(
        Organization|int $organization,
        bool $withTrashed = false,
    ): Collection {
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;

        if (! $this->canAccessOrganization($organizationId, $withTrashed)) {
            return collect();
        }

        if ($this->isSuperadmin()) {
            return Branch::query()
                ->when($withTrashed, fn ($query) => $query->withTrashed())
                ->select(['id', 'organization_id'])
                ->where('organization_id', $organizationId)
                ->orderBy('id')
                ->pluck('id');
        }

        $branchAssignments = $this->branchAssignments()
            ->select(['id', 'organization_id', 'branch_id', 'user_id', 'status'])
            ->where('organization_id', $organizationId)
            ->orderBy('branch_id')
            ->get();

        if ($branchAssignments->isNotEmpty()) {
            return $branchAssignments
                ->filter(fn (BranchUser $assignment): bool => $assignment->status === OrganizationUserStatus::Active)
                ->pluck('branch_id')
                ->map(fn ($branchId): int => (int) $branchId)
                ->unique()
                ->values();
        }

        return Branch::query()
            ->when($withTrashed, fn ($query) => $query->withTrashed())
            ->select(['id', 'organization_id'])
            ->where('organization_id', $organizationId)
            ->orderBy('id')
            ->pluck('id');
    }

    public function hasSystemRole(SystemRole|string $role): bool
    {
        $systemRole = $role instanceof SystemRole ? $role : SystemRole::from($role);

        if ($this->relationLoaded('roles')) {
            return $this->roles->contains(
                fn (Role $currentRole): bool => $currentRole->code === $systemRole,
            );
        }

        return $this->roles()
            ->where('roles.code', $systemRole->value)
            ->exists();
    }

    public function isSuperadmin(): bool
    {
        return $this->hasSystemRole(SystemRole::Superadmin);
    }

    public function preferredLocale(): string
    {
        return SupportedLocale::normalize($this->locale);
    }

    public function hasOrganizationRole(
        Organization|int $organization,
        SystemRole|string $role,
        bool $withTrashed = false,
    ): bool {
        if (! $this->isSuperadmin() && ! $this->organizationHasActiveSubscription($organization, $withTrashed)) {
            return false;
        }

        $roleCode = $role instanceof SystemRole ? $role->value : SystemRole::from($role)->value;

        return $this->activeOrganizationMembershipQuery($organization)
            ->whereHas('role', function ($query) use ($roleCode): void {
                $query->where('roles.code', $roleCode);
            })
            ->exists();
    }

    public function canManageOrganizationBrands(Organization|int $organization): bool
    {
        return $this->isSuperadmin()
            || $this->hasOrganizationRole($organization, SystemRole::Owner)
            || $this->hasOrganizationRole($organization, SystemRole::Director);
    }

    public function canManageOrganizationBranches(Organization|int $organization): bool
    {
        return $this->hasPermission(SystemPermission::ManageBranches, $organization);
    }

    private function hasOrganizationPermission(Organization|int $organization, string $permissionCode): bool
    {
        if (! $this->canAccessOrganization($organization)) {
            return false;
        }
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;
        $rows = PermissionUserOverride::query()->select(['id', 'permission_id', 'organization_id', 'scope_key', 'enabled'])
            ->where('user_id', $this->id)->whereHas('permission', fn ($query) => $query->where('code', $permissionCode))
            ->where(fn (Builder $query) => $query->whereNull('organization_id')->orWhere('organization_id', $organizationId))->get();
        if ($rows->isNotEmpty()) {
            $singleOrganization = $rows->contains(fn (PermissionUserOverride $row): bool => $row->organization_id === null && $row->enabled)
                && $this->organizationMemberships()->count() === 1;
            $overrides = PermissionUserOverride::effectiveForOrganization($rows, $organizationId, $singleOrganization);
            if ($overrides->isNotEmpty()) {
                return (bool) $overrides->first();
            }
        }

        return $this->activeOrganizationMembershipQuery($organizationId)
            ->whereHas('role.permissions', fn ($query) => $query->where('permissions.code', $permissionCode)->where('permission_role.enabled', true))->exists();
    }

    /**
     * The same decisions power server authorization and the context access explanation.
     *
     * @param  list<string>  $codes
     * @return array<string, array{allowed: bool, source: 'superadmin'|'inactive_membership'|'scope_restricted'|'role'|'explicit_allow'|'explicit_deny'|'legacy'}>
     */
    public function organizationPermissionDecisions(Organization|int $organization, array $codes): array
    {
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;
        if ($this->isSuperadmin()) {
            return array_fill_keys($codes, ['allowed' => true, 'source' => 'superadmin']);
        }
        $membership = OrganizationUser::query()->select(['id', 'role_id', 'status'])
            ->where('organization_id', $organizationId)->where('user_id', $this->id)->first();
        if (! $membership instanceof OrganizationUser || $membership->status !== OrganizationUserStatus::Active) {
            return array_fill_keys($codes, ['allowed' => false, 'source' => 'inactive_membership']);
        }
        if (! $this->canAccessOrganization($organizationId)) {
            return array_fill_keys($codes, ['allowed' => false, 'source' => 'scope_restricted']);
        }
        $permissions = Permission::query()->select(['id', 'code'])
            ->whereIn('code', $codes)->get();
        $defaults = PermissionRole::query()->select(['permission_id', 'enabled'])
            ->where('role_id', $membership->role_id)->whereIn('permission_id', $permissions->modelKeys())
            ->pluck('enabled', 'permission_id');
        $rows = PermissionUserOverride::query()->select(['id', 'permission_id', 'organization_id', 'scope_key', 'enabled'])
            ->where('user_id', $this->id)->whereIn('permission_id', $permissions->modelKeys())
            ->where(fn (Builder $query) => $query->whereNull('organization_id')->orWhere('organization_id', $organizationId))->get();
        $singleOrganization = $this->organizationMemberships()->count() === 1;
        $overrides = PermissionUserOverride::effectiveForOrganization($rows, $organizationId, $singleOrganization);
        $scoped = $rows->where('organization_id', $organizationId)->keyBy('permission_id');
        $result = array_fill_keys($codes, ['allowed' => false, 'source' => 'role']);
        foreach ($permissions as $permission) {
            $result[$permission->code] = [
                'allowed' => $overrides->has($permission->id) ? (bool) $overrides[$permission->id] : (bool) $defaults->get($permission->id, false),
                'source' => $scoped->has($permission->id) ? ($overrides->get($permission->id) ? 'explicit_allow' : 'explicit_deny')
                    : ($overrides->has($permission->id) ? 'legacy' : 'role'),
            ];
        }

        return $result;
    }

    private function activeOrganizationMembershipQuery(Organization|int $organization): HasMany
    {
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;

        return $this->organizationMemberships()
            ->where('organization_id', $organizationId)
            ->where('status', OrganizationUserStatus::Active->value);
    }

    private function organizationHasActiveSubscription(
        Organization|int $organization,
        bool $withTrashed = false,
    ): bool {
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;

        return Organization::query()
            ->when($withTrashed, fn ($query) => $query->withTrashed())
            ->whereKey($organizationId)
            ->where(function ($query): void {
                $query
                    ->whereDoesntHave('subscription')
                    ->orWhereHas('subscription', function ($subscriptionQuery): void {
                        $subscriptionQuery->where('status', OrganizationSubscriptionStatus::Active->value);
                    });
            })
            ->exists();
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }
}
