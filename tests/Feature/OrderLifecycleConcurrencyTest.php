<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\DraftOrderStatus;
use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderStatusLogEvent;
use App\Enums\OrganizationUserStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemRole;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusLog;
use App\Models\OrganizationUser;
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
use Tests\Support\OrderLifecycleConcurrencyTasks;

test('concurrent waiter confirmations create one order and one department dispatch', function (): void {
    $databasePath = tempnam(sys_get_temp_dir(), 'restaurant-order-concurrency-');

    expect($databasePath)->toBeString();

    $connectionName = 'order_lifecycle_concurrency';
    $originalDefaultConnection = config('database.default');
    $connection = config('database.connections.sqlite');
    $connection['database'] = $databasePath;

    try {
        config([
            'database.default' => $connectionName,
            "database.connections.{$connectionName}" => $connection,
        ]);
        DB::purge($connectionName);

        expect(Artisan::call('migrate', [
            '--database' => $connectionName,
            '--force' => true,
        ]))->toBe(0);

        $this->seed(SystemPermissionsSeeder::class);

        $owner = User::factory()->create();
        $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Concurrent Order Group']);
        $brand = Brand::factory()->for($organization)->create(['name' => 'Concurrent Order Brand']);
        $branch = Branch::factory()
            ->for($organization)
            ->for($brand)
            ->create(['name' => 'Concurrent Order Restaurant']);
        $servicePoint = ServicePoint::factory()
            ->for($branch)
            ->create([
                'name' => 'Concurrent Order Table',
                'status' => ServicePointStatus::HasNewOrder,
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
        $draftOrder = DraftOrder::factory()
            ->for($tableSession)
            ->create([
                'status' => DraftOrderStatus::SentToWaiter,
                'sent_to_waiter_at' => now(),
                'sent_by_guest_id' => $guest->id,
            ]);

        DraftOrderItem::factory()
            ->for($draftOrder, 'draftOrder')
            ->for($guest, 'guest')
            ->create([
                'item_name' => 'Concurrent Soup',
                'unit_price_cents' => 700,
                'modifier_total_cents' => 0,
                'total_price_cents' => 700,
            ]);

        $waiter = User::factory()->create(['name' => 'Concurrent Waiter']);
        $waiterRole = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();

        OrganizationUser::factory()
            ->forOrganization($organization)
            ->forUser($waiter)
            ->forRole($waiterRole)
            ->active()
            ->create(['status' => OrganizationUserStatus::Active]);

        $draftOrderId = $draftOrder->id;
        $waiterId = $waiter->id;

        config(['database.default' => $originalDefaultConnection]);

        $results = Concurrency::driver('process')->run([
            OrderLifecycleConcurrencyTasks::confirm($connection, $connectionName, $draftOrderId, $waiterId),
            OrderLifecycleConcurrencyTasks::confirm($connection, $connectionName, $draftOrderId, $waiterId),
        ], 60);

        config(['database.default' => $connectionName]);
        DB::purge($connectionName);

        expect(collect($results)->pluck('order_id')->unique()->values()->all())->toHaveCount(1)
            ->and(collect($results)->pluck('status')->unique()->values()->all())->toBe([
                OrderStatus::SentToKitchenBar->value,
            ])
            ->and(Order::query()->where('draft_order_id', $draftOrderId)->count())->toBe(1)
            ->and(KitchenTicket::query()->whereHas('order', fn ($query) => $query->where('draft_order_id', $draftOrderId))->count())->toBe(1)
            ->and(OrderStatusLog::query()
                ->where('draft_order_id', $draftOrderId)
                ->where('event', OrderStatusLogEvent::DraftConfirmed->value)
                ->count())->toBe(1)
            ->and(OrderStatusLog::query()
                ->where('draft_order_id', $draftOrderId)
                ->where('event', OrderStatusLogEvent::OrderSentToKitchenBar->value)
                ->count())->toBe(1);
    } finally {
        config(['database.default' => $originalDefaultConnection]);
        DB::disconnect($connectionName);
        DB::purge($connectionName);
        File::delete([
            $databasePath,
            $databasePath.'-shm',
            $databasePath.'-wal',
        ]);
    }
});

test('concurrent department status requests create one transition history entry', function (): void {
    $databasePath = tempnam(sys_get_temp_dir(), 'restaurant-department-concurrency-');

    expect($databasePath)->toBeString();

    $connectionName = 'department_status_concurrency';
    $originalDefaultConnection = config('database.default');
    $connection = config('database.connections.sqlite');
    $connection['database'] = $databasePath;

    try {
        config([
            'database.default' => $connectionName,
            "database.connections.{$connectionName}" => $connection,
        ]);
        DB::purge($connectionName);

        expect(Artisan::call('migrate', [
            '--database' => $connectionName,
            '--force' => true,
        ]))->toBe(0);

        $this->seed(SystemPermissionsSeeder::class);

        $owner = User::factory()->create();
        $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Concurrent Department Group']);
        $brand = Brand::factory()->for($organization)->create(['name' => 'Concurrent Department Brand']);
        $branch = Branch::factory()
            ->for($organization)
            ->for($brand)
            ->create(['name' => 'Concurrent Department Restaurant']);
        $servicePoint = ServicePoint::factory()
            ->for($branch)
            ->create(['name' => 'Concurrent Department Table']);
        $tableSession = TableSession::factory()
            ->forServicePoint($servicePoint)
            ->active()
            ->create();
        $department = KitchenDepartment::factory()
            ->for($branch)
            ->create([
                'type' => KitchenDepartmentType::Kitchen,
                'name' => 'Concurrent Kitchen',
            ]);
        $order = Order::factory()
            ->forTableSession($tableSession)
            ->sentToDepartments()
            ->create();
        $ticket = KitchenTicket::factory()
            ->forOrder($order)
            ->create([
                'kitchen_department_id' => $department->id,
                'department_type' => $department->type,
                'department_name' => $department->name,
            ]);
        $orderItem = OrderItem::factory()
            ->for($order)
            ->create([
                'kitchen_department_id' => $department->id,
                'kitchen_department_type' => $department->type,
                'kitchen_department_name' => $department->name,
                'item_name' => 'Concurrent pasta',
            ]);
        $ticketItem = KitchenTicketItem::factory()
            ->forDispatchedOrderItem($ticket, $orderItem)
            ->pending()
            ->create();
        $chef = User::factory()->create(['name' => 'Concurrent Chef']);
        $chefRole = Role::query()->where('code', SystemRole::HeadChef->value)->firstOrFail();

        OrganizationUser::factory()
            ->forOrganization($organization)
            ->forUser($chef)
            ->forRole($chefRole)
            ->active()
            ->create(['status' => OrganizationUserStatus::Active]);

        $ticketItemId = $ticketItem->id;
        $chefId = $chef->id;

        config(['database.default' => $originalDefaultConnection]);

        $results = Concurrency::driver('process')->run([
            OrderLifecycleConcurrencyTasks::acceptDepartmentItem($connection, $connectionName, $ticketItemId, $chefId),
            OrderLifecycleConcurrencyTasks::acceptDepartmentItem($connection, $connectionName, $ticketItemId, $chefId),
        ], 60);

        config(['database.default' => $connectionName]);
        DB::purge($connectionName);

        expect(array_unique($results))->toBe([KitchenTicketItemStatus::Accepted->value])
            ->and($ticketItem->fresh()->status)->toBe(KitchenTicketItemStatus::Accepted)
            ->and($order->fresh()->status)->toBe(OrderStatus::SentToKitchenBar)
            ->and(OrderStatusLog::query()
                ->where('order_id', $order->id)
                ->where('event', OrderStatusLogEvent::TicketItemStatusChanged->value)
                ->where('status_type', 'kitchen_ticket_item')
                ->where('new_status', KitchenTicketItemStatus::Accepted->value)
                ->count())->toBe(1);
    } finally {
        config(['database.default' => $originalDefaultConnection]);
        DB::disconnect($connectionName);
        DB::purge($connectionName);
        File::delete([
            $databasePath,
            $databasePath.'-shm',
            $databasePath.'-wal',
        ]);
    }
});
