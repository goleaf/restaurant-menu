<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\ServicePoints\BulkCreateServicePointsAction;
use App\Actions\ServicePoints\DeleteServicePointAction;
use App\Actions\ServicePoints\MoveServicePointsAction;
use App\Actions\TableSessions\OpenTableSessionForServicePointAction;
use App\Models\Branch;
use App\Models\ServicePoint;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
            $point = in_array($kind, ['archive', 'open'], true) ? ServicePoint::query()->where('branch_id', $branchId)->findOrFail($command['id']) : null;
            file_put_contents($connection['database'].'.ready.'.getmypid(), 'ready');
            $deadline = microtime(true) + 5;
            while (count(glob($connection['database'].'.ready.*')) < 2 && microtime(true) < $deadline) {
                usleep(10000);
            }
            if (count(glob($connection['database'].'.ready.*')) !== 2) {
                throw new RuntimeException('Table writers failed to rendezvous.');
            }
            try {
                $result = match ($kind) {
                    'bulk' => app(BulkCreateServicePointsAction::class)->handle($branch, $command['data'], $actor, $command['request_id'], $command['fingerprint']),
                    'move' => app(MoveServicePointsAction::class)->handle($actor, $branch, $command['ids'], $command['target'], $command['versions'], $command['fingerprint'], $command['request_id']),
                    'archive' => app(DeleteServicePointAction::class)->handle($actor, $branch, $point, $command['version']),
                    'open' => app(OpenTableSessionForServicePointAction::class)->handle($point, $actor)->id,
                };

                return ['result' => 'saved', 'value' => $result, 'pid' => getmypid()];
            } catch (ValidationException) {
                return ['result' => 'conflict', 'pid' => getmypid()];
            } catch (ModelNotFoundException $exception) {
                if ($kind !== 'open') {
                    throw $exception;
                }

                return ['result' => 'conflict', 'pid' => getmypid()];
            }
        };
    }
}
