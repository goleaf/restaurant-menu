<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\ServicePoints\BulkCreateServicePointsAction;
use App\Actions\ServicePoints\MoveServicePointsAction;
use App\Models\Branch;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class FloorServicePointConcurrencyTasks
{
    /** @param array<string,mixed> $connection @param array<string,mixed> $command */
    public static function write(array $connection, int $actorId, int $branchId, string $kind, array $command): Closure
    {
        return static function () use ($connection, $actorId, $branchId, $kind, $command): array {
            config(['database.default' => 'floor_point_concurrency', 'database.connections.floor_point_concurrency' => $connection]);
            DB::purge('floor_point_concurrency');
            $actor = User::query()->findOrFail($actorId);
            $branch = Branch::query()->findOrFail($branchId);
            file_put_contents($connection['database'].'.ready.'.getmypid(), 'ready');
            $deadline = microtime(true) + 5;
            while (count(glob($connection['database'].'.ready.*')) < 2 && microtime(true) < $deadline) {
                usleep(10000);
            }
            if (count(glob($connection['database'].'.ready.*')) !== 2) {
                throw new RuntimeException('Table writers failed to rendezvous.');
            }
            try {
                $result = $kind === 'bulk'
                    ? app(BulkCreateServicePointsAction::class)->handle($branch, $command['data'], $actor, $command['request_id'], $command['fingerprint'])
                    : app(MoveServicePointsAction::class)->handle($actor, $branch, $command['ids'], $command['target'], $command['versions'], $command['fingerprint'], $command['request_id']);

                return ['result' => 'saved', 'value' => $result, 'pid' => getmypid()];
            } catch (ValidationException) {
                return ['result' => 'conflict', 'pid' => getmypid()];
            }
        };
    }
}
