<?php

use App\Actions\TableSessions\BuildTableSessionInactivityStateAction;
use App\Actions\TableSessions\CleanupInactiveTableSessionsAction;
use App\Enums\OrderStatus;
use App\Enums\TableSessionStatus;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\DraftOrder;
use App\Models\Order;
use App\Models\ServicePoint;
use App\Models\TableSession;
use Carbon\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-04 12:00:00', 'Europe/Vilnius'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

test('pending guest sessions older than branch setting are cancelled safely', function (): void {
    $branch = Branch::factory()->create(['timezone' => 'Europe/Vilnius']);
    $servicePoint = ServicePoint::factory()->for($branch)->create();
    BranchSetting::factory()->for($branch)->create([
        'pending_session_expire_minutes' => 30,
        'inactivity_warning_minutes' => 45,
    ]);
    $tableSession = oldTableSession($branch, $servicePoint, TableSessionStatus::Pending, 46);

    $result = app(CleanupInactiveTableSessionsAction::class)->handle();

    $tableSession->refresh();

    expect($result['pending_cancelled'])->toBe(1)
        ->and($tableSession->status)->toBe(TableSessionStatus::Cancelled)
        ->and($tableSession->ended_at)->not->toBeNull()
        ->and(data_get($tableSession->metadata, 'cleanup.reason'))->toBe('pending_session_expired');
});

test('pending sessions with unpaid orders are skipped', function (): void {
    $branch = Branch::factory()->create(['timezone' => 'Europe/Vilnius']);
    $servicePoint = ServicePoint::factory()->for($branch)->create();
    BranchSetting::factory()->for($branch)->create([
        'pending_session_expire_minutes' => 30,
        'inactivity_warning_minutes' => 45,
    ]);
    $tableSession = oldTableSession($branch, $servicePoint, TableSessionStatus::Pending, 46);
    $draftOrder = DraftOrder::factory()->for($tableSession)->create();

    Order::factory()->create([
        'branch_id' => $branch->id,
        'service_point_id' => $servicePoint->id,
        'table_session_id' => $tableSession->id,
        'draft_order_id' => $draftOrder->id,
        'status' => OrderStatus::ConfirmedByWaiter,
    ]);

    $result = app(CleanupInactiveTableSessionsAction::class)->handle();

    expect($result['pending_cancelled'])->toBe(0)
        ->and($result['skipped_unpaid_orders'])->toBe(1)
        ->and($tableSession->fresh()->status)->toBe(TableSessionStatus::Pending);
});

test('active sessions get an inactivity warning but are not auto closed', function (): void {
    $branch = Branch::factory()->create(['timezone' => 'Europe/Vilnius']);
    $servicePoint = ServicePoint::factory()->for($branch)->create();
    BranchSetting::factory()->for($branch)->create([
        'pending_session_expire_minutes' => 30,
        'inactivity_warning_minutes' => 45,
    ]);
    $tableSession = oldTableSession($branch, $servicePoint, TableSessionStatus::Active, 61);

    $cleanupResult = app(CleanupInactiveTableSessionsAction::class)->handle();
    $warning = app(BuildTableSessionInactivityStateAction::class)->handle($tableSession->fresh());

    expect($cleanupResult['active_warnings'])->toBe(1)
        ->and($tableSession->fresh()->status)->toBe(TableSessionStatus::Active)
        ->and($warning['should_warn'])->toBeTrue()
        ->and($warning['minutes_inactive'])->toBeGreaterThanOrEqual(61);
});

test('cleanup command can be run manually for shared hosting without cron', function (): void {
    $branch = Branch::factory()->create(['timezone' => 'Europe/Vilnius']);
    $servicePoint = ServicePoint::factory()->for($branch)->create();
    BranchSetting::factory()->for($branch)->create([
        'pending_session_expire_minutes' => 30,
        'inactivity_warning_minutes' => 45,
    ]);
    $tableSession = oldTableSession($branch, $servicePoint, TableSessionStatus::Pending, 46);

    $this->artisan('table-sessions:cleanup-inactive')
        ->assertSuccessful();

    expect($tableSession->fresh()->status)->toBe(TableSessionStatus::Cancelled);
});

function oldTableSession(
    Branch $branch,
    ServicePoint $servicePoint,
    TableSessionStatus $status,
    int $minutesOld,
): TableSession {
    $oldTimestamp = now()->subMinutes($minutesOld);

    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->create([
            'branch_id' => $branch->id,
            'status' => $status,
            'started_at' => $oldTimestamp,
            'created_at' => $oldTimestamp,
            'updated_at' => $oldTimestamp,
        ]);

    $tableSession->forceFill(['updated_at' => $oldTimestamp])->save();

    return $tableSession;
}
