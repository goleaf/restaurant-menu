<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Staff\AddBranchStaffMemberAction;
use App\Actions\Staff\RemoveBranchStaffAssignmentAction;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class TeamScopeConcurrencyTasks
{
    /** @param array<string,mixed> $connection */
    public static function assignment(array $connection, int $organizationId, int $ownerId, int $memberId, int $branchId, string $fingerprint): Closure
    {
        return static function () use ($connection, $organizationId, $ownerId, $memberId, $branchId, $fingerprint): array {
            config(['database.default' => 'team_scope_concurrency', 'database.connections.team_scope_concurrency' => $connection]);
            DB::purge('team_scope_concurrency');
            $organization = Organization::query()->findOrFail($organizationId);
            $actor = User::query()->findOrFail($ownerId);
            $membership = OrganizationUser::query()->with('role')->findOrFail($memberId);
            $branch = Branch::query()->findOrFail($branchId);
            $pattern = $connection['database'].'.ready.*';
            file_put_contents($connection['database'].'.ready.'.getmypid(), 'ready');
            $deadline = microtime(true) + 5;
            while (count(glob($pattern)) < 2 && microtime(true) < $deadline) {
                usleep(10000);
            }
            if (count(glob($pattern)) !== 2) {
                throw new RuntimeException('Scope writers failed to rendezvous.');
            }
            try {
                app(AddBranchStaffMemberAction::class)->handle($organization, $branch, $membership->role, $actor,
                    ['organization_membership_id' => $memberId], $fingerprint, true);

                return ['result' => 'assigned', 'pid' => getmypid()];
            } catch (ValidationException) {
                return ['result' => 'conflict', 'pid' => getmypid()];
            }
        };
    }

    /** @param array<string,mixed> $connection */
    public static function removal(array $connection, int $organizationId, int $ownerId, int $memberId, int $branchId, string $fingerprint): Closure
    {
        return static function () use ($connection, $organizationId, $ownerId, $memberId, $branchId, $fingerprint): array {
            config(['database.default' => 'team_scope_concurrency', 'database.connections.team_scope_concurrency' => $connection]);
            DB::purge('team_scope_concurrency');
            $organization = Organization::query()->findOrFail($organizationId);
            $actor = User::query()->findOrFail($ownerId);
            $membership = OrganizationUser::query()->findOrFail($memberId);
            $branch = Branch::query()->findOrFail($branchId);
            $pattern = $connection['database'].'.ready.*';
            file_put_contents($connection['database'].'.ready.'.getmypid(), 'ready');
            $deadline = microtime(true) + 5;
            while (count(glob($pattern)) < 2 && microtime(true) < $deadline) {
                usleep(10000);
            }
            if (count(glob($pattern)) !== 2) {
                throw new RuntimeException('Removal writers failed to rendezvous.');
            }
            try {
                app(RemoveBranchStaffAssignmentAction::class)->handle($organization, $branch, $membership, $actor, $fingerprint, 'Remove this restaurant only.', true);

                return ['result' => 'removed', 'pid' => getmypid()];
            } catch (ValidationException) {
                return ['result' => 'conflict', 'pid' => getmypid()];
            }
        };
    }
}
