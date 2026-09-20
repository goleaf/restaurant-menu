<?php

declare(strict_types=1);

use App\Actions\Departments\DeliverDepartmentTicketItemNotificationsAction;
use App\Actions\Departments\UpdateDepartmentTicketItemStatusAction;
use App\Actions\Departments\UpdatePreparationTicketItemsAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderStatusLogEvent;
use App\Enums\SystemRole;
use App\Enums\TableSessionStatus;
use App\Models\Branch;
use App\Models\Brand;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Tests\Support\PreparationMutationConcurrencyTasks;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
});

test('preparation rejects an outdated shown version before applying a forward shortcut', function (): void {
    [$item, $cook, $branch] = preparationMutationFixture();
    $version = (string) $item->getRawOriginal('updated_at');
    $item->forceFill(['status' => KitchenTicketItemStatus::Accepted])->save();

    expect(fn () => app(UpdateDepartmentTicketItemStatusAction::class)->handlePreparation(
        $item->id, KitchenTicketItemStatus::Ready, $cook, $branch->id,
        KitchenTicketItemStatus::New, $version, $item->kitchen_ticket_id,
    ))->toThrow(ValidationException::class);
    expect($item->fresh()->status)->toBe(KitchenTicketItemStatus::Accepted);
});

test('preparation binds a mutation to the shown ticket and exact timestamp', function (string $change): void {
    [$item, $cook, $branch] = preparationMutationFixture();
    $ticketId = $item->kitchen_ticket_id;
    $version = (string) $item->getRawOriginal('updated_at');
    if ($change === 'ticket') {
        $ticketId++;
    } else {
        $version = '2000-01-01 00:00:00';
    }

    expect(fn () => app(UpdateDepartmentTicketItemStatusAction::class)->handlePreparation(
        $item->id, KitchenTicketItemStatus::Accepted, $cook, $branch->id,
        KitchenTicketItemStatus::New, $version, $ticketId,
    ))->toThrow(ValidationException::class);
    expect($item->fresh()->status)->toBe(KitchenTicketItemStatus::New);
})->with(['ticket', 'timestamp']);

test('preparation exact lost response replay retains one transition and does not mark served', function (): void {
    [$item, $cook, $branch, $guest, $waiter] = preparationMutationFixture();
    $action = app(UpdateDepartmentTicketItemStatusAction::class);
    $version = (string) $item->getRawOriginal('updated_at');
    foreach (range(1, 2) as $attempt) {
        $result = $action->handlePreparation(
            $item->id, KitchenTicketItemStatus::Ready, $cook, $branch->id,
            KitchenTicketItemStatus::New, $version, $item->kitchen_ticket_id,
        );
        expect($result->notification_delivery_pending)->toBeFalse();
    }
    expect($item->fresh()->status)->toBe(KitchenTicketItemStatus::Ready)
        ->and($item->fresh()->served_at)->toBeNull()
        ->and(preparationMutationEvents($item)->count())->toBe(1)
        ->and($waiter->notifications()->where('type', 'kitchen_item_ready')->count())->toBe(1)
        ->and($guest->notifications()->where('type', 'kitchen_item_ready')->count())->toBe(1);
});

test('failed notification delivery retains a durable event and rolls back partial delivery before retry', function (): void {
    [$item, $cook, $branch, $guest, $waiter] = preparationMutationFixture();
    Exceptions::fake();
    $rejectDelivery = true;
    Event::listen(NotificationSent::class, function (NotificationSent $event) use (&$rejectDelivery): void {
        if ($rejectDelivery && $event->notifiable instanceof TableSessionGuest) {
            throw new RuntimeException('Simulated local delivery failure');
        }
    });
    $action = app(UpdateDepartmentTicketItemStatusAction::class);
    $version = (string) $item->getRawOriginal('updated_at');
    $this->freezeTime();
    $occurredAt = now()->startOfSecond()->toISOString();

    $saved = $action->handlePreparation(
        $item->id, KitchenTicketItemStatus::Ready, $cook, $branch->id,
        KitchenTicketItemStatus::New, $version, $item->kitchen_ticket_id,
    );
    expect($saved->status)->toBe(KitchenTicketItemStatus::Ready)
        ->and($saved->notification_delivery_pending)->toBeTrue()
        ->and(data_get(preparationMutationEvents($item)->firstOrFail()->metadata, 'notifications.state'))->toBe('pending')
        ->and($waiter->notifications()->count())->toBe(0)
        ->and($guest->notifications()->count())->toBe(0);

    $rejectDelivery = false;
    $this->travel(5)->minutes();
    $result = $action->handlePreparation(
        $item->id, KitchenTicketItemStatus::Ready, $cook, $branch->id,
        KitchenTicketItemStatus::New, $version, $item->kitchen_ticket_id,
    );
    expect($result->notification_delivery_pending)->toBeFalse()
        ->and(preparationMutationEvents($item)->count())->toBe(1)
        ->and(data_get(preparationMutationEvents($item)->firstOrFail()->metadata, 'notifications.state'))->toBe('delivered')
        ->and($waiter->notifications()->count())->toBe(1)
        ->and($guest->notifications()->count())->toBe(1)
        ->and(data_get($guest->notifications()->firstOrFail()->data, 'ready_at'))->toBe($occurredAt);
    Exceptions::assertReported(RuntimeException::class);
});

test('preparation cannot mutate a served cancelled paid or closed resource', function (string $state): void {
    [$item, $cook, $branch] = preparationMutationFixture();
    $version = (string) $item->getRawOriginal('updated_at');
    if ($state === 'served') {
        $item->forceFill(['status' => KitchenTicketItemStatus::Ready, 'served_at' => now()])->save();
    } elseif ($state === 'cancelled') {
        $item->forceFill(['status' => KitchenTicketItemStatus::Cancelled])->save();
    } else {
        $item->kitchenTicket->order->forceFill(['status' => OrderStatus::from($state)])->save();
    }
    expect(fn () => app(UpdateDepartmentTicketItemStatusAction::class)->handlePreparation(
        $item->id, KitchenTicketItemStatus::Ready, $cook, $branch->id,
        KitchenTicketItemStatus::New, $version, $item->kitchen_ticket_id,
    ))->toThrow(ValidationException::class);
    expect(preparationMutationEvents($item)->count())->toBe(0);
})->with(['served', 'cancelled', 'paid', 'closed', 'payment_requested']);

test('preparation rejects terminal visit even when a legacy order is still active', function (string $state): void {
    [$item, $cook, $branch] = preparationMutationFixture();
    $item->kitchenTicket->tableSession->forceFill(['status' => TableSessionStatus::from($state)])->save();
    expect(fn () => app(UpdateDepartmentTicketItemStatusAction::class)->handlePreparation(
        $item->id, KitchenTicketItemStatus::Ready, $cook, $branch->id,
        KitchenTicketItemStatus::New, (string) $item->getRawOriginal('updated_at'), $item->kitchen_ticket_id,
    ))->toThrow(ValidationException::class);
    expect($item->fresh()->status)->toBe(KitchenTicketItemStatus::New)
        ->and(preparationMutationEvents($item)->count())->toBe(0);
})->with(['payment_requested', 'paid', 'closed', 'cancelled']);

test('pending preparation event is superseded instead of notifying finished work', function (string $scope, string $state): void {
    [$item, $cook, $branch, $guest, $waiter] = preparationMutationFixture();
    Exceptions::fake();
    $fail = true;
    Event::listen(NotificationSent::class, function (NotificationSent $event) use (&$fail): void {
        if ($fail && $event->notifiable instanceof TableSessionGuest) {
            throw new RuntimeException('Preparation delivery retry fixture');
        }
    });
    app(UpdateDepartmentTicketItemStatusAction::class)->handlePreparation(
        $item->id, KitchenTicketItemStatus::Ready, $cook, $branch->id,
        $item->status, (string) $item->getRawOriginal('updated_at'), $item->kitchen_ticket_id,
    );
    $event = preparationMutationEvents($item)->firstOrFail();
    $fail = false;
    if ($scope === 'visit') {
        $item->kitchenTicket->tableSession->forceFill(['status' => TableSessionStatus::from($state)])->save();
    } else {
        $item->kitchenTicket->order->forceFill(['status' => OrderStatus::from($state)])->save();
    }
    app(DeliverDepartmentTicketItemNotificationsAction::class)->handle($event->id);
    expect($waiter->notifications()->count())->toBe(0)
        ->and($guest->notifications()->count())->toBe(0)
        ->and(data_get($event->fresh()->metadata, 'notifications.state'))->toBe('superseded');
})->with([
    'payment requested visit' => ['visit', 'payment_requested'],
    'paid visit' => ['visit', 'paid'],
    'closed visit' => ['visit', 'closed'],
    'cancelled visit' => ['visit', 'cancelled'],
    'served order' => ['order', 'served'],
    'payment requested order' => ['order', 'payment_requested'],
    'paid order' => ['order', 'paid'],
    'closed order' => ['order', 'closed'],
    'cancelled order' => ['order', 'cancelled'],
]);

test('preparation selected item batch reports an exact partial result without touching hidden work', function (): void {
    [$item, $cook, $branch] = preparationMutationFixture();
    $ticket = $item->kitchenTicket;
    $otherOrderItem = OrderItem::factory()->for($ticket->order)->create();
    $other = KitchenTicketItem::factory()->forDispatchedOrderItem($ticket, $otherOrderItem)->create();
    $hiddenOrderItem = OrderItem::factory()->for($ticket->order)->create();
    $hidden = KitchenTicketItem::factory()->forDispatchedOrderItem($ticket, $hiddenOrderItem)->create();
    $items = [
        ['id' => $item->id, 'status' => 'new', 'updated_at' => (string) $item->getRawOriginal('updated_at')],
        ['id' => $other->id, 'status' => 'new', 'updated_at' => (string) $other->getRawOriginal('updated_at')],
    ];
    $other->forceFill(['status' => KitchenTicketItemStatus::InProgress])->save();
    $outcomes = app(UpdatePreparationTicketItemsAction::class)->handle($cook, $branch->id, $ticket->id, KitchenTicketItemStatus::Accepted, $items);

    expect(array_column($outcomes, 'successful'))->toBe([true, false])
        ->and(array_column($outcomes, 'id'))->toBe([$item->id, $other->id])
        ->and($item->fresh()->status)->toBe(KitchenTicketItemStatus::Accepted)
        ->and($other->fresh()->status)->toBe(KitchenTicketItemStatus::InProgress)
        ->and($hidden->fresh()->status)->toBe(KitchenTicketItemStatus::New);
});

test('preparation batch preserves successful outcomes when one independent operation throws', function (): void {
    $actor = User::factory()->create();
    $delegate = Mockery::mock(UpdateDepartmentTicketItemStatusAction::class);
    $delegate->shouldReceive('handlePreparation')->with(1, KitchenTicketItemStatus::Accepted, $actor, 2, KitchenTicketItemStatus::New, 'version', 3)
        ->once()->andThrow(new RuntimeException('Private internal detail'));
    $delegate->shouldReceive('handlePreparation')->with(2, KitchenTicketItemStatus::Accepted, $actor, 2, KitchenTicketItemStatus::New, 'version', 3)
        ->once()->andReturn((new KitchenTicketItem)->setAttribute('notification_delivery_pending', false));
    Exceptions::fake();
    $result = (new UpdatePreparationTicketItemsAction($delegate))->handle($actor, 2, 3, KitchenTicketItemStatus::Accepted, [
        ['id' => 1, 'status' => 'new', 'updated_at' => 'version'],
        ['id' => 2, 'status' => 'new', 'updated_at' => 'version'],
    ]);
    expect(array_column($result, 'successful'))->toBe([false, true])
        ->and($result[0]['message'])->toBe(__('errors.types.system_error.message'));
    Exceptions::assertReported(RuntimeException::class);
});

test('preparation batch rejects duplicate and oversized selection before any write', function (string $invalid): void {
    [$item, $cook, $branch] = preparationMutationFixture();
    $selection = ['id' => $item->id, 'status' => 'new', 'updated_at' => (string) $item->getRawOriginal('updated_at')];
    $items = $invalid === 'duplicate' ? [$selection, $selection] : array_map(
        fn (int $index): array => [...$selection, 'id' => $index], range(1, 25),
    );
    expect(fn () => app(UpdatePreparationTicketItemsAction::class)->handle($cook, $branch->id, $item->kitchen_ticket_id, KitchenTicketItemStatus::Accepted, $items))
        ->toThrow(ValidationException::class);
    expect($item->fresh()->status)->toBe(KitchenTicketItemStatus::New);
})->with(['duplicate', 'oversized']);

test('preparation notification payload follows transferred visit while preserving original place', function (): void {
    [$item, $cook, $branch, $guest] = preparationMutationFixture();
    $ticket = $item->kitchenTicket;
    $destination = ServicePoint::factory()->for($branch)->create(['name' => 'Current serving place']);
    $ticket->tableSession->forceFill(['service_point_id' => $destination->id])->save();
    app(UpdateDepartmentTicketItemStatusAction::class)->handlePreparation(
        $item->id, KitchenTicketItemStatus::Ready, $cook, $branch->id,
        $item->status, (string) $item->getRawOriginal('updated_at'), $ticket->id,
    );
    $data = $guest->notifications()->where('type', 'kitchen_item_ready')->firstOrFail()->data;
    expect($data['service_point_id'])->toBe($destination->id)
        ->and($data['original_service_point_id'])->toBe($ticket->service_point_id)
        ->and($data['service_point_name'])->toBe('Current serving place');
});

test('preparation refuses a replay after a further transition or access revocation', function (string $change): void {
    [$item, $cook, $branch] = preparationMutationFixture();
    $action = app(UpdateDepartmentTicketItemStatusAction::class);
    $version = (string) $item->getRawOriginal('updated_at');
    $action->handlePreparation($item->id, KitchenTicketItemStatus::Accepted, $cook, $branch->id, KitchenTicketItemStatus::New, $version, $item->kitchen_ticket_id);
    if ($change === 'status') {
        $item->forceFill(['status' => KitchenTicketItemStatus::InProgress])->save();
    } else {
        OrganizationUser::query()->where('user_id', $cook->id)->update(['status' => 'suspended']);
    }
    expect(fn () => $action->handlePreparation($item->id, KitchenTicketItemStatus::Accepted, $cook, $branch->id, KitchenTicketItemStatus::New, $version, $item->kitchen_ticket_id))
        ->toThrow(ValidationException::class);
    expect(preparationMutationEvents($item)->count())->toBe(1);
})->with(['status', 'revoked']);

/** @return array{KitchenTicketItem, User, Branch, TableSessionGuest, User, User} */
function preparationMutationFixture(): array
{
    $owner = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'Preparation group']);
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $point = ServicePoint::factory()->for($branch)->create();
    $session = TableSession::factory()->forServicePoint($point)->active()->create();
    $guest = TableSessionGuest::factory()->for($session)->active()->create();
    $department = KitchenDepartment::factory()->for($branch)->forType(KitchenDepartmentType::Kitchen)->active()->create();
    $order = Order::factory()->forTableSession($session)->sentToDepartments()->create();
    $orderItem = OrderItem::factory()->for($order)->for($guest, 'guest')->create();
    $ticket = KitchenTicket::factory()->forOrder($order)->create([
        'kitchen_department_id' => $department->id,
        'department_type' => $department->type,
        'department_name' => $department->name,
    ]);
    $item = KitchenTicketItem::factory()->forDispatchedOrderItem($ticket, $orderItem)->pending()->create();
    $cook = User::factory()->create();
    OrganizationUser::factory()->forOrganization($organization)->forUser($cook)
        ->forRole(Role::query()->where('code', SystemRole::Cook->value)->firstOrFail())->active()->create();
    $waiter = User::factory()->create();
    OrganizationUser::factory()->forOrganization($organization)->forUser($waiter)
        ->forRole(Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail())->active()->create();

    return [$item, $cook, $branch, $guest, $waiter, $owner];
}

/** @return Builder<OrderStatusLog> */
function preparationMutationEvents(KitchenTicketItem $item): Builder
{
    return OrderStatusLog::query()->where('event', OrderStatusLogEvent::TicketItemStatusChanged)
        ->where('metadata->kitchen_ticket_item_id', $item->id);
}

test('preparation concurrent processes preserve one transition against competing work', function (string $race): void {
    $databasePath = tempnam(sys_get_temp_dir(), 'preparation-mutation-race-');
    expect($databasePath)->toBeString();
    $connectionName = 'preparation_mutation_race';
    $originalDefault = config('database.default');
    $connection = config('database.connections.sqlite');
    $connection['database'] = $databasePath;

    try {
        config(['database.default' => $connectionName, "database.connections.{$connectionName}" => $connection]);
        DB::purge($connectionName);
        expect(Artisan::call('migrate', ['--database' => $connectionName, '--force' => true]))->toBe(0);
        $this->seed(SystemPermissionsSeeder::class);
        [$item, $cook, $branch, , $waiter, $owner] = preparationMutationFixture();
        $operations = match ($race) {
            'start' => ['in_progress', 'in_progress'],
            'cancel' => ['ready', 'cancel'],
            default => ['ready', 'serve'],
        };
        if ($race === 'serve') {
            $item->forceFill(['status' => KitchenTicketItemStatus::Ready])->save();
            $item->kitchenTicket->order->forceFill(['status' => OrderStatus::Ready])->save();
        }
        $expectedStatus = $item->status->value;
        $version = (string) $item->getRawOriginal('updated_at');
        $secondActor = match ($race) {
            'cancel' => $owner, 'serve' => $waiter, default => $cook
        };
        config(['database.default' => $originalDefault]);
        $results = Concurrency::driver('process')->run([
            PreparationMutationConcurrencyTasks::operation($connection, $connectionName, $item->id, $cook->id, $branch->id, $item->kitchen_ticket_id, $expectedStatus, $version, $operations[0]),
            PreparationMutationConcurrencyTasks::operation($connection, $connectionName, $item->id, $secondActor->id, $branch->id, $item->kitchen_ticket_id, $expectedStatus, $version, $operations[1]),
        ], 45);
        config(['database.default' => $connectionName]);
        DB::purge($connectionName);
        $fresh = $item->fresh();
        if ($race === 'start') {
            expect(array_column($results, 'successful'))->toBe([true, true])
                ->and($fresh->status)->toBe(KitchenTicketItemStatus::InProgress)
                ->and(preparationMutationEvents($item)->count())->toBe(1);
        } elseif ($race === 'cancel') {
            expect($results[1]['successful'])->toBeTrue()
                ->and($fresh->status)->toBe(KitchenTicketItemStatus::Cancelled)
                ->and(preparationMutationEvents($item)->count())->toBeLessThanOrEqual(1);
        } else {
            expect($results[1]['successful'])->toBeTrue()
                ->and($fresh->status)->toBe(KitchenTicketItemStatus::Ready)
                ->and($fresh->served_at)->not->toBeNull()
                ->and(preparationMutationEvents($item)->count())->toBe(0);
        }
    } finally {
        config(['database.default' => $originalDefault]);
        DB::disconnect($connectionName);
        DB::purge($connectionName);
        File::delete([$databasePath, $databasePath.'-wal', $databasePath.'-shm']);
    }
})->with(['start', 'cancel', 'serve']);
