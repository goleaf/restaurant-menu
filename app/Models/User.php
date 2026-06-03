<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
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

    /**
     * @return BelongsToMany<Organization, $this>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_users')
            ->using(OrganizationUser::class)
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
     * @return HasMany<Invitation, $this>
     */
    public function sentInvitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'invited_by_user_id');
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
     * @return BelongsToMany<Permission, $this>
     */
    public function permissionOverrides(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_user_overrides')
            ->using(PermissionUserOverride::class)
            ->withPivot('enabled')
            ->withTimestamps();
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

    public function canAccessOrganization(Organization|int $organization): bool
    {
        if ($this->isSuperadmin()) {
            return true;
        }

        return $this->activeOrganizationMembershipQuery($organization)
            ->exists();
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

    public function hasOrganizationRole(Organization|int $organization, SystemRole|string $role): bool
    {
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
        return $this->canManageOrganizationBrands($organization)
            || $this->hasPermission(SystemPermission::ManageBranches, $organization);
    }

    private function hasOrganizationPermission(Organization|int $organization, string $permissionCode): bool
    {
        if (! $this->canAccessOrganization($organization)) {
            return false;
        }

        $override = $this->permissionOverrides()
            ->where('permissions.code', $permissionCode)
            ->first();

        if ($override instanceof Permission) {
            return (bool) $override->pivot->enabled;
        }

        return $this->activeOrganizationMembershipQuery($organization)
            ->whereHas('role.permissions', function ($query) use ($permissionCode): void {
                $query
                    ->where('permissions.code', $permissionCode)
                    ->where('permission_role.enabled', true);
            })
            ->exists();
    }

    private function activeOrganizationMembershipQuery(Organization|int $organization): HasMany
    {
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;

        return $this->organizationMemberships()
            ->where('organization_id', $organizationId)
            ->where('status', OrganizationUserStatus::Active->value);
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
