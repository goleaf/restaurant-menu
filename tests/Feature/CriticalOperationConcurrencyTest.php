<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\AuditLogAction;
use App\Enums\InvitationStatus;
use App\Enums\MenuStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderStatusLogEvent;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\DraftOrderItem;
use App\Models\Invitation;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\QrCode;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CriticalOperationConcurrencyTasks;

test('critical retries converge across real processes without duplicate effects', function (): void {
    $databasePath = tempnam(sys_get_temp_dir(), 'restaurant-critical-concurrency-');

    expect($databasePath)->toBeString();

    $storageRoot = $databasePath.'-storage';
    $connectionName = 'critical_operation_concurrency';
    $originalDefaultConnection = config('database.default');
    $connection = config('database.connections.sqlite');
    $connection['database'] = $databasePath;

    try {
        File::ensureDirectoryExists($storageRoot);
        config([
            'database.default' => $connectionName,
            "database.connections.{$connectionName}" => $connection,
            'filesystems.disks.public.root' => $storageRoot,
        ]);
        DB::purge($connectionName);
        Storage::forgetDisk('public');

        expect(Artisan::call('migrate', [
            '--database' => $connectionName,
            '--force' => true,
        ]))->toBe(0);

        $this->seed(SystemPermissionsSeeder::class);

        $owner = User::factory()->create(['email' => 'critical-owner@example.test']);
        $organization = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'Critical Concurrency Group']);
        $brand = Brand::factory()->for($organization)->create(['name' => 'Critical Concurrency Brand']);
        $branch = Branch::factory()
            ->for($organization)
            ->for($brand)
            ->create(['name' => 'Critical Concurrency Restaurant']);
        $servicePoint = ServicePoint::factory()
            ->for($branch)
            ->create([
                'name' => 'Critical Table',
                'status' => ServicePointStatus::Occupied,
                'is_active' => true,
            ]);
        $tableSession = TableSession::factory()
            ->forServicePoint($servicePoint)
            ->active()
            ->create(['status' => TableSessionStatus::Active]);
        $guest = TableSessionGuest::factory()
            ->for($tableSession)
            ->create([
                'guest_name' => 'Concurrent Guest',
                'status' => TableSessionGuestStatus::Active,
            ]);
        $menu = Menu::factory()
            ->for($branch)
            ->create(['status' => MenuStatus::Active]);
        $category = MenuCategory::factory()
            ->for($menu)
            ->create(['is_active' => true]);
        $menuItem = MenuItem::factory()
            ->for($menu)
            ->for($category, 'category')
            ->create([
                'name' => 'Concurrent Soup',
                'price_cents' => 700,
                'is_available' => true,
            ]);
        $recipient = User::factory()->create(['email' => 'critical-waiter@example.test']);
        $waiterRole = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
        $closePermission = Permission::query()->where('code', SystemPermission::CloseTableSessions->value)->firstOrFail();
        $confirmPermission = Permission::query()->where('code', SystemPermission::ConfirmOrders->value)->firstOrFail();
        $waiterRole->permissions()->syncWithoutDetaching([
            $closePermission->id => ['enabled' => true],
            $confirmPermission->id => ['enabled' => true],
        ]);
        $invitation = Invitation::factory()
            ->forOrganization($organization)
            ->forRole($waiterRole)
            ->pending()
            ->create(['email' => $recipient->email]);
        $qrCode = QrCode::factory()
            ->for($servicePoint)
            ->create([
                'status' => QrCodeStatus::Active,
                'created_by_user_id' => $owner->id,
            ]);
        $qrGenerationServicePoint = ServicePoint::factory()
            ->for($branch)
            ->create(['name' => 'Concurrent QR Generation Table']);

        $runConcurrently = static function (array $tasks) use ($connectionName, $originalDefaultConnection): array {
            config(['database.default' => $originalDefaultConnection]);
            $results = Concurrency::driver('process')->run($tasks, 20);
            config(['database.default' => $connectionName]);
            DB::purge($connectionName);

            return $results;
        };

        $invitationResults = $runConcurrently([
            CriticalOperationConcurrencyTasks::acceptInvitation($connection, $connectionName, $invitation->id, $recipient->id),
            CriticalOperationConcurrencyTasks::acceptInvitation($connection, $connectionName, $invitation->id, $recipient->id),
        ]);

        expect($invitationResults)->toBe(['accepted', 'accepted'])
            ->and($invitation->fresh()->status)->toBe(InvitationStatus::Accepted)
            ->and(OrganizationUser::query()
                ->where('organization_id', $organization->id)
                ->where('user_id', $recipient->id)
                ->count())->toBe(1)
            ->and(AuditLog::query()
                ->where('action', AuditLogAction::InvitationAccepted->value)
                ->where('entity_id', $invitation->id)
                ->count())->toBe(1);

        $registrationInvitation = Invitation::factory()
            ->forOrganization($organization)
            ->forRole($waiterRole)
            ->pending()
            ->create(['email' => 'critical-new-waiter@example.test']);
        $registrationResults = $runConcurrently([
            CriticalOperationConcurrencyTasks::registerInvitationRecipient(
                $connection,
                $connectionName,
                $registrationInvitation->id,
                'Critical New Waiter',
                'critical-new-waiter@example.test',
                'StrongPassword2026!',
            ),
            CriticalOperationConcurrencyTasks::registerInvitationRecipient(
                $connection,
                $connectionName,
                $registrationInvitation->id,
                'Critical New Waiter',
                'critical-new-waiter@example.test',
                'StrongPassword2026!',
            ),
        ]);
        $registeredRecipientId = $registrationResults[0];

        expect(array_unique($registrationResults))->toHaveCount(1)
            ->and(User::query()->where('email', 'critical-new-waiter@example.test')->count())->toBe(1)
            ->and(OrganizationUser::query()
                ->where('organization_id', $organization->id)
                ->where('user_id', $registeredRecipientId)
                ->count())->toBe(1)
            ->and(AuditLog::query()
                ->where('action', AuditLogAction::InvitationAccepted->value)
                ->where('entity_id', $registrationInvitation->id)
                ->count())->toBe(1);

        $idempotencyKey = '018fdc1b-7a43-7d8e-a8b5-49ef5cc99999';
        $addedItemIds = $runConcurrently([
            CriticalOperationConcurrencyTasks::addDraftItem($connection, $connectionName, $tableSession->id, $guest->id, $menuItem->id, $idempotencyKey),
            CriticalOperationConcurrencyTasks::addDraftItem($connection, $connectionName, $tableSession->id, $guest->id, $menuItem->id, $idempotencyKey),
        ]);
        $draftOrderItem = DraftOrderItem::query()->whereKey($addedItemIds[0])->firstOrFail();

        expect(array_unique($addedItemIds))->toHaveCount(1)
            ->and(DraftOrderItem::query()->where('draft_order_id', $draftOrderItem->draft_order_id)->count())->toBe(1);

        $updatedItemIds = $runConcurrently([
            CriticalOperationConcurrencyTasks::updateDraftItem($connection, $connectionName, $draftOrderItem->id, $guest->id, 3),
            CriticalOperationConcurrencyTasks::updateDraftItem($connection, $connectionName, $draftOrderItem->id, $guest->id, 3),
        ]);

        expect(array_unique($updatedItemIds))->toHaveCount(1)
            ->and($draftOrderItem->fresh()->quantity)->toBe(3)
            ->and($draftOrderItem->fresh()->comment)->toBe('Concurrent retry')
            ->and(OrderStatusLog::query()
                ->where('draft_order_id', $draftOrderItem->draft_order_id)
                ->where('event', OrderStatusLogEvent::DraftEdited->value)
                ->count())->toBe(2);

        $deleteResults = $runConcurrently([
            CriticalOperationConcurrencyTasks::deleteDraftItem($connection, $connectionName, $draftOrderItem->id, $draftOrderItem->draft_order_id, $guest->id),
            CriticalOperationConcurrencyTasks::deleteDraftItem($connection, $connectionName, $draftOrderItem->id, $draftOrderItem->draft_order_id, $guest->id),
        ]);

        expect($deleteResults)->toBe([true, true])
            ->and($draftOrderItem->fresh())->toBeNull()
            ->and(OrderStatusLog::query()
                ->where('draft_order_id', $draftOrderItem->draft_order_id)
                ->where('event', OrderStatusLogEvent::DraftEdited->value)
                ->count())->toBe(3);

        $generatedQrCodeIds = $runConcurrently([
            CriticalOperationConcurrencyTasks::generateQr($connection, $connectionName, $qrGenerationServicePoint->id, $recipient->id, $storageRoot),
            CriticalOperationConcurrencyTasks::generateQr($connection, $connectionName, $qrGenerationServicePoint->id, $recipient->id, $storageRoot),
        ]);

        expect(array_unique($generatedQrCodeIds))->toHaveCount(1)
            ->and(QrCode::query()->where('service_point_id', $qrGenerationServicePoint->id)->count())->toBe(1)
            ->and(QrCode::query()
                ->where('service_point_id', $qrGenerationServicePoint->id)
                ->where('status', QrCodeStatus::Active->value)
                ->count())->toBe(1);

        $replacementQrCodeIds = $runConcurrently([
            CriticalOperationConcurrencyTasks::reissueQr($connection, $connectionName, $qrCode->id, $recipient->id, $storageRoot),
            CriticalOperationConcurrencyTasks::reissueQr($connection, $connectionName, $qrCode->id, $recipient->id, $storageRoot),
        ]);

        expect(array_unique($replacementQrCodeIds))->toHaveCount(1)
            ->and($qrCode->fresh()->status)->toBe(QrCodeStatus::Revoked)
            ->and(QrCode::query()->where('service_point_id', $servicePoint->id)->count())->toBe(2)
            ->and(QrCode::query()
                ->where('service_point_id', $servicePoint->id)
                ->where('status', QrCodeStatus::Active->value)
                ->count())->toBe(1)
            ->and(AuditLog::query()
                ->where('action', AuditLogAction::QrReissued->value)
                ->where('branch_id', $branch->id)
                ->count())->toBe(1);

        $order = Order::factory()
            ->for($branch)
            ->for($servicePoint)
            ->for($tableSession)
            ->create(['status' => OrderStatus::Ready]);
        $orderStatusResults = $runConcurrently([
            CriticalOperationConcurrencyTasks::changeOrderStatus($connection, $connectionName, $order->id, $recipient->id, OrderStatus::Served),
            CriticalOperationConcurrencyTasks::changeOrderStatus($connection, $connectionName, $order->id, $recipient->id, OrderStatus::Served),
        ]);

        expect(array_unique($orderStatusResults))->toBe([OrderStatus::Served->value])
            ->and($order->fresh()->status)->toBe(OrderStatus::Served)
            ->and(OrderStatusLog::query()
                ->where('order_id', $order->id)
                ->where('event', OrderStatusLogEvent::OrderStatusChanged->value)
                ->count())->toBe(1);

        $closedSessionIds = $runConcurrently([
            CriticalOperationConcurrencyTasks::closeTable($connection, $connectionName, $tableSession->id, $recipient->id),
            CriticalOperationConcurrencyTasks::closeTable($connection, $connectionName, $tableSession->id, $recipient->id),
        ]);

        expect(array_unique($closedSessionIds))->toHaveCount(1)
            ->and($tableSession->fresh()->status)->toBe(TableSessionStatus::Closed)
            ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::Free)
            ->and(AuditLog::query()
                ->where('action', AuditLogAction::TableSessionClosed->value)
                ->where('entity_id', $tableSession->id)
                ->count())->toBe(1);
    } finally {
        config(['database.default' => $originalDefaultConnection]);
        DB::disconnect($connectionName);
        DB::purge($connectionName);
        Storage::forgetDisk('public');
        File::delete([
            $databasePath,
            $databasePath.'-shm',
            $databasePath.'-wal',
        ]);
        File::deleteDirectory($storageRoot);
    }
});
