<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\DraftOrders\AddGuestDraftOrderItemAction;
use App\Actions\DraftOrders\DeleteGuestDraftOrderItemAction;
use App\Actions\DraftOrders\UpdateGuestDraftOrderItemAction;
use App\Actions\Invitations\AcceptInvitationAction;
use App\Actions\Invitations\RegisterInvitationRecipientAction;
use App\Actions\Orders\ChangeOrderStatusAction;
use App\Actions\QrCodes\GenerateQrCodeForServicePointAction;
use App\Actions\QrCodes\ReissueQrCodeForServicePointAction;
use App\Actions\TableSessions\CloseTableSessionAction;
use App\Enums\OrderStatus;
use App\Models\DraftOrderItem;
use App\Models\Invitation;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use Closure;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class CriticalOperationConcurrencyTasks
{
    /** @param array<string, mixed> $connection */
    public static function acceptInvitation(
        array $connection,
        string $connectionName,
        int $invitationId,
        int $recipientId,
    ): Closure {
        return static function () use ($connection, $connectionName, $invitationId, $recipientId): string {
            self::configureConnection($connection, $connectionName);

            try {
                app(AcceptInvitationAction::class)->handle(
                    Invitation::query()->whereKey($invitationId)->firstOrFail(),
                    User::query()->whereKey($recipientId)->firstOrFail(),
                );

                return 'accepted';
            } catch (DomainException) {
                return 'already_accepted';
            }
        };
    }

    /** @param array<string, mixed> $connection */
    public static function registerInvitationRecipient(
        array $connection,
        string $connectionName,
        int $invitationId,
        string $name,
        string $email,
        string $password,
    ): Closure {
        return static function () use ($connection, $connectionName, $email, $invitationId, $name, $password): int {
            self::configureConnection($connection, $connectionName);

            return app(RegisterInvitationRecipientAction::class)->handle(
                Invitation::query()->whereKey($invitationId)->firstOrFail(),
                [
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                ],
            )->id;
        };
    }

    /** @param array<string, mixed> $connection */
    public static function addDraftItem(
        array $connection,
        string $connectionName,
        int $tableSessionId,
        int $guestId,
        int $menuItemId,
        string $idempotencyKey,
    ): Closure {
        return static function () use ($connection, $connectionName, $guestId, $idempotencyKey, $menuItemId, $tableSessionId): int {
            self::configureConnection($connection, $connectionName);

            return app(AddGuestDraftOrderItemAction::class)->handle(
                tableSession: TableSession::query()->whereKey($tableSessionId)->firstOrFail(),
                guest: TableSessionGuest::query()->whereKey($guestId)->firstOrFail(),
                menuItem: MenuItem::query()->whereKey($menuItemId)->firstOrFail(),
                selectedModifierOptions: [],
                idempotencyKey: $idempotencyKey,
            )->id;
        };
    }

    /** @param array<string, mixed> $connection */
    public static function updateDraftItem(
        array $connection,
        string $connectionName,
        int $draftOrderItemId,
        int $guestId,
        int $quantity,
    ): Closure {
        return static function () use ($connection, $connectionName, $draftOrderItemId, $guestId, $quantity): int {
            self::configureConnection($connection, $connectionName);

            return app(UpdateGuestDraftOrderItemAction::class)->handle(
                draftOrderItem: DraftOrderItem::query()->whereKey($draftOrderItemId)->firstOrFail(),
                guest: TableSessionGuest::query()->whereKey($guestId)->firstOrFail(),
                quantity: $quantity,
                selectedModifierOptions: [],
                comment: 'Concurrent retry',
            )->id;
        };
    }

    /** @param array<string, mixed> $connection */
    public static function deleteDraftItem(
        array $connection,
        string $connectionName,
        int $draftOrderItemId,
        int $draftOrderId,
        int $guestId,
    ): Closure {
        return static function () use ($connection, $connectionName, $draftOrderId, $draftOrderItemId, $guestId): bool {
            self::configureConnection($connection, $connectionName);

            $staleDraftOrderItem = new DraftOrderItem;
            $staleDraftOrderItem->setRawAttributes([
                'id' => $draftOrderItemId,
                'draft_order_id' => $draftOrderId,
                'table_session_guest_id' => $guestId,
            ], true);
            $staleDraftOrderItem->exists = true;

            app(DeleteGuestDraftOrderItemAction::class)->handle(
                $staleDraftOrderItem,
                TableSessionGuest::query()->whereKey($guestId)->firstOrFail(),
            );

            return true;
        };
    }

    /** @param array<string, mixed> $connection */
    public static function reissueQr(
        array $connection,
        string $connectionName,
        int $qrCodeId,
        int $userId,
        string $storageRoot,
    ): Closure {
        return static function () use ($connection, $connectionName, $qrCodeId, $storageRoot, $userId): int {
            self::configureConnection($connection, $connectionName);
            config(['filesystems.disks.public.root' => $storageRoot]);
            Storage::forgetDisk('public');

            return app(ReissueQrCodeForServicePointAction::class)->handle(
                QrCode::query()->whereKey($qrCodeId)->firstOrFail(),
                User::query()->whereKey($userId)->firstOrFail(),
            )->id;
        };
    }

    /** @param array<string, mixed> $connection */
    public static function generateQr(
        array $connection,
        string $connectionName,
        int $servicePointId,
        int $userId,
        string $storageRoot,
    ): Closure {
        return static function () use ($connection, $connectionName, $servicePointId, $storageRoot, $userId): int {
            self::configureConnection($connection, $connectionName);
            config(['filesystems.disks.public.root' => $storageRoot]);
            Storage::forgetDisk('public');

            return app(GenerateQrCodeForServicePointAction::class)->handle(
                ServicePoint::query()->whereKey($servicePointId)->firstOrFail(),
                User::query()->whereKey($userId)->firstOrFail(),
            )->id;
        };
    }

    /** @param array<string, mixed> $connection */
    public static function changeOrderStatus(
        array $connection,
        string $connectionName,
        int $orderId,
        int $userId,
        OrderStatus $targetStatus,
    ): Closure {
        return static function () use ($connection, $connectionName, $orderId, $targetStatus, $userId): string {
            self::configureConnection($connection, $connectionName);

            return app(ChangeOrderStatusAction::class)->handle(
                Order::query()->whereKey($orderId)->firstOrFail(),
                $targetStatus,
                User::query()->whereKey($userId)->firstOrFail(),
            )->status->value;
        };
    }

    /** @param array<string, mixed> $connection */
    public static function closeTable(
        array $connection,
        string $connectionName,
        int $tableSessionId,
        int $userId,
    ): Closure {
        return static function () use ($connection, $connectionName, $tableSessionId, $userId): int {
            self::configureConnection($connection, $connectionName);

            return app(CloseTableSessionAction::class)->handle(
                TableSession::query()->whereKey($tableSessionId)->firstOrFail(),
                User::query()->whereKey($userId)->firstOrFail(),
            )->id;
        };
    }

    /** @param array<string, mixed> $connection */
    private static function configureConnection(array $connection, string $connectionName): void
    {
        config([
            'database.default' => $connectionName,
            "database.connections.{$connectionName}" => $connection,
        ]);
        DB::purge($connectionName);
    }
}
