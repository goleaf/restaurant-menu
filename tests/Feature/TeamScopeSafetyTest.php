<?php

declare(strict_types=1);

use App\Actions\Invitations\AcceptInvitationAction;
use App\Actions\Invitations\CancelInvitationAction;
use App\Actions\Invitations\CreateInvitationAction;
use App\Actions\Invitations\ReissueInvitationAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Staff\AddBranchStaffMemberAction;
use App\Actions\Staff\SetBranchStaffStatusAction;
use App\Actions\Staff\SetOrganizationStaffStatusAction;
use App\Actions\Staff\SyncWaiterAreaAssignmentsAction;
use App\Actions\Staff\UpdateBranchStaffRoleAction;
use App\Actions\Staff\UpdateOrganizationStaffRoleAction;
use App\Actions\Waiter\BuildWaiterDashboardAction;
use App\Enums\AuditLogAction;
use App\Enums\InvitationStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Models\AreaNode;
use App\Models\AreaNodeWaiter;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Invitation;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\Staff\BranchAssignmentQueryService;
use App\Services\Staff\StaffQueryService;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Tests\Support\TeamScopeConcurrencyTasks;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $this->owner = User::factory()->create();
    $this->organization = app(CreateOrganizationAction::class)->handle($this->owner, ['name' => 'Team scope safety']);
    $this->branch = Branch::factory()->for($this->organization)->create();
    $this->otherBranch = Branch::factory()->for($this->organization)->for($this->branch->brand)->create();
    $this->member = OrganizationUser::factory()->forOrganization($this->organization)->forSystemRole(SystemRole::Waiter)->active()->create();
});

test('first restaurant assignment cannot silently replace organization access', function (): void {
    expect($this->member->user->canAccessBranch($this->otherBranch))->toBeTrue();
    expect(fn () => app(AddBranchStaffMemberAction::class)->handle($this->organization, $this->branch, $this->member->role, $this->owner, ['email' => $this->member->user->email]))
        ->toThrow(ValidationException::class);
    expect(BranchUser::query()->where('user_id', $this->member->user_id)->exists())->toBeFalse()
        ->and($this->member->user->fresh()->canAccessBranch($this->otherBranch))->toBeTrue();
});

test('confirmed first assignment uses membership identity and a current scope preview', function (): void {
    $preview = app(BranchAssignmentQueryService::class)->preview($this->organization, $this->branch, $this->member, $this->owner);
    expect($preview['mode'])->toBe('organization')->and($preview['requires_scope_confirmation'])->toBeTrue()
        ->and($preview['lost_ids'])->toBe([$this->otherBranch->id]);
    $this->member->user->update(['email' => 'changed.identity@example.test']);
    $assigned = app(AddBranchStaffMemberAction::class)->handle($this->organization, $this->branch, $this->member->role, $this->owner,
        ['organization_membership_id' => $this->member->id], $preview['fingerprint'], true);
    expect($assigned->id)->toBe($this->member->user_id)
        ->and($assigned->canAccessBranch($this->branch))->toBeTrue()
        ->and($assigned->canAccessBranch($this->otherBranch))->toBeFalse();
});

test('branch limited administrator cannot confirm a scope change outside permitted restaurants', function (): void {
    $admin = User::factory()->create();
    $adminMembership = OrganizationUser::factory()->forOrganization($this->organization)->forUser($admin)->forSystemRole(SystemRole::RestaurantAdmin)->active()->create();
    BranchUser::factory()->forBranch($this->branch)->forUser($admin)->forRole($adminMembership->role)->active()->create();
    $preview = app(BranchAssignmentQueryService::class)->preview($this->organization, $this->branch, $this->member, $admin);
    expect($preview['can_apply'])->toBeFalse()->and($preview['lost_ids'])->toBe([])->and($preview['outside_scope_count'])->toBe(1);
    expect(fn () => app(AddBranchStaffMemberAction::class)->handle($this->organization, $this->branch, $this->member->role, $admin,
        ['organization_membership_id' => $this->member->id], $preview['fingerprint'], true))->toThrow(AuthorizationException::class);
    expect(BranchUser::query()->where('user_id', $this->member->user_id)->exists())->toBeFalse();
});

test('adding a restaurant after scope preview invalidates the confirmed restriction', function (): void {
    $preview = app(BranchAssignmentQueryService::class)->preview($this->organization, $this->branch, $this->member, $this->owner);
    Branch::factory()->for($this->organization)->for($this->branch->brand)->create();
    expect(fn () => app(AddBranchStaffMemberAction::class)->handle($this->organization, $this->branch, $this->member->role, $this->owner,
        ['organization_membership_id' => $this->member->id], $preview['fingerprint'], true))->toThrow(ValidationException::class);
    expect(BranchUser::query()->where('user_id', $this->member->user_id)->exists())->toBeFalse();
});

test('old cancellation confirmation cannot revoke a reissued invitation', function (): void {
    $created = app(CreateInvitationAction::class)->handle($this->organization, $this->member->role, $this->owner, ['email' => 'new.employee@example.test']);
    $version = $created->invitation->credentialVersion();
    $reissued = app(ReissueInvitationAction::class)->handle($this->owner, $this->organization, $created->invitation, $version);
    expect(fn () => app(CancelInvitationAction::class)->handle($this->owner, $this->organization, $reissued->invitation, $version))->toThrow(ValidationException::class);
    expect($reissued->invitation->fresh()->status)->toBe(InvitationStatus::Pending);
});

test('status confirmation detects suspend restore ABA even when the target is currently equal', function (string $scope): void {
    $membership = $scope === 'organization' ? $this->member : BranchUser::factory()->forBranch($this->branch)->forUser($this->member->user)->forRole($this->member->role)->active()->create();
    $action = app($scope === 'organization' ? SetOrganizationStaffStatusAction::class : SetBranchStaffStatusAction::class);
    $action->suspend($membership, $this->owner, 'Review access now.', 0);
    $action->activate($membership->fresh(), $this->owner, 'Review completed.', 1);
    expect(fn () => $action->activate($membership, $this->owner, 'Old confirmation.', 0))->toThrow(ValidationException::class);
    expect($membership->fresh()->status)->toBe(OrganizationUserStatus::Active)
        ->and($membership->fresh()->access_version)->toBe(2);
})->with(['organization', 'branch']);

test('area fingerprint detects a selection changed away and back', function (): void {
    $membership = BranchUser::factory()->forBranch($this->branch)->forUser($this->member->user)->forRole($this->member->role)->active()->create();
    $area = AreaNode::factory()->forBranch($this->branch)->active()->create();
    $snapshot = app(StaffQueryService::class)->assignmentSnapshot($this->branch, $membership);
    $action = app(SyncWaiterAreaAssignmentsAction::class);
    $action->handle($this->branch, $membership, $this->owner, [$area->id]);
    $action->handle($this->branch, $membership, $this->owner, []);
    expect(fn () => $action->handle($this->branch, $membership, $this->owner, [$area->id], $snapshot['fingerprint']))->toThrow(ValidationException::class);
    expect(AreaNodeWaiter::query()->where('user_id', $this->member->user_id)->count())->toBe(0);
});

test('area assignment and its audit roll back together when audit storage rejects the event', function (): void {
    $membership = BranchUser::factory()->forBranch($this->branch)->forUser($this->member->user)->forRole($this->member->role)->active()->create();
    $area = AreaNode::factory()->forBranch($this->branch)->active()->create();
    AuditLog::saving(fn (AuditLog $log): bool => $log->action !== AuditLogAction::StaffPermissionChanged);
    expect(fn () => app(SyncWaiterAreaAssignmentsAction::class)->handle($this->branch, $membership, $this->owner, [$area->id]))->toThrow(RuntimeException::class);
    expect(AreaNodeWaiter::query()->where('user_id', $this->member->user_id)->exists())->toBeFalse()
        ->and($membership->fresh()->access_version)->toBe(0);
});

test('independent sqlite writers cannot apply two competing first assignment previews', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'team-scope-concurrency-');
    $original = config('database.default');
    $connection = config('database.connections.sqlite');
    $connection['database'] = $path;
    try {
        config(['database.default' => 'team_scope_concurrency', 'database.connections.team_scope_concurrency' => $connection]);
        DB::purge('team_scope_concurrency');
        expect($connection['transaction_mode'])->toBe('IMMEDIATE');
        expect(Artisan::call('migrate', ['--database' => 'team_scope_concurrency', '--force' => true]))->toBe(0);
        $this->seed(SystemPermissionsSeeder::class);
        $owner = User::factory()->create();
        $organization = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'Concurrent scope']);
        $firstBranch = Branch::factory()->for($organization)->create();
        $secondBranch = Branch::factory()->for($organization)->for($firstBranch->brand)->create();
        $member = OrganizationUser::factory()->forOrganization($organization)->forSystemRole(SystemRole::Waiter)->active()->create();
        $organizationId = $organization->id;
        $ownerId = $owner->id;
        $memberId = $member->id;
        $tasks = [];
        foreach ([$firstBranch, $secondBranch] as $branch) {
            $branchId = $branch->id;
            $preview = app(BranchAssignmentQueryService::class)->preview($organization, $branch, $member, $owner);
            $fingerprint = $preview['fingerprint'];
            $tasks[] = TeamScopeConcurrencyTasks::assignment(
                $connection, $organizationId, $ownerId, $memberId, $branchId, $fingerprint,
            );
        }
        config(['database.default' => $original]);
        $results = Concurrency::driver('process')->run($tasks, 20);
        config(['database.default' => 'team_scope_concurrency']);
        DB::purge('team_scope_concurrency');
        $states = array_column($results, 'result');
        sort($states);
        expect(array_unique(array_column($results, 'pid')))->toHaveCount(2)
            ->and($states)->toBe(['assigned', 'conflict'])
            ->and(BranchUser::query()->where('user_id', $member->user_id)->count())->toBe(1)
            ->and(OrganizationUser::query()->findOrFail($memberId)->access_version)->toBe(1)
            ->and(AuditLog::query()->where('action', AuditLogAction::StaffRoleChanged->value)->count())->toBe(1);
    } finally {
        config(['database.default' => $original]);
        DB::disconnect('team_scope_concurrency');
        DB::purge('team_scope_concurrency');
        File::delete([$path, $path.'-wal', $path.'-shm']);
        File::delete(glob($path.'.ready.*'));
    }
});

test('invitation creation cannot bypass an existing members inherited restaurant scope preview', function (): void {
    expect(fn () => app(CreateInvitationAction::class)->handle($this->organization, $this->member->role, $this->owner, [
        'email' => $this->member->user->email, 'branch' => $this->branch,
    ]))->toThrow(ValidationException::class);
    expect(Invitation::query()->count())->toBe(0);
});

test('invitation reissue cannot bypass an existing members inherited restaurant scope preview', function (): void {
    $invitation = Invitation::factory()->forOrganization($this->organization)->forRole($this->member->role)->pending()->create([
        'email' => $this->member->user->email, 'brand_id' => $this->branch->brand_id, 'branch_id' => $this->branch->id,
    ]);
    $version = $invitation->credentialVersion();
    expect(fn () => app(ReissueInvitationAction::class)->handle($this->owner, $this->organization, $invitation, $version))->toThrow(ValidationException::class);
    expect($invitation->fresh()->credentialVersion())->toBe($version);
});

test('accepting an old branch invitation cannot silently narrow an existing organization members inherited access', function (): void {
    $invitation = Invitation::factory()->forOrganization($this->organization)->forRole($this->member->role)->pending()->create([
        'email' => $this->member->user->email, 'brand_id' => $this->branch->brand_id, 'branch_id' => $this->branch->id,
    ]);
    expect(fn () => app(AcceptInvitationAction::class)->handle($invitation, $this->member->user))
        ->toThrow(DomainException::class);
    expect($invitation->fresh()->status)->toBe(InvitationStatus::Pending)
        ->and(BranchUser::query()->where('user_id', $this->member->user_id)->exists())->toBeFalse()
        ->and($this->member->user->fresh()->canAccessBranch($this->otherBranch))->toBeTrue();
});

test('area preview becomes stale when organization participation changes away and back', function (): void {
    $membership = BranchUser::factory()->forBranch($this->branch)->forUser($this->member->user)->forRole($this->member->role)->active()->create();
    $area = AreaNode::factory()->forBranch($this->branch)->active()->create();
    $snapshot = app(StaffQueryService::class)->assignmentSnapshot($this->branch, $membership);
    $status = app(SetOrganizationStaffStatusAction::class);
    $status->suspend($this->member, $this->owner, 'Organization access review.', 0);
    $status->activate($this->member->fresh(), $this->owner, 'Organization review completed.', 1);
    expect(fn () => app(SyncWaiterAreaAssignmentsAction::class)->handle($this->branch, $membership, $this->owner, [$area->id], $snapshot['fingerprint']))
        ->toThrow(ValidationException::class);
    expect(AreaNodeWaiter::query()->where('user_id', $this->member->user_id)->exists())->toBeFalse();
});

test('archiving the last selected area preserves restricted coverage until an explicit clear', function (): void {
    $membership = BranchUser::factory()->forBranch($this->branch)->forUser($this->member->user)->forRole($this->member->role)->active()->create();
    $area = AreaNode::factory()->forBranch($this->branch)->active()->create();
    $otherArea = AreaNode::factory()->forBranch($this->branch)->active()->create();
    ServicePoint::factory()->forBranch($this->branch)->for($otherArea, 'areaNode')->create();
    $action = app(SyncWaiterAreaAssignmentsAction::class);
    $action->handle($this->branch, $membership, $this->owner, [$area->id]);
    $area->delete();
    $dashboard = app(BuildWaiterDashboardAction::class);
    expect($dashboard->handle($this->member->user, selectedBranchId: $this->branch->id)['service_point_count'])->toBe(0)
        ->and(app(StaffQueryService::class)->assignmentSnapshot($this->branch, $membership)['ids'])->toBe([$area->id]);
    $action->handle($this->branch, $membership, $this->owner, []);
    expect($dashboard->handle($this->member->user, selectedBranchId: $this->branch->id)['service_point_count'])->toBe(1);
});

test('assignment audit failure preserves inherited access and its membership version', function (): void {
    $preview = app(BranchAssignmentQueryService::class)->preview($this->organization, $this->branch, $this->member, $this->owner);
    AuditLog::saving(fn (AuditLog $log): bool => $log->action !== AuditLogAction::StaffRoleChanged);
    expect(fn () => app(AddBranchStaffMemberAction::class)->handle($this->organization, $this->branch, $this->member->role, $this->owner,
        ['organization_membership_id' => $this->member->id], $preview['fingerprint'], true))->toThrow(RuntimeException::class);
    expect(BranchUser::query()->where('user_id', $this->member->user_id)->exists())->toBeFalse()
        ->and($this->member->fresh()->access_version)->toBe(0)
        ->and($this->member->user->fresh()->canAccessBranch($this->otherBranch))->toBeTrue();
});

test('restaurant restoration does not restore suspended organization participation', function (): void {
    $membership = BranchUser::factory()->forBranch($this->branch)->forUser($this->member->user)->forRole($this->member->role)->active()->create();
    app(SetBranchStaffStatusAction::class)->suspend($membership, $this->owner, 'Review restaurant access.', 0);
    app(SetOrganizationStaffStatusAction::class)->suspend($this->member, $this->owner, 'Review organization access.', 0);
    app(SetBranchStaffStatusAction::class)->activate($membership->fresh(), $this->owner, 'Restaurant review complete.', 1);
    expect($membership->fresh()->status)->toBe(OrganizationUserStatus::Active)
        ->and($this->member->fresh()->status)->toBe(OrganizationUserStatus::Suspended)
        ->and($this->member->user->fresh()->canAccessBranch($this->branch))->toBeFalse();
});

test('role confirmation detects change away and back before treating the selected role as unchanged', function (string $scope): void {
    $membership = $scope === 'organization' ? $this->member : BranchUser::factory()->forBranch($this->branch)->forUser($this->member->user)->forRole($this->member->role)->active()->create();
    $role = $this->member->role;
    $otherRole = Role::query()->where('code', SystemRole::Cook->value)->firstOrFail();
    $action = app($scope === 'organization' ? UpdateOrganizationStaffRoleAction::class : UpdateBranchStaffRoleAction::class);
    $context = $scope === 'organization' ? $this->organization : $this->branch;
    $action->handle($this->owner, $context, $membership, $otherRole, 'Assign kitchen duties.', 0);
    $action->handle($this->owner, $context, $membership->fresh(), $role, 'Return to waiter duties.', 1);
    expect(fn () => $action->handle($this->owner, $context, $membership, $role, 'Old role confirmation.', 0))->toThrow(ValidationException::class);
    expect($membership->fresh()->role_id)->toBe($role->id)->and($membership->fresh()->access_version)->toBe(2);
})->with(['organization', 'branch']);

test('an unchanged role still authorizes the protected target identity', function (string $scope): void {
    $membership = $scope === 'organization' ? $this->member : BranchUser::factory()->forBranch($this->branch)->forUser($this->member->user)->forRole($this->member->role)->active()->create();
    $this->member->user->roles()->attach(Role::query()->where('code', SystemRole::Superadmin->value)->firstOrFail());
    $action = app($scope === 'organization' ? UpdateOrganizationStaffRoleAction::class : UpdateBranchStaffRoleAction::class);
    $context = $scope === 'organization' ? $this->organization : $this->branch;
    expect(fn () => $action->handle($this->owner, $context, $membership, $this->member->role, 'Unchanged protected role.', 0))->toThrow(AuthorizationException::class);
})->with(['organization', 'branch']);
