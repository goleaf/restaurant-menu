<?php

use App\Enums\ServicePointStatus;
use App\Enums\TableSessionJoinRequestStatus;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\TableSessionJoinRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Support\GuestSessionConcurrencyTasks;

test('concurrent first entries create one host guest and one approvable join request', function (): void {
    $databasePath = tempnam(sys_get_temp_dir(), 'restaurant-qr-concurrency-');

    expect($databasePath)->toBeString();

    $connectionName = 'guest_concurrency';
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

        $servicePoint = ServicePoint::factory()->create([
            'is_active' => true,
            'status' => ServicePointStatus::Occupied,
        ]);
        $tableSession = TableSession::factory()
            ->forServicePoint($servicePoint)
            ->active()
            ->waiterOpened()
            ->create();
        $servicePointId = $servicePoint->id;

        config(['database.default' => $originalDefaultConnection]);

        $entryTasks = [
            GuestSessionConcurrencyTasks::enter(
                $connection,
                $connectionName,
                $servicePointId,
                'Concurrent Ana',
                str_repeat('A', 64),
            ),
            GuestSessionConcurrencyTasks::enter(
                $connection,
                $connectionName,
                $servicePointId,
                'Concurrent Boris',
                str_repeat('B', 64),
            ),
        ];

        $entryResults = Concurrency::driver('process')->run($entryTasks, 20);

        config(['database.default' => $connectionName]);
        DB::purge($connectionName);

        expect(collect($entryResults)->pluck('state')->sort()->values()->all())->toBe([
            'active_session_joined',
            'join_request_created',
        ]);

        $hostGuest = TableSessionGuest::query()
            ->where('table_session_id', $tableSession->id)
            ->firstOrFail();
        $joinRequest = TableSessionJoinRequest::query()
            ->where('table_session_id', $tableSession->id)
            ->firstOrFail();

        expect($tableSession->fresh()->opened_by_guest_id)->toBe($hostGuest->id)
            ->and(TableSessionGuest::query()->where('table_session_id', $tableSession->id)->count())->toBe(1)
            ->and(TableSessionJoinRequest::query()->where('table_session_id', $tableSession->id)->count())->toBe(1)
            ->and($joinRequest->status)->toBe(TableSessionJoinRequestStatus::Pending);

        $approvalTasks = [
            GuestSessionConcurrencyTasks::approve(
                $connection,
                $connectionName,
                $hostGuest->id,
                $joinRequest->id,
            ),
            GuestSessionConcurrencyTasks::approve(
                $connection,
                $connectionName,
                $hostGuest->id,
                $joinRequest->id,
            ),
        ];

        config(['database.default' => $originalDefaultConnection]);
        $approvedGuestIds = Concurrency::driver('process')->run($approvalTasks, 20);

        config(['database.default' => $connectionName]);
        DB::purge($connectionName);

        expect(array_unique($approvedGuestIds))->toHaveCount(1)
            ->and(TableSessionGuest::query()->where('table_session_id', $tableSession->id)->count())->toBe(2)
            ->and(TableSessionGuest::query()->where('guest_token', $joinRequest->guest_token)->count())->toBe(1)
            ->and($joinRequest->fresh()->status)->toBe(TableSessionJoinRequestStatus::Approved);
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
