<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Branches\GetBranchPollingIntervalAction;
use App\Actions\Branches\SaveBranchSettingsGroupAction;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Order;
use App\Models\User;
use App\Services\Branches\BranchSettingsQueryService;
use App\Support\Branches\BranchSettingsGroup;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class BranchSettingsConcurrencyTasks
{
    /** @param array<string,mixed> $connection @param array<string,mixed> $data */
    public static function save(array $connection, int $branchId, int $actorId, string $group, array $data, string $requestId): Closure
    {
        return static function () use ($connection, $branchId, $actorId, $group, $data, $requestId): array {
            config(['database.default' => 'settings_concurrency', 'database.connections.settings_concurrency' => $connection]);
            DB::purge('settings_concurrency');
            $actor = User::query()->findOrFail($actorId);
            $branch = Branch::query()->findOrFail($branchId);
            $settings = app(BranchSettingsQueryService::class)->effective($branch);
            $version = BranchSettingsGroup::fingerprint($branch, $settings, $group);
            file_put_contents($connection['database'].'.ready.'.getmypid(), 'ready');
            $deadline = microtime(true) + 5;
            while (count(glob($connection['database'].'.ready.*')) < 2 && microtime(true) < $deadline) {
                usleep(10000);
            }
            if (count(glob($connection['database'].'.ready.*')) !== 2) {
                throw new RuntimeException('Both settings writers must read before either writes.');
            }
            try {
                app(SaveBranchSettingsGroupAction::class)->handle($actor, $branch, $group, $data, $version, $requestId);

                return ['result' => 'saved', 'pid' => getmypid()];
            } catch (ValidationException) {
                return ['result' => 'conflict', 'pid' => getmypid()];
            }
        };
    }

    /** @param array<string,mixed> $connection */
    public static function currencyAfterOrder(array $connection, int $branchId, int $actorId, bool $createOrder): Closure
    {
        return static function () use ($connection, $branchId, $actorId, $createOrder): array {
            config(['database.default' => 'settings_concurrency', 'database.connections.settings_concurrency' => $connection]);
            DB::purge('settings_concurrency');
            $branch = Branch::query()->findOrFail($branchId);
            $actor = User::query()->findOrFail($actorId);
            $settings = app(BranchSettingsQueryService::class)->effective($branch);
            $version = BranchSettingsGroup::fingerprint($branch, $settings, 'settlement');
            $waitFor = $connection['database'].($createOrder ? '.preview' : '.order');
            if (! $createOrder) {
                file_put_contents($connection['database'].'.preview', 'ready');
            }
            $deadline = microtime(true) + 5;
            while (! file_exists($waitFor) && microtime(true) < $deadline) {
                usleep(10000);
            }
            if (! file_exists($waitFor)) {
                throw new RuntimeException('Currency and order writers failed to rendezvous.');
            }
            if ($createOrder) {
                Order::factory()->for($branch)->create();
                file_put_contents($connection['database'].'.order', 'committed');

                return ['result' => 'created', 'pid' => getmypid()];
            }
            try {
                app(SaveBranchSettingsGroupAction::class)->handle($actor, $branch, 'settlement', [
                    'default_currency' => 'USD', 'service_charge_enabled' => false,
                    'service_charge_percent' => '0.00', 'tips_enabled' => false,
                ], $version, (string) Str::uuid());

                return ['result' => 'saved', 'pid' => getmypid()];
            } catch (ValidationException) {
                return ['result' => 'conflict', 'pid' => getmypid()];
            }
        };
    }

    /** @param array<string,mixed> $connection */
    public static function pollingCacheRace(array $connection, int $branchId, bool $writer): Closure
    {
        return static function () use ($connection, $branchId, $writer): array {
            config(['database.default' => 'settings_concurrency', 'database.connections.settings_concurrency' => $connection]);
            DB::purge('settings_concurrency');
            Cache::purge('database');
            $path = $connection['database'];
            if ($writer) {
                $deadline = microtime(true) + 5;
                while (! file_exists($path.'.cache-read') && microtime(true) < $deadline) {
                    usleep(10000);
                }
                if (! file_exists($path.'.cache-read')) {
                    throw new RuntimeException('Polling reader did not reach its cold read.');
                }
                file_put_contents($path.'.cache-writing', 'started');
                BranchSetting::query()->where('branch_id', $branchId)->sole()->update(['polling_interval_seconds' => 15]);
                $value = app(GetBranchPollingIntervalAction::class)->handle($branchId);
                file_put_contents($path.'.cache-written', 'published');
            } else {
                $waiting = false;
                DB::listen(function ($query) use ($path, &$waiting): void {
                    if ($waiting || ! str_contains($query->sql, 'from "branch_settings"')) {
                        return;
                    }
                    $waiting = true;
                    file_put_contents($path.'.cache-read', 'read');
                    $deadline = microtime(true) + 5;
                    while (! file_exists($path.'.cache-writing') && microtime(true) < $deadline) {
                        usleep(10000);
                    }
                    if (! file_exists($path.'.cache-writing')) {
                        throw new RuntimeException('Polling writer did not start.');
                    }
                    $deadline = microtime(true) + 0.5;
                    while (! file_exists($path.'.cache-written') && microtime(true) < $deadline) {
                        usleep(10000);
                    }
                });
                $value = app(GetBranchPollingIntervalAction::class)->handle($branchId);
            }

            return ['value' => $value, 'pid' => getmypid()];
        };
    }
}
