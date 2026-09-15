<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Staff\SetOrganizationStaffStatusAction;
use App\Actions\Staff\SyncWaiterAreaAssignmentsAction;
use App\Actions\Staff\UpdateBranchStaffRoleAction;
use App\Actions\Staff\UpdateOrganizationStaffRoleAction;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TeamAccessConcurrencyTasks
{
    /** @param array<string,mixed> $connection */
    public static function change(array $connection, int $actorId, int $organizationId, int $membershipId, int $roleId, string $kind, string $barrier, ?int $branchId = null): Closure
    {
        return static function () use ($connection, $actorId, $organizationId, $membershipId, $roleId, $kind, $barrier, $branchId): array {
            config(['database.default' => 'team_access_concurrency', 'database.connections.team_access_concurrency' => $connection]);
            DB::purge('team_access_concurrency');
            $actor = User::query()->whereKey($actorId)->firstOrFail();
            $organization = Organization::query()->whereKey($organizationId)->firstOrFail();
            $role = Role::query()->whereKey($roleId)->firstOrFail();
            $membership = $branchId === null ? OrganizationUser::query()->whereKey($membershipId)->firstOrFail()
                : BranchUser::query()->whereKey($membershipId)->firstOrFail();
            file_put_contents($barrier.'/'.getmypid(), 'ready');
            $deadline = microtime(true) + 5;
            while (count(glob($barrier.'/*')) < 2 && microtime(true) < $deadline) {
                usleep(10000);
            }
            if (count(glob($barrier.'/*')) !== 2) {
                throw new \RuntimeException('Concurrent workers failed to rendezvous.');
            }
            try {
                if ($kind === 'suspend' && $membership instanceof OrganizationUser) {
                    app(SetOrganizationStaffStatusAction::class)->suspend($membership, $actor, 'Concurrent access review.', expectedVersion: 0);
                } elseif ($membership instanceof BranchUser) {
                    app(UpdateBranchStaffRoleAction::class)->handle($actor, Branch::query()->whereKey($branchId)->firstOrFail(), $membership, $role, 'Concurrent role review.', expectedVersion: 0);
                } else {
                    app(UpdateOrganizationStaffRoleAction::class)->handle($actor, $organization, $membership, $role, 'Concurrent role review.', expectedVersion: 0);
                }

                return ['result' => 'saved', 'pid' => getmypid()];
            } catch (ValidationException) {
                return ['result' => 'conflict', 'pid' => getmypid()];
            }
        };
    }

    /** @param array<string,mixed> $connection @param list<int> $areaIds */
    public static function assignAreas(array $connection, int $actorId, int $branchId, int $membershipId, array $areaIds, string $expectedFingerprint, string $barrier): Closure
    {
        return static function () use ($connection, $actorId, $branchId, $membershipId, $areaIds, $expectedFingerprint, $barrier): array {
            config(['database.default' => 'team_access_concurrency', 'database.connections.team_access_concurrency' => $connection]);
            DB::purge('team_access_concurrency');
            $actor = User::query()->whereKey($actorId)->firstOrFail();
            $branch = Branch::query()->whereKey($branchId)->firstOrFail();
            $membership = BranchUser::query()->whereKey($membershipId)->firstOrFail();
            file_put_contents($barrier.'/'.getmypid(), 'ready');
            $deadline = microtime(true) + 5;
            while (count(glob($barrier.'/*')) < 2 && microtime(true) < $deadline) {
                usleep(10000);
            }
            if (count(glob($barrier.'/*')) !== 2) {
                throw new \RuntimeException('Concurrent assignment workers failed to rendezvous.');
            }
            try {
                app(SyncWaiterAreaAssignmentsAction::class)->handle($branch, $membership, $actor, $areaIds, $expectedFingerprint);

                return ['result' => 'saved', 'pid' => getmypid(), 'area_ids' => $areaIds];
            } catch (ValidationException $exception) {
                return ['result' => 'conflict', 'pid' => getmypid(), 'area_ids' => $areaIds, 'errors' => array_keys($exception->errors())];
            }
        };
    }
}
