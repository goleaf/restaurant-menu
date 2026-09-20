<?php

declare(strict_types=1);

use App\Actions\Departments\BuildDepartmentDashboardAction;
use App\Actions\Departments\BuildDepartmentTicketPrintAction;
use App\Enums\DepartmentTicketFilter;
use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderStatusLogEvent;
use App\Enums\SystemRole;
use App\Enums\TableSessionStatus;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusLog;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
use App\Support\LocalizedDateFormatter;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

function preparationQueueScenario(): array
{
    test()->seed(SystemPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->roles()->attach(Role::query()->where('code', SystemRole::Superadmin->value)->firstOrFail());
    $order = Order::factory()->preparing()->create();
    $department = KitchenDepartment::factory()->for($order->branch)->forType(KitchenDepartmentType::Kitchen)->create();
    $ticket = KitchenTicket::factory()->forOrder($order)->create([
        'kitchen_department_id' => $department->id,
        'department_name' => 'Saved production name',
        'sent_at' => now()->subMinutes(20),
    ]);

    return [$user, $department, $ticket, $order];
}

function preparationQueueItem(KitchenTicket $ticket, KitchenTicketItemStatus $status, array $attributes = []): KitchenTicketItem
{
    $orderItem = OrderItem::factory()->for($ticket->order)->create([
        'table_session_guest_id' => null,
        'variant_name' => 'Saved large portion',
        'comment' => 'No sauce',
        'allergens_snapshot' => ['milk'],
        ...$attributes,
    ]);

    return KitchenTicketItem::factory()->forDispatchedOrderItem($ticket, $orderItem)->create(['status' => $status]);
}

function preparationQueuePayload(User $user, KitchenDepartment $department, DepartmentTicketFilter $filter = DepartmentTicketFilter::Active): array
{
    return app(BuildDepartmentDashboardAction::class)->handle(
        $user, $department->id, KitchenDepartmentType::kitchenProductionTypes(), [], [], $filter,
    );
}

test('a ready filtered row cannot mark its partially prepared full ticket ready', function (): void {
    [$user, $department, $ticket] = preparationQueueScenario();
    preparationQueueItem($ticket, KitchenTicketItemStatus::Ready);
    preparationQueueItem($ticket, KitchenTicketItemStatus::InProgress);
    $payload = preparationQueuePayload($user, $department, DepartmentTicketFilter::Ready)['tickets'][0];

    expect($payload['work_status']['value'])->toBe(KitchenTicketItemStatus::InProgress->value)
        ->and($payload['is_terminal'])->toBeFalse()
        ->and($payload['full_item_count'])->toBe(2)
        ->and($payload['matching_item_count'])->toBe(1)
        ->and($payload['is_partial'])->toBeTrue();
});

test('filter counters count full matching portions and recent cancellations in the same scope', function (): void {
    [$user, $department, $ticket] = preparationQueueScenario();
    preparationQueueItem($ticket, KitchenTicketItemStatus::Ready, ['quantity' => 3]);
    preparationQueueItem($ticket, KitchenTicketItemStatus::InProgress, ['quantity' => 4]);
    preparationQueueItem($ticket, KitchenTicketItemStatus::Cancelled, ['quantity' => 2]);

    $ready = preparationQueuePayload($user, $department, DepartmentTicketFilter::Ready);
    $active = preparationQueuePayload($user, $department, DepartmentTicketFilter::Active);

    expect($ready['portion_count'])->toBe(3)
        ->and($active['portion_count'])->toBe(4)
        ->and($active['recent_cancelled_item_count'])->toBe(1);
});

test('queue authorization aggregates rows and counters share one coherent read transaction', function (): void {
    [$user, $department, $ticket] = preparationQueueScenario();
    preparationQueueItem($ticket, KitchenTicketItemStatus::New);
    $outerLevel = DB::connection()->transactionLevel();
    $levels = [];
    DB::listen(function (QueryExecuted $query) use (&$levels): void {
        if (str_contains($query->sql, 'kitchen_ticket') || str_contains($query->sql, 'kitchen_department')) {
            $levels[] = $query->connection->transactionLevel();
        }
    });

    preparationQueuePayload($user, $department);

    expect(count($levels))->toBeGreaterThan(3)
        ->and(array_values(array_unique($levels)))->toBe([$outerLevel + 1]);
});

test('served filtered rows cannot stop the full ticket timer while another item cooks', function (): void {
    [$user, $department, $ticket] = preparationQueueScenario();
    preparationQueueItem($ticket, KitchenTicketItemStatus::Ready)->forceFill(['served_at' => now()])->save();
    preparationQueueItem($ticket, KitchenTicketItemStatus::InProgress);
    $payload = preparationQueuePayload($user, $department, DepartmentTicketFilter::Completed)['tickets'][0];

    expect($payload['work_status']['value'])->toBe(KitchenTicketItemStatus::InProgress->value)
        ->and($payload['is_terminal'])->toBeFalse();
});

test('cancelled orders do not contribute to operational counters', function (): void {
    [$user, $department, $ticket, $order] = preparationQueueScenario();
    preparationQueueItem($ticket, KitchenTicketItemStatus::New);
    $order->forceFill(['status' => OrderStatus::Cancelled])->save();

    $payload = preparationQueuePayload($user, $department);

    expect($payload['ticket_count'])->toBe(0)
        ->and($payload['new_item_count'])->toBe(0);
});

test('queue and print preserve variant warnings and original identity after a visit moves', function (): void {
    [$user, $department, $ticket, $order] = preparationQueueScenario();
    $item = preparationQueueItem($ticket, KitchenTicketItemStatus::New, [
        'selected_modifiers' => [['group_name' => 'Sauce', 'option_name' => 'On the side', 'price_delta_cents' => 250]],
    ]);
    $original = $order->servicePoint;
    $destination = ServicePoint::factory()->for($order->branch)->create(['name' => 'Current serving table', 'display_number' => '42']);
    $order->tableSession->forceFill(['service_point_id' => $destination->id])->save();

    $payload = preparationQueuePayload($user, $department)['tickets'][0];
    $print = app(BuildDepartmentTicketPrintAction::class)->handle($user, $ticket);

    expect($payload['service_point_name'])->toBe('Current serving table')
        ->and($payload['original_service_point_id'])->toBe($original->id)
        ->and($payload['department_name'])->toBe('Saved production name')
        ->and($payload['items'][0]['variant_name'])->toBe('Saved large portion')
        ->and($payload['items'][0]['version'])->toBe($item->getRawOriginal('updated_at'))
        ->and($payload['items'][0]['modifiers'])->toBe([['label' => 'Sauce: On the side']])
        ->and($print['items'][0]['variant_name'])->toBe('Saved large portion')
        ->and($print['service_point']['id'])->toBe($destination->id)
        ->and($print['items'][0]['comment'])->toBe('No sauce')
        ->and($print['items'][0]['allergens'])->toBe([__('menu.allergens.options.milk')])
        ->and($payload['items'][0])->not->toHaveKey('guest_name')
        ->and($print['items'][0])->not->toHaveKey('guest_name');
});

test('queue and print use the restaurant timezone for received and served times', function (): void {
    [$user, $department, $ticket, $order] = preparationQueueScenario();
    $order->branch->forceFill(['timezone' => 'Europe/Vilnius'])->save();
    $received = CarbonImmutable::parse('2026-09-20 12:00:00', 'UTC');
    $served = $received->addMinutes(20);
    $ticket->forceFill(['sent_at' => $received])->save();
    preparationQueueItem($ticket, KitchenTicketItemStatus::Ready)->forceFill(['served_at' => $served])->save();

    $queue = preparationQueuePayload($user, $department, DepartmentTicketFilter::History);
    $print = app(BuildDepartmentTicketPrintAction::class)->handle($user, $ticket);

    expect($queue['branch_timezone'])->toBe('Europe/Vilnius')
        ->and($queue['tickets'][0]['sent_at'])->toBe(LocalizedDateFormatter::dateTime($received->setTimezone('Europe/Vilnius')))
        ->and($queue['tickets'][0]['sent_at'])->toBe($print['ticket']['sent_at'])
        ->and($queue['tickets'][0]['items'][0]['completed_at'])->toBe(LocalizedDateFormatter::dateTime($served->setTimezone('Europe/Vilnius')))
        ->and($queue['tickets'][0]['items'][0]['completed_at'])->toBe($print['items'][0]['served_at']);
});

test('legacy readiness without a real transition event never invents its timestamp from updated at', function (): void {
    [$user, $department, $ticket] = preparationQueueScenario();
    preparationQueueItem($ticket, KitchenTicketItemStatus::Ready);

    $payload = preparationQueuePayload($user, $department, DepartmentTicketFilter::Ready)['tickets'][0];

    expect($payload['timer_phase'])->toBe('awaiting_service')
        ->and($payload['timer_known'])->toBeFalse()
        ->and($payload['ready_at'])->toBeNull();
});

test('ready waiting time starts from the event and does not use the cooking delay thresholds', function (): void {
    [$user, $department, $ticket, $order] = preparationQueueScenario();
    $item = preparationQueueItem($ticket, KitchenTicketItemStatus::Ready);
    OrderStatusLog::factory()->for($order)->create([
        'event' => OrderStatusLogEvent::TicketItemStatusChanged,
        'status_type' => 'kitchen_ticket_item',
        'new_status' => 'ready',
        'metadata' => ['kitchen_ticket_id' => $ticket->id, 'kitchen_ticket_item_id' => $item->id],
        'occurred_at' => now()->subMinutes(2),
    ]);

    $payload = preparationQueuePayload($user, $department, DepartmentTicketFilter::Ready)['tickets'][0];

    expect($payload['timer_phase'])->toBe('awaiting_service')
        ->and($payload['timer_known'])->toBeTrue()
        ->and($payload['elapsed_seconds'])->toBe(120)
        ->and($payload['delay_state'])->toBe('on-track');
});

test('queue hydration remains bounded for a single exceptionally large ticket', function (): void {
    [$user, $department, $ticket] = preparationQueueScenario();
    for ($index = 0; $index < 105; $index++) {
        preparationQueueItem($ticket, KitchenTicketItemStatus::InProgress, ['quantity' => 3]);
    }

    $payload = preparationQueuePayload($user, $department)['tickets'][0];

    expect($payload['items'])->toHaveCount(100)
        ->and($payload['full_item_count'])->toBe(105)
        ->and($payload['matching_item_count'])->toBe(105)
        ->and($payload['full_portion_count'])->toBe(315)
        ->and($payload['has_next_item_page'])->toBeTrue();
});

test('explicit ticket detail traverses the full ticket independently of the list filter', function (): void {
    [$user, $department, $ticket] = preparationQueueScenario();
    for ($index = 0; $index < 105; $index++) {
        preparationQueueItem($ticket, KitchenTicketItemStatus::InProgress);
    }
    $payload = app(BuildDepartmentDashboardAction::class)->handle(
        user: $user,
        selectedDepartmentId: $department->id,
        departmentTypes: KitchenDepartmentType::kitchenProductionTypes(),
        roleCodes: [],
        permissionCodes: [],
        filter: DepartmentTicketFilter::Ready,
        page: 8,
        selectedTicketId: $ticket->id,
        itemPage: 2,
    )['tickets'][0];

    expect($payload['items'])->toHaveCount(5)
        ->and($payload['full_item_count'])->toBe(105)
        ->and($payload['matching_item_count'])->toBe(105)
        ->and($payload['has_previous_item_page'])->toBeTrue()
        ->and($payload['has_next_item_page'])->toBeFalse();
});

test('unrouted preparation remains a bounded problem visible only in its explicit permitted restaurant family', function (): void {
    [$user, $department, $ticket] = preparationQueueScenario();
    preparationQueueItem($ticket, KitchenTicketItemStatus::New);
    $ticket->forceFill(['kitchen_department_id' => null])->save();
    $barTicket = KitchenTicket::factory()->forOrder($ticket->order)->create(['department_type' => KitchenDepartmentType::Bar->value]);
    preparationQueueItem($barTicket, KitchenTicketItemStatus::New);
    $payload = app(BuildDepartmentDashboardAction::class)->handle(
        user: $user,
        selectedDepartmentId: $department->id,
        departmentTypes: KitchenDepartmentType::kitchenProductionTypes(),
        roleCodes: [],
        permissionCodes: [],
        branchId: $department->branch_id,
    );

    expect($payload['routing_issue_count'])->toBe(1)
        ->and($payload['routing_issues'][0]['ticket_id'])->toBe($ticket->id)
        ->and($payload['tickets'])->toBeEmpty();
});

test('active counters do not scan completed history or include old cancellations', function (): void {
    [$user, $department, $ticket] = preparationQueueScenario();
    preparationQueueItem($ticket, KitchenTicketItemStatus::Ready)->forceFill(['served_at' => now()])->save();
    preparationQueueItem($ticket, KitchenTicketItemStatus::Cancelled)->forceFill(['updated_at' => now()->subDays(2)])->save();
    preparationQueueItem($ticket, KitchenTicketItemStatus::Cancelled);

    $active = preparationQueuePayload($user, $department);
    $history = preparationQueuePayload($user, $department, DepartmentTicketFilter::History);

    expect($active['completed_item_count'])->toBe(0)
        ->and($active['cancelled_item_count'])->toBe(1)
        ->and($history['completed_item_count'])->toBe(1)
        ->and($history['cancelled_item_count'])->toBe(2);
});

test('the current notification delivery failure stays visible after reloading the queue', function (): void {
    [$user, $department, $ticket, $order] = preparationQueueScenario();
    $item = preparationQueueItem($ticket, KitchenTicketItemStatus::Ready);
    OrderStatusLog::factory()->for($order)->create([
        'event' => OrderStatusLogEvent::TicketItemStatusChanged,
        'new_status' => 'ready',
        'metadata' => ['kitchen_ticket_id' => $ticket->id, 'kitchen_ticket_item_id' => $item->id, 'notifications' => ['state' => 'pending']],
    ]);

    $payload = preparationQueuePayload($user, $department, DepartmentTicketFilter::Ready);

    expect($payload['tickets'][0]['items'][0]['notification_delivery_pending'])->toBeTrue()
        ->and($payload['tickets'][0]['items'][0]['can_retry_notification'])->toBeTrue();
});

test('historical pending delivery does not offer retry after service or terminal context', function (string $terminal): void {
    [$user, $department, $ticket, $order] = preparationQueueScenario();
    $item = preparationQueueItem($ticket, KitchenTicketItemStatus::Ready);
    OrderStatusLog::factory()->for($order)->create([
        'event' => OrderStatusLogEvent::TicketItemStatusChanged,
        'new_status' => 'ready',
        'metadata' => ['kitchen_ticket_id' => $ticket->id, 'kitchen_ticket_item_id' => $item->id, 'notifications' => ['state' => 'pending']],
    ]);
    if ($terminal === 'served') {
        $item->forceFill(['served_at' => now()])->save();
    } elseif ($terminal === 'cancelled') {
        $order->forceFill(['status' => OrderStatus::Cancelled])->save();
    } else {
        $order->tableSession->forceFill(['status' => TableSessionStatus::Closed])->save();
    }
    $filter = $terminal === 'closed' ? DepartmentTicketFilter::Ready : DepartmentTicketFilter::History;
    $payload = preparationQueuePayload($user, $department, $filter)['tickets'][0]['items'][0];

    expect($payload['notification_delivery_pending'])->toBeTrue()
        ->and($payload['can_retry_notification'])->toBeFalse();
})->with(['served', 'cancelled', 'closed']);

test('whole order cancellation remains visible with its real reason when ticket row states are preserved', function (): void {
    [$user, $department, $ticket, $order] = preparationQueueScenario();
    $item = preparationQueueItem($ticket, KitchenTicketItemStatus::InProgress);
    $order->forceFill(['status' => OrderStatus::Cancelled, 'metadata' => [
        'cancelled_at' => now()->toISOString(),
        'cancellation_reason' => 'Guest left before preparation finished.',
    ]])->save();

    $payload = preparationQueuePayload($user, $department, DepartmentTicketFilter::Cancelled);
    $print = app(BuildDepartmentTicketPrintAction::class)->handle($user, $ticket);

    expect($payload['ticket_count'])->toBe(1)
        ->and($payload['tickets'][0]['work_status']['value'])->toBe('cancelled')
        ->and($payload['tickets'][0]['items'][0]['status_value'])->toBe('cancelled')
        ->and($payload['tickets'][0]['items'][0]['cancellation_reason'])->toBe('Guest left before preparation finished.')
        ->and($payload['tickets'][0]['items'][0]['primary_transition'])->toBeNull()
        ->and($print['items'][0]['status_key'])->toBe('statuses.kitchen_ticket_item.cancelled')
        ->and($print['items'][0]['cancellation_reason'])->toBe('Guest left before preparation finished.')
        ->and($item->fresh()->status)->toBe(KitchenTicketItemStatus::InProgress);
});
