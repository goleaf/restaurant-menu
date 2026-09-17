<?php

declare(strict_types=1);

use App\Actions\Kitchen\ResolveKitchenAccessibleDepartmentIdsAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Staff\ApplyPermissionDraftAction;
use App\Actions\Staff\SetUserPermissionOverrideAction;
use App\Actions\Staff\UpdateBranchStaffRoleAction;
use App\Actions\Staff\UpdateOrganizationStaffRoleAction;
use App\Enums\AuditLogAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\PermissionOverrideState;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Forms\Team\PermissionDraftForm;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\KitchenDepartment;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\PermissionUserOverride;
use App\Models\Role;
use App\Models\User;
use App\Services\Staff\PermissionQueryService;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Tests\Support\TeamAccessConcurrencyTasks;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
});

test('permission draft preview is read only and applies atomically to one organization', function (): void {
    [$owner, $organization, $member, $branch] = permissionDraftMember();
    $other = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'Other Fictional Organization']);
    OrganizationUser::factory()->forOrganization($other)->forUser($member->user)->forRole($member->role)->active()->create();
    $queries = app(PermissionQueryService::class);
    $snapshot = $queries->draftSnapshot($owner, $organization, $member->user, $branch);
    $permission = Permission::query()->where('code', SystemPermission::ManageMenu->value)->firstOrFail();
    $changes = [['permission_id' => $permission->id, 'state' => 'allow']];
    $preview = $queries->draftPreview($owner, $organization, $member->user, $changes, $snapshot['fingerprint'], $branch);

    expect($preview['changes'][0])->toMatchArray(['permission_id' => $permission->id, 'before' => 'default', 'after' => 'allow', 'projected_allowed' => true])
        ->and(PermissionUserOverride::query()->count())->toBe(0);
    app(ApplyPermissionDraftAction::class)->handle($owner, $organization, $member->user, $changes, $snapshot['fingerprint'], branch: $branch);
    expect($member->user->hasPermission(SystemPermission::ManageMenu, $organization))->toBeTrue()
        ->and($member->user->hasPermission(SystemPermission::ManageMenu, $other))->toBeFalse()
        ->and($member->fresh()->access_version)->toBe(1);
});

test('permission drafts reject a change and return to the same state', function (): void {
    [$owner, $organization, $member] = permissionDraftMember();
    $snapshot = app(PermissionQueryService::class)->draftSnapshot($owner, $organization, $member->user);
    $permission = Permission::query()->where('code', SystemPermission::ManageMenu->value)->firstOrFail();
    $action = app(SetUserPermissionOverrideAction::class);
    $action->handle($member->user, $permission, PermissionOverrideState::Allow, $owner, $organization->id);
    $action->handle($member->user, $permission, PermissionOverrideState::Default, $owner, $organization->id);

    expect(fn () => app(ApplyPermissionDraftAction::class)->handle($owner, $organization, $member->user, [['permission_id' => $permission->id, 'state' => 'allow']], $snapshot['fingerprint']))
        ->toThrow(ValidationException::class)
        ->and($member->fresh()->access_version)->toBe(2)
        ->and($member->user->hasPermission(SystemPermission::ManageMenu, $organization))->toBeFalse();
});

test('critical permission drafts require both reason and explicit confirmation', function (): void {
    [$owner, $organization, $member] = permissionDraftMember();
    $snapshot = app(PermissionQueryService::class)->draftSnapshot($owner, $organization, $member->user);
    $permission = Permission::query()->where('code', SystemPermission::ManagePermissions->value)->firstOrFail();
    $changes = [['permission_id' => $permission->id, 'state' => 'allow']];
    $action = app(ApplyPermissionDraftAction::class);
    expect(fn () => $action->handle($owner, $organization, $member->user, $changes, $snapshot['fingerprint']))->toThrow(ValidationException::class)
        ->and(fn () => $action->handle($owner, $organization, $member->user, $changes, $snapshot['fingerprint'], 'Authorized delegation.'))->toThrow(ValidationException::class);
    $action->handle($owner, $organization, $member->user, $changes, $snapshot['fingerprint'], 'Authorized delegation.', true);
    expect($member->user->hasPermission(SystemPermission::ManagePermissions, $organization))->toBeTrue();
});

test('a failed audit rolls back every permission in a draft and its revision', function (): void {
    [$owner, $organization, $member] = permissionDraftMember();
    $snapshot = app(PermissionQueryService::class)->draftSnapshot($owner, $organization, $member->user);
    $permissions = Permission::query()->whereIn('code', [SystemPermission::ManageMenu->value, SystemPermission::ChangePrices->value])->orderBy('id')->get();
    $changes = $permissions->map(fn (Permission $permission): array => ['permission_id' => $permission->id, 'state' => 'allow'])->all();
    $written = 0;
    AuditLog::creating(function () use (&$written): bool {
        return ++$written < 2;
    });
    try {
        expect(fn () => app(ApplyPermissionDraftAction::class)->handle($owner, $organization, $member->user, $changes, $snapshot['fingerprint']))->toThrow(RuntimeException::class);
    } finally {
        AuditLog::flushEventListeners();
    }
    expect(PermissionUserOverride::query()->count())->toBe(0)
        ->and($member->fresh()->access_version)->toBe(0)
        ->and(AuditLog::query()->where('action', AuditLogAction::StaffPermissionChanged)->count())->toBe(0);
});

test('branch restrictions and inactive organization membership dominate permission preview', function (): void {
    [$owner, $organization, $member, $branch] = permissionDraftMember();
    BranchUser::factory()->forOrganization($organization)->forBranch($branch)->forUser($member->user)->forRole($member->role)->create(['status' => OrganizationUserStatus::Suspended]);
    $snapshot = app(PermissionQueryService::class)->draftSnapshot($owner, $organization, $member->user, $branch);
    expect($snapshot['branch_accessible'])->toBeFalse()
        ->and($snapshot['decisions'][SystemPermission::ViewOrders->value])->toBe(['allowed' => false, 'source' => 'branch_restricted']);
    $member->forceFill(['status' => OrganizationUserStatus::Suspended])->save();
    $snapshot = app(PermissionQueryService::class)->draftSnapshot($owner, $organization, $member->user, $branch);
    expect($snapshot['decisions'][SystemPermission::ViewOrders->value])->toBe(['allowed' => false, 'source' => 'inactive_membership']);
});

test('draft applies reauthorize the actor', function (): void {
    [$owner, $organization, $member, $branch] = permissionDraftMember();
    $snapshot = app(PermissionQueryService::class)->draftSnapshot($owner, $organization, $member->user, $branch);
    $permission = Permission::query()->where('code', SystemPermission::ManageMenu->value)->firstOrFail();
    OrganizationUser::query()->where('organization_id', $organization->id)->where('user_id', $owner->id)->update(['status' => OrganizationUserStatus::Suspended]);
    expect(fn () => app(ApplyPermissionDraftAction::class)->handle($owner, $organization, $member->user, [['permission_id' => $permission->id, 'state' => 'allow']], $snapshot['fingerprint'], branch: $branch))->toThrow(AuthorizationException::class);
});

/** @return array{User, Organization, OrganizationUser, Branch} */
function permissionDraftMember(): array
{
    $owner = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'Permission Draft Fictional Organization']);
    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    $member = OrganizationUser::factory()->forOrganization($organization)->forUser(User::factory()->create())->forRole($role)->active()->create();
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();

    return [$owner, $organization, $member->load(['user', 'role']), $branch];
}

test('drafts preserve the last manager and roll back preceding noncritical changes', function (): void {
    [$owner, $organization, $member] = permissionDraftMember();
    $superadmin = User::factory()->create();
    $superadmin->roles()->attach(Role::query()->where('code', SystemRole::Superadmin->value)->firstOrFail());
    $snapshot = app(PermissionQueryService::class)->draftSnapshot($superadmin, $organization, $owner);
    $permissions = Permission::query()->whereIn('code', [SystemPermission::ManageMenu->value, SystemPermission::ManageStaff->value])->orderBy('id')->get();
    $changes = $permissions->map(fn (Permission $permission): array => ['permission_id' => $permission->id, 'state' => 'deny'])->all();
    expect(fn () => app(ApplyPermissionDraftAction::class)->handle($superadmin, $organization, $owner, $changes, $snapshot['fingerprint'], 'Reviewed management withdrawal.', true))->toThrow(ValidationException::class)
        ->and(PermissionUserOverride::query()->count())->toBe(0)
        ->and($owner->hasPermission(SystemPermission::ManageStaff, $organization))->toBeTrue();
});

test('drafts reject self editing and protected platform users', function (): void {
    [$owner, $organization, $member] = permissionDraftMember();
    $queries = app(PermissionQueryService::class);
    $permission = Permission::query()->where('code', SystemPermission::ManageMenu->value)->firstOrFail();
    $changes = [['permission_id' => $permission->id, 'state' => 'deny']];
    $snapshot = $queries->draftSnapshot($owner, $organization, $owner);
    expect(fn () => $queries->draftPreview($owner, $organization, $owner, $changes, $snapshot['fingerprint']))->toThrow(AuthorizationException::class);
    $member->user->roles()->attach(Role::query()->where('code', SystemRole::Superadmin->value)->firstOrFail());
    $snapshot = $queries->draftSnapshot($owner, $organization, $member->user);
    expect(fn () => $queries->draftPreview($owner, $organization, $member->user, $changes, $snapshot['fingerprint']))->toThrow(AuthorizationException::class);
});

test('default removes only the scoped override and previews the preserved legacy deny', function (): void {
    [$owner, $organization, $member] = permissionDraftMember();
    $permission = Permission::query()->where('code', SystemPermission::ViewOrders->value)->firstOrFail();
    PermissionUserOverride::factory()->forUser($member->user)->forPermission($permission)->denied()->create();
    app(SetUserPermissionOverrideAction::class)->handle($member->user, $permission, PermissionOverrideState::Allow, $owner, $organization->id);
    $queries = app(PermissionQueryService::class);
    $snapshot = $queries->draftSnapshot($owner, $organization, $member->user);
    $changes = [['permission_id' => $permission->id, 'state' => 'default']];
    $preview = $queries->draftPreview($owner, $organization, $member->user, $changes, $snapshot['fingerprint']);
    expect($preview['changes'][0])->toMatchArray(['projected_allowed' => false, 'projected_source' => 'legacy']);
    app(ApplyPermissionDraftAction::class)->handle($owner, $organization, $member->user, $changes, $snapshot['fingerprint']);
    expect(PermissionUserOverride::query()->count())->toBe(1)
        ->and($member->user->hasPermission(SystemPermission::ViewOrders, $organization))->toBeFalse();
});

test('drafts reject a changed branch assignment and foreign branch context', function (): void {
    [$owner, $organization, $member, $branch] = permissionDraftMember();
    $queries = app(PermissionQueryService::class);
    $snapshot = $queries->draftSnapshot($owner, $organization, $member->user, $branch);
    $permission = Permission::query()->where('code', SystemPermission::ManageMenu->value)->firstOrFail();
    BranchUser::factory()->forOrganization($organization)->forBranch($branch)->forUser($member->user)->forRole($member->role)->create(['status' => OrganizationUserStatus::Suspended]);
    expect(fn () => $queries->draftPreview($owner, $organization, $member->user, [['permission_id' => $permission->id, 'state' => 'allow']], $snapshot['fingerprint'], $branch))->toThrow(ValidationException::class);
    $foreign = Branch::factory()->create();
    expect(fn () => $queries->draftSnapshot($owner, $organization, $member->user, $foreign))->toThrow(ModelNotFoundException::class);
});

test('duplicate malformed and unknown draft permissions fail before persistence', function (array $changes): void {
    [$owner, $organization, $member] = permissionDraftMember();
    $snapshot = app(PermissionQueryService::class)->draftSnapshot($owner, $organization, $member->user);
    expect(fn () => app(ApplyPermissionDraftAction::class)->handle($owner, $organization, $member->user, $changes, $snapshot['fingerprint']))->toThrow(ValidationException::class)
        ->and(PermissionUserOverride::query()->count())->toBe(0);
})->with([
    'duplicate' => [[['permission_id' => 1, 'state' => 'allow'], ['permission_id' => 1, 'state' => 'deny']]],
    'boolean id' => [[['permission_id' => true, 'state' => 'allow']]],
    'unknown state' => [[['permission_id' => 1, 'state' => 'enabled']]],
    'unknown permission' => [[['permission_id' => 999999, 'state' => 'allow']]],
    'extra keys' => [[['permission_id' => 1, 'state' => 'allow', 'organization_id' => 999999]]],
]);

test('repeating a saved draft does not produce duplicate permission audit', function (): void {
    [$owner, $organization, $member] = permissionDraftMember();
    $snapshot = app(PermissionQueryService::class)->draftSnapshot($owner, $organization, $member->user);
    $permission = Permission::query()->where('code', SystemPermission::ManageMenu->value)->firstOrFail();
    $changes = [['permission_id' => $permission->id, 'state' => 'allow']];
    $action = app(ApplyPermissionDraftAction::class);
    $action->handle($owner, $organization, $member->user, $changes, $snapshot['fingerprint']);
    expect(fn () => $action->handle($owner, $organization, $member->user, $changes, $snapshot['fingerprint']))->toThrow(ValidationException::class)
        ->and(AuditLog::query()->where('action', AuditLogAction::StaffPermissionChanged)->count())->toBe(1)
        ->and($member->fresh()->access_version)->toBe(1);
});

test('settings preview respects the branch policy alternative through branch management', function (): void {
    [$owner, $organization, $member, $branch] = permissionDraftMember();
    $role = Role::query()->where('code', SystemRole::Director->value)->firstOrFail();
    $member->forceFill(['role_id' => $role->id])->save();
    $permission = Permission::query()->where('code', SystemPermission::ManageSettings->value)->firstOrFail();
    $queries = app(PermissionQueryService::class);
    $snapshot = $queries->draftSnapshot($owner, $organization, $member->user, $branch);
    $preview = $queries->draftPreview($owner, $organization, $member->user, [['permission_id' => $permission->id, 'state' => 'deny']], $snapshot['fingerprint'], $branch);
    expect($preview['changes'][0])->toMatchArray(['after' => 'deny', 'projected_allowed' => true, 'projected_source' => 'policy_allowed']);
});

test('independent sqlite permission drafts preserve one winner and the last manager', function (string $kind): void {
    $database = tempnam(sys_get_temp_dir(), 'restaurant-permission-draft-race-');
    $barrier = $database.'-barrier';
    $original = config('database.default');
    $connection = config('database.connections.sqlite');
    $connection['database'] = $database;
    try {
        File::ensureDirectoryExists($barrier);
        config(['database.default' => 'team_permission_concurrency', 'database.connections.team_permission_concurrency' => $connection]);
        DB::purge('team_permission_concurrency');
        expect($connection['transaction_mode'])->toBe('IMMEDIATE');
        expect(Artisan::call('migrate', ['--database' => 'team_permission_concurrency', '--force' => true]))->toBe(0);
        $this->seed(SystemPermissionsSeeder::class);
        [$owner, $organization, $member] = permissionDraftMember();
        $actor = $owner;
        $subjects = [$member->user, $member->user];
        $permission = Permission::query()->where('code', SystemPermission::ManageMenu->value)->firstOrFail();
        $states = ['allow', 'deny'];
        if ($kind === 'last_manager') {
            $actor = User::factory()->create();
            $actor->roles()->attach(Role::query()->where('code', SystemRole::Superadmin->value)->firstOrFail());
            $member->forceFill(['role_id' => Role::query()->where('code', SystemRole::Director->value)->firstOrFail()->id])->save();
            $subjects = [$owner, $member->user];
            $permission = Permission::query()->where('code', SystemPermission::ManageStaff->value)->firstOrFail();
            $states = ['deny', 'deny'];
        }
        $tasks = [];
        foreach ($subjects as $index => $subject) {
            $snapshot = app(PermissionQueryService::class)->draftSnapshot($actor, $organization, $subject);
            $tasks[] = TeamAccessConcurrencyTasks::applyPermissions($connection, $actor->id, $organization->id, $subject->id,
                [['permission_id' => $permission->id, 'state' => $states[$index]]], $snapshot['fingerprint'], $barrier);
        }
        config(['database.default' => $original]);
        $results = Concurrency::driver('process')->run($tasks, 20);
        expect(array_unique(array_column($results, 'pid')))->toHaveCount(2);
        $states = array_column($results, 'result');
        sort($states);
        expect($states)->toBe(['conflict', 'saved']);
        config(['database.default' => 'team_permission_concurrency']);
        DB::purge('team_permission_concurrency');
        expect(PermissionUserOverride::query()->count())->toBe(1)
            ->and(AuditLog::query()->where('action', AuditLogAction::StaffPermissionChanged)->count())->toBe(1);
        if ($kind === 'last_manager') {
            expect($owner->hasPermission(SystemPermission::ManageStaff, $organization) || $member->user->hasPermission(SystemPermission::ManageStaff, $organization))->toBeTrue();
        } else {
            expect($member->fresh()->access_version)->toBe(1);
        }
    } finally {
        config(['database.default' => $original]);
        DB::disconnect('team_permission_concurrency');
        DB::purge('team_permission_concurrency');
        File::deleteDirectory($barrier);
        File::delete([$database, $database.'-wal', $database.'-shm']);
    }
})->with(['same_subject', 'last_manager']);

test('permission snapshot work is bounded to the selected subject', function (): void {
    [$owner, $organization, $member, $branch] = permissionDraftMember();
    $queries = app(PermissionQueryService::class);
    $queries->draftSnapshot($owner, $organization, $member->user, $branch);
    $snapshot = [];
    $before = countDatabaseQueries(function () use ($queries, $owner, $organization, $member, $branch, &$snapshot): void {
        $snapshot = $queries->draftSnapshot($owner, $organization, $member->user, $branch);
    });
    OrganizationUser::factory()->count(30)->forOrganization($organization)->forRole($member->role)->active()->create();
    $after = countDatabaseQueries(fn () => $queries->draftSnapshot($owner, $organization, $member->user, $branch));
    expect($after)->toBe($before)->toBeLessThanOrEqual(160)
        ->and(strlen(json_encode($snapshot)))->toBeLessThan(50000)
        ->and($snapshot['rows'])->toHaveCount(count(SystemPermission::cases()));
});

test('permission form validates original transport values with the correct localized field prefix', function (string $locale): void {
    app()->setLocale($locale);
    $form = new PermissionDraftForm(new class extends Component {}, 'permissionForm');
    foreach ([null, 'allow', ['bad-id' => 'allow'], [1 => false], [1 => 'unknown']] as $invalid) {
        $form->states = $invalid;
        try {
            $form->validatedChanges();
            test()->fail('Malformed permission input passed validation.');
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                expect($key)->toStartWith('permissionForm.states');
                foreach ($messages as $message) {
                    expect($message)->not->toContain('validation.', 'permissions.', ':attribute', ':min', ':max');
                }
            }
        }
    }
    $form->states = [1 => 'allow', 2 => 'default'];
    expect($form->validatedChanges())->toBe([['permission_id' => 1, 'state' => 'allow'], ['permission_id' => 2, 'state' => 'default']]);
    $form->reason = [];
    $form->confirmed = false;
    try {
        $form->validatedConfirmation(true);
        test()->fail('Missing critical confirmation passed validation.');
    } catch (ValidationException $exception) {
        expect(array_keys($exception->errors()))->toContain('permissionForm.reason', 'permissionForm.confirmed');
    }
})->with(['en', 'lt', 'ru']);

test('changing only branch management previews the resulting settings capability without writing a settings override', function (bool $grant): void {
    [$owner, $organization, $member, $branch] = permissionDraftMember();
    $manageBranches = Permission::query()->where('code', SystemPermission::ManageBranches->value)->firstOrFail();
    $settings = Permission::query()->where('code', SystemPermission::ManageSettings->value)->firstOrFail();
    if (! $grant) {
        app(SetUserPermissionOverrideAction::class)->handle($member->user, $manageBranches, PermissionOverrideState::Allow, $owner, $organization->id);
    }
    $queries = app(PermissionQueryService::class);
    $snapshot = $queries->draftSnapshot($owner, $organization, $member->user, $branch);
    $changes = [['permission_id' => $manageBranches->id, 'state' => $grant ? 'allow' : 'deny']];
    $preview = $queries->draftPreview($owner, $organization, $member->user, $changes, $snapshot['fingerprint'], $branch);
    $impact = collect($preview['changes'])->firstWhere('permission_id', $settings->id);
    expect($impact)->toMatchArray(['before' => 'default', 'after' => 'default', 'current_allowed' => ! $grant,
        'projected_allowed' => $grant, 'indirect' => true]);
    app(ApplyPermissionDraftAction::class)->handle($owner, $organization, $member->user, $changes, $snapshot['fingerprint'], 'Reviewed restaurant configuration access.', true, $branch);
    expect($member->user->permissionOverrides($organization->id)->where('permissions.id', $settings->id)->exists())->toBeFalse()
        ->and(Gate::forUser($member->user->fresh())->allows('manageSettings', $branch))->toBe($grant);
})->with([true, false]);

test('organization role previews preserve overrides and do not mutate memberships or audit', function (): void {
    [$owner, $organization, $member, $branch] = permissionDraftMember();
    $director = Role::query()->where('code', SystemRole::Director->value)->firstOrFail();
    $menu = Permission::query()->where('code', SystemPermission::ManageMenu->value)->firstOrFail();
    app(SetUserPermissionOverrideAction::class)->handle($member->user, $menu, PermissionOverrideState::Deny, $owner, $organization->id);
    $auditCount = AuditLog::query()->count();
    $preview = app(PermissionQueryService::class)->rolePreview($owner, $organization, $member->user, $director, $branch);
    $rows = collect($preview['rows'])->keyBy('code');
    expect($preview['scope'])->toBe('organization')
        ->and($rows[SystemPermission::ManageMenu->value])->toMatchArray(['current_role_default' => false, 'projected_role_default' => true, 'override_state' => 'deny', 'projected_allowed' => false, 'projected_source' => 'explicit_deny'])
        ->and($rows[SystemPermission::ManageStaff->value]['projected_allowed'])->toBeTrue()
        ->and($member->fresh()->role_id)->toBe($member->role_id)
        ->and(AuditLog::query()->count())->toBe($auditCount);
});

test('branch role previews retain organization permissions and do not add the kitchen role path', function (): void {
    [$owner, $organization, $member, $branch] = permissionDraftMember();
    $cook = Role::query()->where('code', SystemRole::Cook->value)->firstOrFail();
    BranchUser::factory()->forBranch($branch)->forUser($member->user)->forRole($member->role)->active()->create();
    $preview = app(PermissionQueryService::class)->rolePreview($owner, $organization, $member->user, $cook, $branch, true);
    expect($preview['scope'])->toBe('branch')->and($preview['changes'])->toBe([])
        ->and($preview['role_permission_defaults_change'])->toBeFalse()
        ->and($preview['department_effects'][0])->toMatchArray(['key' => 'kitchen', 'current_role_allows' => false, 'projected_role_allows' => false]);
});

test('organization kitchen role preview explains the role path even when ViewKitchen is denied', function (): void {
    [$owner, $organization, $member, $branch] = permissionDraftMember();
    $cook = Role::query()->where('code', SystemRole::Cook->value)->firstOrFail();
    $viewKitchen = Permission::query()->where('code', SystemPermission::ViewKitchen->value)->firstOrFail();
    PermissionUserOverride::factory()->forUser($member->user)->forPermission($viewKitchen)->forOrganization($organization)->denied()->create();
    $preview = app(PermissionQueryService::class)->rolePreview($owner, $organization, $member->user, $cook, $branch);
    expect($preview['department_effects'][0])->toMatchArray(['key' => 'kitchen', 'current_role_allows' => false, 'projected_role_allows' => true, 'resource_specific' => true]);
});

test('role preview fingerprint rejects proposed role default changes inside the role action', function (): void {
    [$owner, $organization, $member] = permissionDraftMember();
    $director = Role::query()->where('code', SystemRole::Director->value)->firstOrFail();
    $queries = app(PermissionQueryService::class);
    $preview = $queries->rolePreview($owner, $organization, $member->user, $director);
    $menu = Permission::query()->where('code', SystemPermission::ManageMenu->value)->firstOrFail();
    $director->permissions()->updateExistingPivot($menu->id, ['enabled' => false]);
    expect(fn () => app(UpdateOrganizationStaffRoleAction::class)->handle($owner, $organization, $member, $director,
        'Reviewed organization role.', $member->access_version, $preview['fingerprint']))->toThrow(ValidationException::class)
        ->and($member->fresh()->role_id)->toBe($member->role_id);
});

test('confirmed organization role impact matches current policies and the existing kitchen resolver', function (): void {
    [$owner, $organization, $member, $branch] = permissionDraftMember();
    $cook = Role::query()->where('code', SystemRole::Cook->value)->firstOrFail();
    $viewKitchen = Permission::query()->where('code', SystemPermission::ViewKitchen->value)->firstOrFail();
    PermissionUserOverride::factory()->forUser($member->user)->forPermission($viewKitchen)->forOrganization($organization)->denied()->create();
    $department = KitchenDepartment::factory()->for($branch)->active()->create();
    $resolver = app(ResolveKitchenAccessibleDepartmentIdsAction::class);
    expect($resolver->handle($member->user)->contains($department->id))->toBeFalse();
    $queries = app(PermissionQueryService::class);
    $preview = $queries->rolePreview($owner, $organization, $member->user, $cook);
    app(UpdateOrganizationStaffRoleAction::class)->handle($owner, $organization, $member, $cook,
        'Reviewed kitchen responsibilities.', $member->access_version, $preview['fingerprint']);
    expect($preview['department_effects'][0]['projected_eligible'])->toBeTrue()
        ->and($resolver->handle($member->user->fresh())->contains($department->id))->toBeTrue()
        ->and($member->user->fresh()->hasPermission(SystemPermission::ViewKitchen, $organization))->toBeFalse();
    $actual = $queries->draftSnapshot($owner, $organization, $member->user);
    foreach ($preview['rows'] as $row) {
        expect($actual['decisions'][$row['code']]['allowed'])->toBe($row['projected_allowed']);
    }
});

test('branch role impact rechecks organization permissions inside the existing transaction', function (): void {
    [$owner, $organization, $member, $branch] = permissionDraftMember();
    $cook = Role::query()->where('code', SystemRole::Cook->value)->firstOrFail();
    $assignment = BranchUser::factory()->forBranch($branch)->forUser($member->user)->forRole($member->role)->active()->create();
    $queries = app(PermissionQueryService::class);
    $preview = $queries->rolePreview($owner, $organization, $member->user, $cook, $branch, true);
    $menu = Permission::query()->where('code', SystemPermission::ManageMenu->value)->firstOrFail();
    app(SetUserPermissionOverrideAction::class)->handle($member->user, $menu, PermissionOverrideState::Allow, $owner, $organization->id);
    expect(fn () => app(UpdateBranchStaffRoleAction::class)->handle($owner, $branch, $assignment, $cook,
        'Reviewed restaurant assignment.', $assignment->access_version, $preview['fingerprint']))->toThrow(ValidationException::class)
        ->and($assignment->fresh()->role_id)->toBe($member->role_id);
    $fresh = $queries->rolePreview($owner, $organization, $member->user, $cook, $branch, true);
    app(UpdateBranchStaffRoleAction::class)->handle($owner, $branch, $assignment, $cook,
        'Reviewed restaurant assignment.', $assignment->access_version, $fresh['fingerprint']);
    expect($assignment->fresh()->role_id)->toBe($cook->id)
        ->and($member->fresh()->role_id)->toBe($member->role_id);
});

test('reusing a permission read saves duplicate policy queries and invalidates after writes or authority loss', function (): void {
    [$owner, $organization, $member, $branch] = permissionDraftMember();
    $queries = app(PermissionQueryService::class);
    $first = [];
    $cold = countDatabaseQueries(function () use ($queries, $owner, $organization, $member, $branch, &$first): void {
        $first = $queries->draftSnapshot($owner, $organization, $member->user, $branch);
    });
    $reused = [];
    $warm = countDatabaseQueries(function () use ($queries, $owner, $organization, $member, $branch, &$reused): void {
        $reused = $queries->draftSnapshot($owner, $organization, $member->user, $branch);
    });
    expect($warm)->toBeLessThan($cold - 50)->and($reused)->toBe($first);
    $menu = Permission::query()->where('code', SystemPermission::ManageMenu->value)->firstOrFail();
    app(SetUserPermissionOverrideAction::class)->handle($member->user, $menu, PermissionOverrideState::Allow, $owner, $organization->id);
    $changed = $queries->draftSnapshot($owner, $organization, $member->user, $branch);
    expect($changed['fingerprint'])->not->toBe($first['fingerprint'])
        ->and($changed['decisions'][SystemPermission::ManageMenu->value]['allowed'])->toBeTrue();
    OrganizationUser::query()->where('organization_id', $organization->id)->where('user_id', $owner->id)->update(['status' => OrganizationUserStatus::Suspended]);
    expect(fn () => $queries->draftSnapshot($owner, $organization, $member->user, $branch))->toThrow(AuthorizationException::class);
});
