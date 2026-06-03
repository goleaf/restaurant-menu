<?php

use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionSource;
use App\Enums\TableSessionStatus;
use App\Models\Branch;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

test('table sessions table stores branch service point opening and lifecycle fields', function () {
    expect(Schema::hasTable('table_sessions'))->toBeTrue();
    expect(Schema::hasColumns('table_sessions', [
        'branch_id',
        'service_point_id',
        'active_service_point_id',
        'pending_service_point_id',
        'opened_by_user_id',
        'opened_by_guest_id',
        'status',
        'source',
        'started_at',
        'ended_at',
        'closed_by_user_id',
        'metadata',
    ]))->toBeTrue();
});

test('table session guests table stores guest identity inside a table session', function () {
    expect(Schema::hasTable('table_session_guests'))->toBeTrue();
    expect(Schema::hasColumns('table_session_guests', [
        'table_session_id',
        'guest_name',
        'guest_token',
        'status',
        'ready_at',
        'joined_at',
        'left_at',
        'metadata',
    ]))->toBeTrue();
});

test('table session guest readiness is stored separately from guest status', function () {
    $readyAt = now()->startOfSecond();
    $guest = TableSessionGuest::factory()->create(['ready_at' => null]);

    expect($guest->fresh()->status)->toBe(TableSessionGuestStatus::Active)
        ->and($guest->fresh()->ready_at)->toBeNull();

    $guest->update(['ready_at' => $readyAt]);

    expect($guest->fresh()->status)->toBe(TableSessionGuestStatus::Active)
        ->and($guest->fresh()->ready_at?->equalTo($readyAt))->toBeTrue();
});

test('table session guest statuses include current and future join states', function () {
    expect(TableSessionGuestStatus::values())->toBe([
        'pending_approval',
        'active',
        'rejected',
        'left',
        'removed',
    ]);
});

test('table session statuses include the fixed lifecycle taxonomy', function () {
    expect(TableSessionStatus::values())->toBe([
        'pending',
        'active',
        'waiting_waiter_confirmation',
        'payment_requested',
        'paid',
        'closed',
        'cancelled',
    ]);
});

test('table session sources include waiter and guest opening paths', function () {
    expect(TableSessionSource::values())->toBe([
        'waiter_opened',
        'guest_created',
    ]);
});

test('table session defaults are safe before orders and guests exist', function () {
    $servicePoint = ServicePoint::factory()->create();

    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->create();

    expect($tableSession->fresh()->status)->toBe(TableSessionStatus::Pending);
    expect($tableSession->fresh()->source)->toBe(TableSessionSource::GuestCreated);
    expect($tableSession->fresh()->started_at)->toBeNull();
    expect($tableSession->fresh()->ended_at)->toBeNull();
    expect($tableSession->fresh()->opened_by_user_id)->toBeNull();
    expect($tableSession->fresh()->opened_by_guest_id)->toBeNull();
    expect($tableSession->fresh()->pending_service_point_id)->toBe($servicePoint->id);
    expect($tableSession->fresh()->metadata)->toBe([]);
});

test('table session factory keeps branch aligned with service point branch', function () {
    $tableSession = TableSession::factory()->create();

    expect($tableSession->branch_id)->toBe($tableSession->servicePoint->branch_id);
});

test('table session belongs to branch service point and audit users', function () {
    $branch = Branch::factory()->create();
    $servicePoint = ServicePoint::factory()->for($branch)->create(['name' => 'Table 30']);
    $waiter = User::factory()->create();
    $closer = User::factory()->create();
    $startedAt = now()->subMinutes(10)->startOfSecond();
    $endedAt = now()->startOfSecond();

    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->waiterOpened()
        ->create([
            'opened_by_user_id' => $waiter->id,
            'status' => TableSessionStatus::Closed,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'closed_by_user_id' => $closer->id,
            'metadata' => ['note' => 'Manual close'],
        ]);

    expect($tableSession->branch->is($branch))->toBeTrue();
    expect($tableSession->servicePoint->is($servicePoint))->toBeTrue();
    expect($tableSession->openedByUser->is($waiter))->toBeTrue();
    expect($tableSession->closedByUser->is($closer))->toBeTrue();
    expect($branch->tableSessions()->whereKey($tableSession->id)->exists())->toBeTrue();
    expect($servicePoint->tableSessions()->whereKey($tableSession->id)->exists())->toBeTrue();
    expect($waiter->openedTableSessions()->whereKey($tableSession->id)->exists())->toBeTrue();
    expect($closer->closedTableSessions()->whereKey($tableSession->id)->exists())->toBeTrue();
    expect($tableSession->fresh()->source)->toBe(TableSessionSource::WaiterOpened);
    expect($tableSession->fresh()->status)->toBe(TableSessionStatus::Closed);
    expect($tableSession->fresh()->started_at?->equalTo($startedAt))->toBeTrue();
    expect($tableSession->fresh()->ended_at?->equalTo($endedAt))->toBeTrue();
    expect($tableSession->fresh()->metadata)->toBe(['note' => 'Manual close']);
});

test('table session belongs to guests and exposes active guests alphabetically', function () {
    $tableSession = TableSession::factory()->create();
    $zara = TableSessionGuest::factory()
        ->for($tableSession)
        ->create(['guest_name' => 'Zara']);
    $ana = TableSessionGuest::factory()
        ->for($tableSession)
        ->create(['guest_name' => 'Ana']);
    TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Mira',
            'status' => TableSessionGuestStatus::Left,
        ]);

    expect($tableSession->guests()->pluck('guest_name')->all())->toBe([
        'Ana',
        'Mira',
        'Zara',
    ]);
    expect($tableSession->activeGuests()->pluck('id')->all())->toBe([
        $ana->id,
        $zara->id,
    ]);
});

test('guest created table session can store opening session guest id', function () {
    $servicePoint = ServicePoint::factory()->create();
    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->create([
            'source' => TableSessionSource::GuestCreated,
            'status' => TableSessionStatus::Pending,
        ]);
    $guest = TableSessionGuest::factory()
        ->for($tableSession)
        ->create(['guest_name' => 'Opening guest']);

    $tableSession->forceFill(['opened_by_guest_id' => $guest->id])->save();

    expect($tableSession->fresh()->opened_by_guest_id)->toBe($guest->id);
    expect($tableSession->fresh()->openedByGuest->is($guest))->toBeTrue();
    expect($tableSession->fresh()->source)->toBe(TableSessionSource::GuestCreated);
    expect($tableSession->fresh()->status)->toBe(TableSessionStatus::Pending);
});
