<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Departments\UpdateDepartmentTicketItemStatusAction;
use App\Actions\Orders\CancelOrderItemAction;
use App\Actions\Waiter\MarkKitchenTicketItemServedAction;
use App\Enums\KitchenTicketItemStatus;
use App\Models\KitchenTicketItem;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PreparationMutationConcurrencyTasks
{
    /** @param array<string, mixed> $connection */
    public static function operation(
        array $connection,
        string $connectionName,
        int $itemId,
        int $actorId,
        int $branchId,
        int $ticketId,
        string $expectedStatus,
        string $expectedUpdatedAt,
        string $operation,
    ): Closure {
        return static function () use ($connection, $connectionName, $itemId, $actorId, $branchId, $ticketId, $expectedStatus, $expectedUpdatedAt, $operation): array {
            config(['database.default' => $connectionName, "database.connections.{$connectionName}" => $connection]);
            DB::purge($connectionName);
            $user = User::query()->whereKey($actorId)->firstOrFail();
            try {
                if ($operation === 'cancel') {
                    $item = KitchenTicketItem::query()->with('orderItem')->whereKey($itemId)->firstOrFail();
                    app(CancelOrderItemAction::class)->handle($item->orderItem, $user, 'Concurrent guest cancellation');
                } elseif ($operation === 'serve') {
                    app(MarkKitchenTicketItemServedAction::class)->handle(KitchenTicketItem::query()->whereKey($itemId)->firstOrFail(), $user);
                } else {
                    app(UpdateDepartmentTicketItemStatusAction::class)->handlePreparation(
                        $itemId, KitchenTicketItemStatus::from($operation), $user, $branchId,
                        KitchenTicketItemStatus::from($expectedStatus), $expectedUpdatedAt, $ticketId,
                    );
                }

                return ['operation' => $operation, 'successful' => true];
            } catch (ValidationException) {
                return ['operation' => $operation, 'successful' => false];
            }
        };
    }
}
