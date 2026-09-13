<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Departments\UpdateDepartmentTicketItemStatusAction;
use App\Actions\Waiter\ConfirmDraftOrderByWaiterAction;
use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
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

    /** @param array<string, mixed> $connection */
    public static function acceptDepartmentItem(
        array $connection,
        string $connectionName,
        int $ticketItemId,
        int $chefId,
    ): Closure {
        return static function () use ($chefId, $connection, $connectionName, $ticketItemId): string {
            config([
                'database.default' => $connectionName,
                "database.connections.{$connectionName}" => $connection,
            ]);
            DB::purge($connectionName);

            return app(UpdateDepartmentTicketItemStatusAction::class)->handle(
                itemId: $ticketItemId,
                status: KitchenTicketItemStatus::Accepted,
                user: User::query()->whereKey($chefId)->firstOrFail(),
                departmentTypes: KitchenDepartmentType::kitchenProductionTypes(),
                roleCodes: [SystemRole::HeadChef, SystemRole::Cook],
                permissionCodes: [SystemPermission::ViewKitchen],
            )->status->value;
        };
    }
}
