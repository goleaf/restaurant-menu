<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\TableSessions\ConfirmBranchSessionCleanupAction;
use App\Models\Branch;
use App\Models\TableSession;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SessionCleanupConcurrencyTasks
{
    /**
     * @param  array<string, mixed>  $connection
     * @param  array<string, mixed>  $preview
     */
    public static function run(array $connection, int $branchId, int $actorId, int $sessionId, array $preview, string $requestId, string $operation): Closure
    {
        return static function () use ($connection, $branchId, $actorId, $sessionId, $preview, $requestId, $operation): array {
            config(['database.default' => 'session_cleanup_concurrency', 'database.connections.session_cleanup_concurrency' => $connection]);
            DB::purge('session_cleanup_concurrency');
            file_put_contents($connection['database'].'.ready.'.getmypid(), 'ready');
            $deadline = microtime(true) + 8;
            while (count(glob($connection['database'].'.ready.*')) < 2 && microtime(true) < $deadline) {
                usleep(10000);
            }
            if (count(glob($connection['database'].'.ready.*')) !== 2) {
                throw new RuntimeException('Both cleanup processes must start before either mutation.');
            }
            if ($operation === 'activity') {
                TableSession::query()->whereKey($sessionId)->firstOrFail()->forceFill(['updated_at' => now()])->save();
                file_put_contents($connection['database'].'.activity', 'committed');

                return ['pid' => getmypid(), 'activity' => true];
            }
            if ($operation === 'after_activity') {
                while (! is_file($connection['database'].'.activity') && microtime(true) < $deadline) {
                    usleep(10000);
                }
                if (! is_file($connection['database'].'.activity')) {
                    throw new RuntimeException('Activity writer did not commit before confirmation.');
                }
            }
            $result = app(ConfirmBranchSessionCleanupAction::class)->handle(
                User::query()->findOrFail($actorId), Branch::query()->findOrFail($branchId), $preview, $requestId,
            );

            return ['pid' => getmypid(), 'result' => $result];
        };
    }
}
