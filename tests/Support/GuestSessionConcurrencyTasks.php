<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\TableSessions\ApproveTableSessionJoinRequestAction;
use App\Actions\TableSessions\CreateGuestPendingTableSessionAction;
use App\Models\ServicePoint;
use App\Models\TableSessionGuest;
use App\Models\TableSessionJoinRequest;
use Closure;
use Illuminate\Support\Facades\DB;

final class GuestSessionConcurrencyTasks
{
    /** @param array<string, mixed> $connection */
    public static function enter(
        array $connection,
        string $connectionName,
        int $servicePointId,
        string $guestName,
        string $credential,
    ): Closure {
        return static function () use ($connection, $connectionName, $credential, $guestName, $servicePointId): array {
            config([
                'database.default' => $connectionName,
                "database.connections.{$connectionName}" => $connection,
            ]);
            DB::purge($connectionName);

            $servicePoint = ServicePoint::query()->whereKey($servicePointId)->firstOrFail();
            $result = app(CreateGuestPendingTableSessionAction::class)->handle(
                $servicePoint,
                $guestName,
                $credential,
            );

            return [
                'state' => $result['state']->value,
                'guest_id' => $result['guest']?->id,
                'join_request_id' => $result['join_request']?->id,
            ];
        };
    }

    /** @param array<string, mixed> $connection */
    public static function approve(
        array $connection,
        string $connectionName,
        int $hostGuestId,
        int $joinRequestId,
    ): Closure {
        return static function () use ($connection, $connectionName, $hostGuestId, $joinRequestId): int {
            config([
                'database.default' => $connectionName,
                "database.connections.{$connectionName}" => $connection,
            ]);
            DB::purge($connectionName);

            return app(ApproveTableSessionJoinRequestAction::class)->handle(
                TableSessionJoinRequest::query()->whereKey($joinRequestId)->firstOrFail(),
                TableSessionGuest::query()->whereKey($hostGuestId)->firstOrFail(),
            )->id;
        };
    }
}
