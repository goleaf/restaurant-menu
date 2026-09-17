<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Onboarding\CreateRestaurantSetupAction;
use App\Models\RestaurantOnboarding;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class RestaurantSetupConcurrencyTasks
{
    /**
     * @param  array<string, mixed>  $connection
     * @param  array<string, mixed>  $data
     */
    public static function create(array $connection, int $actorId, array $data, string $requestId): Closure
    {
        return static function () use ($connection, $actorId, $data, $requestId): array {
            config(['database.default' => 'restaurant_setup_concurrency', 'database.connections.restaurant_setup_concurrency' => $connection]);
            DB::purge('restaurant_setup_concurrency');
            $actor = User::query()->whereKey($actorId)->firstOrFail();
            self::awaitPeer((string) $connection['database']);
            RestaurantOnboarding::creating(function (): void {
                usleep(200000);
            });
            $started = microtime(true);
            try {
                $setup = app(CreateRestaurantSetupAction::class)->handle($actor, $data, $requestId);
                $result = ['state' => 'created', 'setup_id' => $setup->id, 'branch_id' => $setup->branch_id];
            } catch (ValidationException $exception) {
                $result = ['state' => 'conflict', 'errors' => array_keys($exception->errors())];
            }

            return [...$result, 'pid' => getmypid(), 'php' => PHP_VERSION, 'started' => $started, 'finished' => microtime(true)];
        };
    }

    private static function awaitPeer(string $path): void
    {
        $pattern = $path.'.ready.*';
        file_put_contents($path.'.ready.'.getmypid(), 'ready');
        $deadline = microtime(true) + 5;
        while (count(glob($pattern)) < 2 && microtime(true) < $deadline) {
            usleep(10000);
        }
        if (count(glob($pattern)) !== 2) {
            throw new RuntimeException('Restaurant setup workers failed to rendezvous.');
        }
    }
}
