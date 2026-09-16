<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\SystemRole;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\PermissionUserOverride;
use App\Models\Role;
use App\Models\User;
use App\Support\DemoLogin\DemoAccountCatalog;
use App\Support\DemoLogin\DemoEnvironment;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Hash;

/**
 * @phpstan-type DirectoryRow array{name: string, email: string, password: ?string, roles: list<string>, companies: list<string>, grants: list<array{scope: string, permissions: list<string>}>, overrides: list<array{scope: string, permission: string, enabled: bool}>}
 */
final class BuildLocalLoginDirectoryAction
{
    public function __construct(
        private readonly Application $application,
        private readonly DemoEnvironment $environment,
    ) {}

    /** @return Paginator<int, covariant DirectoryRow>|null */
    public function handle(Request $request): ?Paginator
    {
        if (! $this->application->environment('local') || config('app.env') !== 'local'
            || ! $this->environment->allowsRequest($request)) {
            return null;
        }

        $users = User::query()
            ->select(['id', 'name', 'email', 'password'])
            ->with([
                'roles:id,code',
                'roles.permissions:id,code',
                'organizationMemberships:id,user_id,organization_id,role_id,status',
                'organizationMemberships.organization' => fn ($query) => $query->withTrashed()->select(['id', 'name']),
                'organizationMemberships.role:id,code',
                'organizationMemberships.role.permissions:id,code',
                'ownedOrganizations' => fn ($query) => $query->withTrashed()->select(['id', 'owner_user_id', 'name']),
                'permissionOverrideRecords:id,user_id,permission_id,organization_id,scope_key,enabled',
                'permissionOverrideRecords.permission:id,code',
                'permissionOverrideRecords.organization' => fn ($query) => $query->withTrashed()->select(['id', 'name']),
            ])
            ->orderBy('id')
            ->simplePaginate(25, pageName: 'users_page')
            ->withQueryString();

        return $users->through(fn (User $user): array => $this->row($user));
    }

    /** @return DirectoryRow */
    private function row(User $user): array
    {
        $grants = $user->roles->map(fn (Role $role): array => $this->roleGrants($role, __('local_login.account_roles')));
        $companies = $user->organizationMemberships->map(fn (OrganizationUser $membership): string => ($membership->organization->name ?? __('local_login.none')).' · '.$membership->role->code->localizedLabel().' · '.$membership->status->localizedLabel());

        foreach ($user->organizationMemberships as $membership) {
            $grants->push($this->roleGrants($membership->role, $membership->organization->name ?? __('local_login.none')));
        }

        $ownedWithoutMembership = $user->ownedOrganizations->whereNotIn('id', $user->organizationMemberships->pluck('organization_id'));
        $companies = $companies->concat($ownedWithoutMembership->map(fn (Organization $organization): string => $organization->name.' · '.SystemRole::Owner->localizedLabel()));

        return [
            'name' => $user->name,
            'email' => $user->email,
            'password' => $this->demoPassword($user),
            'roles' => $user->roles->map(fn (Role $role): string => $role->code->localizedLabel())->values()->all(),
            'companies' => $companies->values()->all(),
            'grants' => $grants->values()->all(),
            'overrides' => $user->permissionOverrideRecords->map(fn (PermissionUserOverride $override): array => [
                'scope' => $override->organization->name ?? __('local_login.legacy_scope'),
                'permission' => $override->permission->code,
                'enabled' => $override->enabled,
            ])->values()->all(),
        ];
    }

    /** @return array{scope: string, permissions: list<string>} */
    private function roleGrants(Role $role, string $scope): array
    {
        return [
            'scope' => $scope.' · '.$role->code->localizedLabel(),
            'permissions' => $role->code === SystemRole::Superadmin
                ? [__('local_login.all_permissions')]
                : $role->permissions->where('pivot.enabled', true)
                    ->pluck('code')->sort()->values()->all(),
        ];
    }

    private function demoPassword(User $user): ?string
    {
        $password = config('demo-login.password');
        if (! is_string($password) || $password === '') {
            return null;
        }

        foreach (DemoAccountCatalog::accounts() as $identity) {
            if ($user->email === $identity['email'] && $user->hasSystemRole($identity['role'])) {
                return Hash::check($password, $user->password) ? $password : null;
            }
        }

        return null;
    }
}
