<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\AreaNodes\UpdateAreaNodeAction;
use App\Models\AreaNode;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class FloorAreaConcurrencyTasks
{
    /** @param array<string,mixed> $connection */
    public static function move(array $connection, int $actorId, int $areaId, int $parentId): Closure
    {
        return static function () use ($connection, $actorId, $areaId, $parentId): array {
            config(['database.default' => 'floor_area_concurrency', 'database.connections.floor_area_concurrency' => $connection]);
            DB::purge('floor_area_concurrency');
            $actor = User::query()->findOrFail($actorId);
            $area = AreaNode::query()->findOrFail($areaId);
            $data = [...$area->only(['name', 'icon', 'sort_order', 'is_active']), 'type' => $area->type->value, 'parent_id' => $parentId];
            file_put_contents($connection['database'].'.ready.'.getmypid(), 'ready');
            $deadline = microtime(true) + 5;
            while (count(glob($connection['database'].'.ready.*')) < 2 && microtime(true) < $deadline) {
                usleep(10000);
            }
            if (count(glob($connection['database'].'.ready.*')) !== 2) {
                throw new RuntimeException('Area writers failed to rendezvous.');
            }
            try {
                app(UpdateAreaNodeAction::class)->handle($area, $data, $actor, 0);

                return ['result' => 'moved', 'pid' => getmypid()];
            } catch (InvalidArgumentException) {
                return ['result' => 'cycle_rejected', 'pid' => getmypid()];
            }
        };
    }
}
