<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\DraftOrderStatus;
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
use App\Models\KitchenTicket;
use App\Models\Order;
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
                'menu_item_id' => null,
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
        ], 20);

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
