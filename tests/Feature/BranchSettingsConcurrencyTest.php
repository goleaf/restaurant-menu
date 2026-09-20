<?php

declare(strict_types=1);

use App\Actions\Branches\GetBranchPollingIntervalAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\Support\BranchSettingsConcurrencyTasks;

test('independent SQLite processes preserve groups detect conflicts and converge replays', function (string $race): void {
    $path = tempnam(sys_get_temp_dir(), 'settings-concurrency-');
    $original = config('database.default');
    $connection = config('database.connections.sqlite');
    $connection['database'] = $path;
    try {
        config(['database.default' => 'settings_concurrency', 'database.connections.settings_concurrency' => $connection]);
        DB::purge('settings_concurrency');
        expect($connection['transaction_mode'])->toBe('IMMEDIATE');
        expect(Artisan::call('migrate', ['--database' => 'settings_concurrency', '--force' => true]))->toBe(0);
        $this->seed(SystemPermissionsSeeder::class);
        $actor = User::factory()->create();
        $organization = app(CreateOrganizationAction::class)->handle($actor, ['name' => 'Concurrent settings']);
        $branch = Branch::factory()->for($organization)->create();
        if ($race !== 'first insert') {
            BranchSetting::factory()->for($branch)->create();
        }
        $request = (string) Str::uuid();
        $secondGroup = in_array($race, ['independent', 'first insert'], true) ? 'advanced' : 'locale';
        $secondData = in_array($race, ['independent', 'first insert'], true)
            ? ['polling_interval_seconds' => 5, 'inactivity_warning_minutes' => 50, 'pending_session_expire_minutes' => 40]
            : ['default_language' => $race === 'same command' ? 'lt' : 'ru'];
        $tasks = [
            BranchSettingsConcurrencyTasks::save($connection, $branch->id, $actor->id, 'locale', ['default_language' => 'lt'], $request),
            BranchSettingsConcurrencyTasks::save($connection, $branch->id, $actor->id, $secondGroup, $secondData, $race === 'same command' ? $request : (string) Str::uuid()),
        ];
        config(['database.default' => $original]);
        $results = Concurrency::driver('process')->run($tasks, 20);
        config(['database.default' => 'settings_concurrency']);
        DB::purge('settings_concurrency');
        $states = array_column($results, 'result');
        sort($states);
        expect(array_unique(array_column($results, 'pid')))->toHaveCount(2)
            ->and(array_column($results, 'pid'))->not->toContain(getmypid())
            ->and($states)->toBe($race === 'same group' ? ['conflict', 'saved'] : ['saved', 'saved'])
            ->and(AuditLog::query()->where('action', 'branch_settings_changed')->count())->toBe(in_array($race, ['independent', 'first insert'], true) ? 2 : 1);
        if (in_array($race, ['independent', 'first insert'], true)) {
            expect($branch->settings()->sole()->default_language)->toBe('lt')->and($branch->settings()->sole()->polling_interval_seconds)->toBe(5);
            expect($branch->settings()->count())->toBe(1);
        }
    } finally {
        config(['database.default' => $original]);
        DB::disconnect('settings_concurrency');
        DB::purge('settings_concurrency');
        File::delete([$path, $path.'-wal', $path.'-shm', ...glob($path.'.ready.*')]);
    }
})->with(['independent', 'first insert', 'same group', 'same command']);

test('an order committed by another process after currency preview prevents currency relabeling', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'settings-money-race-');
    $original = config('database.default');
    $connection = config('database.connections.sqlite');
    $connection['database'] = $path;
    try {
        config(['database.default' => 'settings_concurrency', 'database.connections.settings_concurrency' => $connection]);
        DB::purge('settings_concurrency');
        expect(Artisan::call('migrate', ['--database' => 'settings_concurrency', '--force' => true]))->toBe(0);
        $this->seed(SystemPermissionsSeeder::class);
        $actor = User::factory()->create();
        $organization = app(CreateOrganizationAction::class)->handle($actor, ['name' => 'Currency race']);
        $branch = Branch::factory()->for($organization)->create();
        BranchSetting::factory()->for($branch)->create();
        config(['database.default' => $original]);
        $results = Concurrency::driver('process')->run([
            BranchSettingsConcurrencyTasks::currencyAfterOrder($connection, $branch->id, $actor->id, false),
            BranchSettingsConcurrencyTasks::currencyAfterOrder($connection, $branch->id, $actor->id, true),
        ], 20);
        config(['database.default' => 'settings_concurrency']);
        DB::purge('settings_concurrency');
        expect(array_unique(array_column($results, 'pid')))->toHaveCount(2)
            ->and(array_column($results, 'result'))->toBe(['conflict', 'created'])
            ->and($branch->fresh()->currency)->toBe('EUR')
            ->and(Order::query()->sole()->currency)->toBe('EUR');
    } finally {
        config(['database.default' => $original]);
        DB::disconnect('settings_concurrency');
        DB::purge('settings_concurrency');
        File::delete([$path, $path.'-wal', $path.'-shm', $path.'.preview', $path.'.order']);
    }
});

test('an independent polling cache fill cannot outlive a settings invalidation', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'settings-poll-cache-race-');
    $original = config('database.default');
    $connection = config('database.connections.sqlite');
    $connection['database'] = $path;
    try {
        config(['database.default' => 'settings_concurrency', 'database.connections.settings_concurrency' => $connection]);
        DB::purge('settings_concurrency');
        expect(Artisan::call('migrate', ['--database' => 'settings_concurrency', '--force' => true]))->toBe(0);
        $branch = Branch::factory()->create();
        BranchSetting::factory()->for($branch)->create(['polling_interval_seconds' => 1]);
        config(['database.default' => $original]);
        $results = Concurrency::driver('process')->run([
            BranchSettingsConcurrencyTasks::pollingCacheRace($connection, $branch->id, false),
            BranchSettingsConcurrencyTasks::pollingCacheRace($connection, $branch->id, true),
        ], 20);
        config(['database.default' => 'settings_concurrency']);
        DB::purge('settings_concurrency');
        Cache::purge('database');
        expect(array_unique(array_column($results, 'pid')))->toHaveCount(2)
            ->and(app(GetBranchPollingIntervalAction::class)->handle($branch->id))->toBe(15);
    } finally {
        config(['database.default' => $original]);
        DB::disconnect('settings_concurrency');
        DB::purge('settings_concurrency');
        Cache::purge('database');
        File::delete([$path, $path.'-wal', $path.'-shm', ...glob($path.'.cache-*')]);
    }
});
