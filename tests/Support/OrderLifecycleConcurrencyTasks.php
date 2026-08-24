<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Waiter\ConfirmDraftOrderByWaiterAction;
use App\Models\DraftOrder;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;

final class OrderLifecycleConcurrencyTasks
{
    /** @param array<string, mixed> $connection */
    public static function confirm(
        array $connection,
        string $connectionName,
        int $draftOrderId,
        int $waiterId,
    ): Closure {
        return static function () use ($connection, $connectionName, $draftOrderId, $waiterId): array {
            config([
                'database.default' => $connectionName,
                "database.connections.{$connectionName}" => $connection,
            ]);
            DB::purge($connectionName);

            $order = app(ConfirmDraftOrderByWaiterAction::class)->handle(
                DraftOrder::query()->whereKey($draftOrderId)->firstOrFail(),
                User::query()->whereKey($waiterId)->firstOrFail(),
            );

            return [
                'order_id' => $order->id,
                'status' => $order->status->value,
            ];
        };
    }
}
