<?php

declare(strict_types=1);

use App\Actions\Invitations\AcceptInvitationAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Staff\RemoveBranchStaffAssignmentAction;
use App\Actions\Staff\SetBranchStaffStatusAction;
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
use App\Models\Order;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;
use App\Services\Staff\BranchAssignmentQueryService;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Tests\Support\TeamScopeConcurrencyTasks;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $this->owner = User::factory()->create();
    $this->organization = app(CreateOrganizationAction::class)->handle($this->owner, ['name' => 'Assignment removal']);
    $this->branch = Branch::factory()->for($this->organization)->create();
    $this->otherBranch = Branch::factory()->for($this->organization)->for($this->branch->brand)->create();
    $this->member = OrganizationUser::factory()->forOrganization($this->organization)->forSystemRole(SystemRole::Waiter)->active()->create();
    $this->assignment = BranchUser::factory()->forBranch($this->branch)->forUser($this->member->user)->forRole($this->member->role)->active()->create();
});

test('removing the last assignment explicitly returns current and future restaurants to organization rules', function (bool $suspended): void {
    if ($suspended) {
        $this->assignment->forceFill(['status' => OrganizationUserStatus::Suspended])->save();
    }
    $preview = app(BranchAssignmentQueryService::class)->removalPreview($this->organization, $this->branch, $this->member, $this->owner);
    expect($preview['operation'])->toBe('return_to_organization')
        ->and($preview['branch_assignment_id'])->toBe($this->assignment->id)
        ->and($preview['access_version'])->toBe(0)
        ->and($preview['requires_scope_confirmation'])->toBeTrue()
        ->and($preview['can_apply'])->toBeTrue()
        ->and($preview['before_ids'])->toBe($suspended ? [] : [$this->branch->id])
        ->and($preview['after_ids'])->toBe([$this->branch->id, $this->otherBranch->id])
        ->and($preview['gained_ids'])->toBe($suspended ? [$this->branch->id, $this->otherBranch->id] : [$this->otherBranch->id]);
    $updated = app(RemoveBranchStaffAssignmentAction::class)->handle($this->organization, $this->branch, $this->member, $this->owner, $preview['fingerprint'], 'Return to organization rules.', true);
    $future = Branch::factory()->for($this->organization)->for($this->branch->brand)->create();
    expect($updated->access_version)->toBe(1)
        ->and(BranchUser::query()->where('user_id', $this->member->user_id)->exists())->toBeFalse()
        ->and($this->member->user->fresh()->canAccessBranch($future))->toBeTrue()
        ->and($this->member->user->fresh()->canAccessBranch($this->otherBranch))->toBeTrue();
    $event = AuditLog::query()->where('action', AuditLogAction::StaffPermissionChanged)->sole();
    expect($event->entity_type)->toBe('organization_user')->and($event->entity_id)->toBe($this->member->id)
        ->and($event->old_values['branch_assignment_id'])->toBe($this->assignment->id)
        ->and($event->new_values['staff_user_id'])->toBe($this->member->user_id)
        ->and($event->new_values['scope'])->toBe('branch_assignment_removed')
        ->and($event->new_values['branch_access_mode'])->toBe('organization');
})->with([false, true]);

test('removing one of several assignments preserves other restaurants zones accounts and order history', function (): void {
    $otherAssignment = BranchUser::factory()->forBranch($this->otherBranch)->forUser($this->member->user)->forRole($this->member->role)->active()->create();
    $otherOrganization = app(CreateOrganizationAction::class)->handle($this->owner, ['name' => 'Independent organization']);
    $otherMember = OrganizationUser::factory()->forOrganization($otherOrganization)->forUser($this->member->user)->forSystemRole(SystemRole::RestaurantAdmin)->active()->create();
    $areas = [];
    foreach ([$this->branch, $this->otherBranch] as $branch) {
        $area = AreaNode::factory()->forBranch($branch)->active()->create();
        $areas[] = AreaNodeWaiter::factory()->create(['organization_id' => $this->organization->id, 'branch_id' => $branch->id, 'area_node_id' => $area->id, 'user_id' => $this->member->user_id]);
    }
    $order = Order::factory()->create(['confirmed_by_user_id' => $this->member->user_id]);
    $userBefore = $this->member->user->getAttributes();
    $preview = app(BranchAssignmentQueryService::class)->removalPreview($this->organization, $this->branch, $this->member, $this->owner);
    expect($preview['operation'])->toBe('remove_assignment')->and($preview['requires_scope_confirmation'])->toBeFalse()
        ->and($preview['after_ids'])->toBe([$this->otherBranch->id])->and($preview['lost_ids'])->toBe([$this->branch->id])->and($preview['area_count'])->toBe(1);
    app(RemoveBranchStaffAssignmentAction::class)->handle($this->organization, $this->branch, $this->member, $this->owner, $preview['fingerprint'], 'Remove this restaurant assignment.', true);
    $future = Branch::factory()->for($this->organization)->for($this->branch->brand)->create();
    expect($this->assignment->fresh())->toBeNull()->and($otherAssignment->fresh()->access_version)->toBe(0)
        ->and($this->member->user->fresh()->canAccessBranch($this->branch))->toBeFalse()
        ->and($this->member->user->fresh()->canAccessBranch($this->otherBranch))->toBeTrue()
        ->and($this->member->user->fresh()->canAccessBranch($future))->toBeFalse()
        ->and($areas[0]->fresh())->toBeNull()->and($areas[1]->fresh())->not->toBeNull()
        ->and($otherMember->fresh()->access_version)->toBe(0)->and($otherMember->fresh()->role_id)->toBe($otherMember->role_id)
        ->and($order->fresh()->getAttributes())->toEqual($order->getAttributes())
        ->and($this->member->user->fresh()->getAttributes())->toBe($userBefore);
});

test('restaurant limited administrator cannot restore hidden restaurants by removing the last assignment', function (): void {
    $admin = User::factory()->create();
    $adminMembership = OrganizationUser::factory()->forOrganization($this->organization)->forUser($admin)->forSystemRole(SystemRole::RestaurantAdmin)->active()->create();
    BranchUser::factory()->forBranch($this->branch)->forUser($admin)->forRole($adminMembership->role)->active()->create();
    $preview = app(BranchAssignmentQueryService::class)->removalPreview($this->organization, $this->branch, $this->member, $admin);
    expect($preview['can_apply'])->toBeFalse()->and($preview['outside_scope_count'])->toBe(1)
        ->and($preview['gained_ids'])->toBe([])->and($preview['gained'])->toBe([])
        ->and(json_encode($preview))->not->toContain($this->otherBranch->name);
    expect(fn () => app(RemoveBranchStaffAssignmentAction::class)->handle($this->organization, $this->branch, $this->member, $admin, $preview['fingerprint'], 'Remove last restaurant.', true))->toThrow(AuthorizationException::class);
    expect($this->assignment->fresh())->not->toBeNull()->and($this->member->fresh()->access_version)->toBe(0);
});

test('local removal can preserve a second assignment outside the administrators scope', function (): void {
    $otherAssignment = BranchUser::factory()->forBranch($this->otherBranch)->forUser($this->member->user)->forRole($this->member->role)->active()->create();
    $admin = User::factory()->create();
    $adminMembership = OrganizationUser::factory()->forOrganization($this->organization)->forUser($admin)->forSystemRole(SystemRole::RestaurantAdmin)->active()->create();
    BranchUser::factory()->forBranch($this->branch)->forUser($admin)->forRole($adminMembership->role)->active()->create();
    $preview = app(BranchAssignmentQueryService::class)->removalPreview($this->organization, $this->branch, $this->member, $admin);
    expect($preview['can_apply'])->toBeTrue()->and($preview['outside_scope_count'])->toBe(0)->and($preview['after'])->toBe([]);
    app(RemoveBranchStaffAssignmentAction::class)->handle($this->organization, $this->branch, $this->member, $admin, $preview['fingerprint'], 'Remove permitted assignment.', true);
    expect($otherAssignment->fresh()->getAttributes())->toEqual($otherAssignment->getAttributes());
});

test('removal fingerprints cannot be substituted with add assignment previews', function (): void {
    $addPreview = app(BranchAssignmentQueryService::class)->preview($this->organization, $this->branch, $this->member, $this->owner);
    expect(fn () => app(RemoveBranchStaffAssignmentAction::class)->handle($this->organization, $this->branch, $this->member, $this->owner, $addPreview['fingerprint'], 'Wrong operation confirmation.', true))->toThrow(ValidationException::class);
    expect($this->assignment->fresh())->not->toBeNull();
});

test('removal detects assignment ABA and changed future organization scope', function (string $change): void {
    $preview = app(BranchAssignmentQueryService::class)->removalPreview($this->organization, $this->branch, $this->member, $this->owner);
    if ($change === 'status') {
        app(SetBranchStaffStatusAction::class)->suspend($this->assignment, $this->owner, 'Pause restaurant participation.', 0);
        app(SetBranchStaffStatusAction::class)->activate($this->assignment->fresh(), $this->owner, 'Restore restaurant participation.', 1);
    } else {
        Branch::factory()->for($this->organization)->for($this->branch->brand)->create();
    }
    expect(fn () => app(RemoveBranchStaffAssignmentAction::class)->handle($this->organization, $this->branch, $this->member, $this->owner, $preview['fingerprint'], 'Old removal confirmation.', true))->toThrow(ValidationException::class);
    expect($this->assignment->fresh())->not->toBeNull();
})->with(['status', 'future branch']);

test('removal rechecks actor authority after preview', function (): void {
    $admin = User::factory()->create();
    $adminMembership = OrganizationUser::factory()->forOrganization($this->organization)->forUser($admin)->forSystemRole(SystemRole::RestaurantAdmin)->active()->create();
    $preview = app(BranchAssignmentQueryService::class)->removalPreview($this->organization, $this->branch, $this->member, $admin);
    $adminMembership->forceFill(['status' => OrganizationUserStatus::Suspended])->save();
    expect(fn () => app(RemoveBranchStaffAssignmentAction::class)->handle($this->organization, $this->branch, $this->member, $admin, $preview['fingerprint'], 'Previously permitted removal.', true))->toThrow(AuthorizationException::class);
    expect($this->assignment->fresh())->not->toBeNull();
});

test('removal cannot target a foreign organization or archived restaurant', function (string $scope): void {
    $preview = app(BranchAssignmentQueryService::class)->removalPreview($this->organization, $this->branch, $this->member, $this->owner);
    $organization = $this->organization;
    if ($scope === 'foreign') {
        $organization = app(CreateOrganizationAction::class)->handle($this->owner, ['name' => 'Foreign organization']);
    } else {
        $this->branch->delete();
    }
    expect(fn () => app(RemoveBranchStaffAssignmentAction::class)->handle($organization, $this->branch, $this->member, $this->owner, $preview['fingerprint'], 'Invalid context removal.', true))->toThrow(ModelNotFoundException::class);
    expect($this->assignment->fresh())->not->toBeNull();
})->with(['foreign', 'archived']);

test('removal blocks self protected accounts and roles outside delegation', function (string $target): void {
    $actor = $this->owner;
    if ($target === 'self') {
        $this->member = OrganizationUser::query()->where('organization_id', $this->organization->id)->where('user_id', $actor->id)->sole();
    } elseif ($target === 'protected') {
        $this->member->user->roles()->attach(Role::query()->where('code', SystemRole::Superadmin->value)->sole());
    } else {
        $actor = User::factory()->create();
        OrganizationUser::factory()->forOrganization($this->organization)->forUser($actor)->forSystemRole(SystemRole::RestaurantAdmin)->active()->create();
        $this->assignment->forceFill(['role_id' => Role::query()->where('code', SystemRole::Owner->value)->sole()->id])->save();
    }
    expect(fn () => app(BranchAssignmentQueryService::class)->removalPreview($this->organization, $this->branch, $this->member, $actor))->toThrow(AuthorizationException::class);
    expect($this->assignment->fresh())->not->toBeNull();
})->with(['self', 'protected', 'delegation']);

test('removal cannot restore organization participation implicitly', function (): void {
    $preview = app(BranchAssignmentQueryService::class)->removalPreview($this->organization, $this->branch, $this->member, $this->owner);
    $this->member->forceFill(['status' => OrganizationUserStatus::Suspended])->save();
    expect(fn () => app(RemoveBranchStaffAssignmentAction::class)->handle($this->organization, $this->branch, $this->member, $this->owner, $preview['fingerprint'], 'Remove during organization suspension.', true))->toThrow(ValidationException::class);
    expect($this->assignment->fresh())->not->toBeNull()->and($this->member->fresh()->status)->toBe(OrganizationUserStatus::Suspended);
});

test('removal and zones roll back when audit storage rejects the event', function (): void {
    $area = AreaNode::factory()->forBranch($this->branch)->active()->create();
    $zoneAssignment = AreaNodeWaiter::factory()->create(['organization_id' => $this->organization->id, 'branch_id' => $this->branch->id, 'area_node_id' => $area->id, 'user_id' => $this->member->user_id]);
    $invitation = Invitation::factory()->forOrganization($this->organization)->forRole($this->member->role)->pending()->create([
        'email' => $this->member->user->email, 'brand_id' => $this->branch->brand_id, 'branch_id' => $this->branch->id,
    ]);
    $credentialVersion = $invitation->credentialVersion();
    $preview = app(BranchAssignmentQueryService::class)->removalPreview($this->organization, $this->branch, $this->member, $this->owner);
    AuditLog::saving(fn (AuditLog $event): bool => $event->action !== AuditLogAction::StaffPermissionChanged);
    expect(fn () => app(RemoveBranchStaffAssignmentAction::class)->handle($this->organization, $this->branch, $this->member, $this->owner, $preview['fingerprint'], 'Audited removal.', true))->toThrow(RuntimeException::class);
    expect($this->assignment->fresh())->not->toBeNull()->and($zoneAssignment->fresh())->not->toBeNull()
        ->and($this->member->fresh()->access_version)->toBe(0)
        ->and(AuditLog::query()->where('action', AuditLogAction::StaffPermissionChanged)->count())->toBe(0)
        ->and(AuditLog::query()->where('action', AuditLogAction::InvitationCancelled)->count())->toBe(0)
        ->and($invitation->fresh()->status)->toBe(InvitationStatus::Pending)
        ->and($invitation->fresh()->credentialVersion())->toBe($credentialVersion);
});

test('removal requires a deliberate confirmation and valid reason inside the action', function (string $reason, bool $confirmed): void {
    $preview = app(BranchAssignmentQueryService::class)->removalPreview($this->organization, $this->branch, $this->member, $this->owner);
    expect(fn () => app(RemoveBranchStaffAssignmentAction::class)->handle($this->organization, $this->branch, $this->member, $this->owner, $preview['fingerprint'], $reason, $confirmed))->toThrow(ValidationException::class);
    expect($this->assignment->fresh())->not->toBeNull();
})->with([['Valid explanation.', false], ['  ', true], ['ab', true], [str_repeat('x', 501), true]]);

test('a removal preview cannot become a return to organization after another assignment disappears', function (): void {
    $other = BranchUser::factory()->forBranch($this->otherBranch)->forUser($this->member->user)->forRole($this->member->role)->active()->create();
    $preview = app(BranchAssignmentQueryService::class)->removalPreview($this->organization, $this->branch, $this->member, $this->owner);
    $other->delete();
    expect(fn () => app(RemoveBranchStaffAssignmentAction::class)->handle($this->organization, $this->branch, $this->member, $this->owner, $preview['fingerprint'], 'Remove just this restaurant.', true))->toThrow(ValidationException::class);
    expect($this->assignment->fresh())->not->toBeNull()->and($this->member->user->fresh()->canAccessBranch($this->otherBranch))->toBeFalse();
});

test('remaining suspended assignments continue to restrict access after local removal', function (): void {
    $other = BranchUser::factory()->forBranch($this->otherBranch)->forUser($this->member->user)->forRole($this->member->role)->suspended()->create();
    $preview = app(BranchAssignmentQueryService::class)->removalPreview($this->organization, $this->branch, $this->member, $this->owner);
    expect($preview['operation'])->toBe('remove_assignment')->and($preview['after_ids'])->toBe([]);
    app(RemoveBranchStaffAssignmentAction::class)->handle($this->organization, $this->branch, $this->member, $this->owner, $preview['fingerprint'], 'Remove active restaurant only.', true);
    expect($other->fresh()->status)->toBe(OrganizationUserStatus::Suspended)
        ->and($this->member->user->fresh()->canAccessBranch($this->otherBranch))->toBeFalse()
        ->and($this->member->user->fresh()->canAccessBranch($this->branch))->toBeFalse();
});

test('a repeated removal does not write duplicate audit or change inherited access', function (): void {
    $preview = app(BranchAssignmentQueryService::class)->removalPreview($this->organization, $this->branch, $this->member, $this->owner);
    $action = app(RemoveBranchStaffAssignmentAction::class);
    $action->handle($this->organization, $this->branch, $this->member, $this->owner, $preview['fingerprint'], 'Return to organization.', true);
    expect(fn () => $action->handle($this->organization, $this->branch, $this->member, $this->owner, $preview['fingerprint'], 'Repeated confirmation.', true))->toThrow(ValidationException::class);
    expect($this->member->fresh()->access_version)->toBe(1)
        ->and(AuditLog::query()->where('action', AuditLogAction::StaffPermissionChanged)->count())->toBe(1);
});

test('a membership identity cannot be paired with another verified user', function (): void {
    $preview = app(BranchAssignmentQueryService::class)->removalPreview($this->organization, $this->branch, $this->member, $this->owner);
    $this->member->user_id = $this->owner->id;
    expect(fn () => app(RemoveBranchStaffAssignmentAction::class)->handle($this->organization, $this->branch, $this->member, $this->owner, $preview['fingerprint'], 'Mismatched membership identity.', true))->toThrow(ModelNotFoundException::class);
    expect($this->assignment->fresh())->not->toBeNull();
});

test('a failed assignment deletion rolls back its version and zone removal', function (): void {
    $area = AreaNode::factory()->forBranch($this->branch)->active()->create();
    $zone = AreaNodeWaiter::factory()->create(['organization_id' => $this->organization->id, 'branch_id' => $this->branch->id, 'area_node_id' => $area->id, 'user_id' => $this->member->user_id]);
    $preview = app(BranchAssignmentQueryService::class)->removalPreview($this->organization, $this->branch, $this->member, $this->owner);
    BranchUser::deleting(fn (): bool => false);
    expect(fn () => app(RemoveBranchStaffAssignmentAction::class)->handle($this->organization, $this->branch, $this->member, $this->owner, $preview['fingerprint'], 'Deletion rejected.', true))->toThrow(RuntimeException::class);
    expect($this->assignment->fresh())->not->toBeNull()->and($zone->fresh())->not->toBeNull()
        ->and($this->member->fresh()->access_version)->toBe(0);
});

test('independent sqlite removals cannot accidentally delete the final restriction', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'team-removal-concurrency-');
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
        $organization = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'Concurrent removal']);
        $first = Branch::factory()->for($organization)->create();
        $second = Branch::factory()->for($organization)->for($first->brand)->create();
        $member = OrganizationUser::factory()->forOrganization($organization)->forSystemRole(SystemRole::Waiter)->active()->create();
        BranchUser::factory()->forBranch($first)->forUser($member->user)->forRole($member->role)->active()->create();
        BranchUser::factory()->forBranch($second)->forUser($member->user)->forRole($member->role)->active()->create();
        $tasks = [];
        foreach ([$first, $second] as $branch) {
            $preview = app(BranchAssignmentQueryService::class)->removalPreview($organization, $branch, $member, $owner);
            expect($preview['operation'])->toBe('remove_assignment');
            $tasks[] = TeamScopeConcurrencyTasks::removal($connection, $organization->id, $owner->id, $member->id, $branch->id, $preview['fingerprint']);
        }
        config(['database.default' => $original]);
        $results = Concurrency::driver('process')->run($tasks, 20);
        config(['database.default' => 'team_scope_concurrency']);
        DB::purge('team_scope_concurrency');
        $states = array_column($results, 'result');
        sort($states);
        $future = Branch::factory()->for($organization)->for($first->brand)->create();
        expect(array_unique(array_column($results, 'pid')))->toHaveCount(2)
            ->and($states)->toBe(['conflict', 'removed'])
            ->and(BranchUser::query()->where('user_id', $member->user_id)->count())->toBe(1)
            ->and($member->fresh()->access_version)->toBe(1)
            ->and($member->user->fresh()->canAccessBranch($future))->toBeFalse()
            ->and(AuditLog::query()->where('action', AuditLogAction::StaffPermissionChanged)->count())->toBe(1);
    } finally {
        config(['database.default' => $original]);
        DB::disconnect('team_scope_concurrency');
        DB::purge('team_scope_concurrency');
        File::delete([$path, $path.'-wal', $path.'-shm']);
        File::delete(glob($path.'.ready.*'));
    }
});

test('removal cancels old invitations only for its restaurant and cannot be reversed by old acceptance', function (bool $last): void {
    if (! $last) {
        BranchUser::factory()->forBranch($this->otherBranch)->forUser($this->member->user)->forRole($this->member->role)->active()->create();
    }
    $invitation = Invitation::factory()->forOrganization($this->organization)->forRole($this->member->role)->pending()->create([
        'email' => $this->member->user->email, 'brand_id' => $this->branch->brand_id, 'branch_id' => $this->branch->id,
    ]);
    $untouched = Invitation::factory()->forOrganization($this->organization)->forRole($this->member->role)->pending()->create([
        'email' => $this->member->user->email, 'brand_id' => $this->otherBranch->brand_id, 'branch_id' => $this->otherBranch->id,
    ]);
    $preview = app(BranchAssignmentQueryService::class)->removalPreview($this->organization, $this->branch, $this->member, $this->owner);
    app(RemoveBranchStaffAssignmentAction::class)->handle($this->organization, $this->branch, $this->member, $this->owner, $preview['fingerprint'], 'Remove assignment and old invitations.', true);
    expect($invitation->fresh()->status)->toBe(InvitationStatus::Cancelled)
        ->and($invitation->fresh()->invite_token_hash)->toBeNull()
        ->and($untouched->fresh()->status)->toBe(InvitationStatus::Pending)
        ->and(AuditLog::query()->where('action', AuditLogAction::InvitationCancelled)->sole()->new_values['reason'])->toBe('branch_assignment_removed')
        ->and(AuditLog::query()->where('action', AuditLogAction::InvitationCancelled)->sole()->new_values['staff_user_id'])->toBe($this->member->user_id);
    expect(fn () => app(AcceptInvitationAction::class)->handle($invitation, $this->member->user))->toThrow(DomainException::class);
    expect($this->assignment->fresh())->toBeNull()
        ->and($this->member->user->fresh()->canAccessBranch($this->branch))->toBe($last);
})->with([true, false]);
