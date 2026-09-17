<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Branches\UpdateBranchTemporaryClosureAction;
use App\Actions\DraftOrders\AddGuestDraftOrderItemAction;
use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class AvailabilityOrderConcurrencyTasks
{
    /** @param array<string,mixed> $connection */
    public static function pause(array $connection, int $branchId, int $actorId): Closure
    {
        return static function () use ($connection, $branchId, $actorId): array {
            self::connect($connection);
            self::await($connection['database'].'.read');
            Branch::updated(function (Branch $branch) use ($connection, $branchId): void {
                if ($branch->id === $branchId && $branch->is_temporarily_closed) {
                    file_put_contents($connection['database'].'.writing', 'pause transaction acquired');
                    self::await($connection['database'].'.attempt');
                    usleep(250000);
                }
            });
            app(UpdateBranchTemporaryClosureAction::class)->handle(User::query()->findOrFail($actorId), Branch::query()->findOrFail($branchId), true,
                'Concurrent restaurant pause.', null, 0, 'UTC', (string) Str::uuid());

            return ['result' => 'paused', 'pid' => getmypid()];
        };
    }

    /** @param array<string,mixed> $connection */
    public static function add(array $connection, int $sessionId, int $guestId, int $itemId): Closure
    {
        return static function () use ($connection, $sessionId, $guestId, $itemId): array {
            self::connect($connection);
            $session = TableSession::query()->findOrFail($sessionId);
            $guest = TableSessionGuest::query()->findOrFail($guestId);
            $item = MenuItem::query()->with('menu.branch')->findOrFail($itemId);
            if ($item->menu->branch->is_temporarily_closed) {
                throw new RuntimeException('The stale order snapshot must precede the pause.');
            }
            file_put_contents($connection['database'].'.read', 'open snapshot loaded');
            self::await($connection['database'].'.writing');
            file_put_contents($connection['database'].'.attempt', 'new order transaction requested');
            try {
                app(AddGuestDraftOrderItemAction::class)->handle($session, $guest, $item, []);

                return ['result' => 'added', 'pid' => getmypid()];
            } catch (ValidationException) {
                return ['result' => 'unavailable', 'pid' => getmypid()];
            }
        };
    }

    /** @param array<string,mixed> $connection */
    private static function connect(array $connection): void
    {
        config(['database.default' => 'availability_concurrency', 'database.connections.availability_concurrency' => $connection]);
        DB::purge('availability_concurrency');
    }

    private static function await(string $path): void
    {
        $deadline = microtime(true) + 5;
        while (! is_file($path) && microtime(true) < $deadline) {
            usleep(10000);
        }
        if (! is_file($path)) {
            throw new RuntimeException('Availability writers failed to rendezvous.');
        }
    }
}
