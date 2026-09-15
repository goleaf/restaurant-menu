<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\AuditLogAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Models\AreaNode;
use App\Models\AreaNodeWaiter;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;
use App\Services\Staff\StaffQueryService;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Support\TeamAccessConcurrencyTasks;

test('independent sqlite writers cannot overwrite another role or remove the last manager', function (string $kind): void {
    $database = tempnam(sys_get_temp_dir(), 'restaurant-team-access-race-');
    $barrier = $database.'-barrier';
    $original = config('database.default');
    $connection = config('database.connections.sqlite');
    $connection['database'] = $database;
    try {
        File::ensureDirectoryExists($barrier);
        config(['database.default' => 'team_access_concurrency', 'database.connections.team_access_concurrency' => $connection]);
        DB::purge('team_access_concurrency');
        expect($connection['transaction_mode'])->toBe('IMMEDIATE');
        expect(Artisan::call('migrate', ['--database' => 'team_access_concurrency', '--force' => true]))->toBe(0);
        $this->seed(SystemPermissionsSeeder::class);
        $owner = User::factory()->create();
        $organization = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'Fictional Concurrent Team']);
        $waiter = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
        $cook = Role::query()->where('code', SystemRole::Cook->value)->firstOrFail();
        $bartender = Role::query()->where('code', SystemRole::Bartender->value)->firstOrFail();
        $member = OrganizationUser::factory()->forOrganization($organization)->forRole($waiter)->active()->create();
        $branch = null;
        if ($kind === 'branch') {
            $brand = Brand::factory()->for($organization)->create();
            $branch = Branch::factory()->for($organization)->for($brand)->create();
            $target = BranchUser::factory()->forBranch($branch)->forUser($member->user)->forRole($waiter)->active()->create();
        } else {
            $target = $member;
        }
        if ($kind === 'suspend') {
            $superadmin = User::factory()->create();
            $superadmin->roles()->attach(Role::query()->where('code', SystemRole::Superadmin->value)->firstOrFail());
            $ownerRole = Role::query()->where('code', SystemRole::Owner->value)->firstOrFail();
            $target->forceFill(['role_id' => $ownerRole->id])->save();
            $second = OrganizationUser::query()->where('organization_id', $organization->id)->where('user_id', $owner->id)->firstOrFail();
            $actor = $superadmin;
        } else {
            $actor = $owner;
            $second = $target;
        }
        $tasks = [
            TeamAccessConcurrencyTasks::change($connection, $actor->id, $organization->id, $target->id, $cook->id, $kind, $barrier, $branch?->id),
            TeamAccessConcurrencyTasks::change($connection, $actor->id, $organization->id, $second->id, $bartender->id, $kind, $barrier, $branch?->id),
        ];
        config(['database.default' => $original]);
        $results = Concurrency::driver('process')->run($tasks, 20);
        expect(array_unique(array_column($results, 'pid')))->toHaveCount(2);
        $states = array_column($results, 'result');
        sort($states);
        expect($states)->toBe(['conflict', 'saved']);
        config(['database.default' => 'team_access_concurrency']);
        DB::purge('team_access_concurrency');
        if ($kind === 'suspend') {
            expect(OrganizationUser::query()->where('organization_id', $organization->id)->where('status', OrganizationUserStatus::Active->value)->count())->toBe(1)
                ->and(AuditLog::query()->where('action', AuditLogAction::StaffDeactivated->value)->count())->toBe(1);
        } else {
            expect($target->fresh()->access_version)->toBe(1)
                ->and(AuditLog::query()->where('action', AuditLogAction::StaffRoleChanged->value)->count())->toBe(1);
            if ($kind === 'branch') {
                expect($member->fresh()->role_id)->toBe($waiter->id);
            }
        }
    } finally {
        config(['database.default' => $original]);
        DB::disconnect('team_access_concurrency');
        DB::purge('team_access_concurrency');
        File::deleteDirectory($barrier);
        File::delete([$database, $database.'-wal', $database.'-shm']);
    }
})->with(['organization', 'branch', 'suspend']);

test('independent sqlite area editors preserve the winning complete assignment and reject a stale fingerprint', function (): void {
    $database = tempnam(sys_get_temp_dir(), 'restaurant-team-area-race-');
    $barrier = $database.'-barrier';
    $original = config('database.default');
    $connection = config('database.connections.sqlite');
    $connection['database'] = $database;
    try {
        File::ensureDirectoryExists($barrier);
        config(['database.default' => 'team_access_concurrency', 'database.connections.team_access_concurrency' => $connection]);
        DB::purge('team_access_concurrency');
        expect($connection['transaction_mode'])->toBe('IMMEDIATE');
        expect(Artisan::call('migrate', ['--database' => 'team_access_concurrency', '--force' => true]))->toBe(0);
        $this->seed(SystemPermissionsSeeder::class);
        $owner = User::factory()->create();
        $organization = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'Fictional Area Access Group']);
        $secondAdministrator = User::factory()->create();
        OrganizationUser::factory()->forOrganization($organization)->forUser($secondAdministrator)
            ->forRole(Role::query()->where('code', SystemRole::Director->value)->firstOrFail())->active()->create();
        $brand = Brand::factory()->for($organization)->create();
        $branch = Branch::factory()->for($organization)->for($brand)->create();
        $waiter = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
        $member = OrganizationUser::factory()->forOrganization($organization)->forRole($waiter)->active()->create();
        $assignment = BranchUser::factory()->forBranch($branch)->forUser($member->user)->forRole($waiter)->active()->create();
        $areas = AreaNode::factory()->count(3)->for($branch)->create(['is_active' => true]);
        AreaNodeWaiter::factory()->create([
            'organization_id' => $organization->id, 'branch_id' => $branch->id,
            'user_id' => $member->user_id, 'area_node_id' => $areas[0]->id,
            'assigned_by_user_id' => $owner->id,
        ]);
        $snapshot = app(StaffQueryService::class)->assignmentSnapshot($branch, $assignment);
        $tasks = [
            TeamAccessConcurrencyTasks::assignAreas($connection, $owner->id, $branch->id, $assignment->id, [$areas[1]->id], $snapshot['fingerprint'], $barrier),
            TeamAccessConcurrencyTasks::assignAreas($connection, $secondAdministrator->id, $branch->id, $assignment->id, [$areas[2]->id], $snapshot['fingerprint'], $barrier),
        ];
        config(['database.default' => $original]);
        $results = Concurrency::driver('process')->run($tasks, 20);
        expect(array_unique(array_column($results, 'pid')))->toHaveCount(2);
        $states = array_column($results, 'result');
        sort($states);
        expect($states)->toBe(['conflict', 'saved']);
        $winner = collect($results)->firstWhere('result', 'saved');
        $loser = collect($results)->firstWhere('result', 'conflict');
        expect($loser['errors'])->toBe(['assignmentForm.areaIds']);
        config(['database.default' => 'team_access_concurrency']);
        DB::purge('team_access_concurrency');
        $persisted = app(StaffQueryService::class)->assignmentSnapshot($branch, $assignment);
        expect($persisted['ids'])->toBe($winner['area_ids'])
            ->and($persisted['ids'])->not->toBe($loser['area_ids'])
            ->and($persisted['fingerprint'])->not->toBe($snapshot['fingerprint'])
            ->and(AreaNodeWaiter::query()->where('branch_id', $branch->id)->where('user_id', $member->user_id)->count())->toBe(1)
            ->and($member->fresh()->status)->toBe(OrganizationUserStatus::Active);
    } finally {
        config(['database.default' => $original]);
        DB::disconnect('team_access_concurrency');
        DB::purge('team_access_concurrency');
        File::deleteDirectory($barrier);
        File::delete([$database, $database.'-wal', $database.'-shm']);
    }
});
