<?php

declare(strict_types=1);

use App\Http\Middleware\CoordinateSqliteRestore;
use App\Support\SqliteRestoreRequestLock;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Http\Request;

test('restore lock waits for running requests and retains exclusivity through response persistence', function (): void {
    $path = sys_get_temp_dir().'/restore-lock-'.bin2hex(random_bytes(8));
    $request = new SqliteRestoreRequestLock($path);
    $other = fopen($path, 'c+');
    try {
        $request->beginRequest();
        expect(flock($other, LOCK_EX | LOCK_NB))->toBeFalse();
        $request->acquireExclusive();
        $request->releaseOutsideRequest();
        expect(flock($other, LOCK_SH | LOCK_NB))->toBeFalse();
        $request->endRequest();
        expect(flock($other, LOCK_EX | LOCK_NB))->toBeTrue();
    } finally {
        $request->endRequest();
        fclose($other);
        unlink($path);
    }
});

test('a restore process cannot pass an in-flight request lock', function (): void {
    $path = sys_get_temp_dir().'/restore-process-'.bin2hex(random_bytes(8));
    $request = new SqliteRestoreRequestLock($path);
    $request->beginRequest();
    $script = 'require $argv[1]; $lock = new App\\Support\\SqliteRestoreRequestLock($argv[2]); echo "waiting\\n"; $lock->acquireExclusive(); echo "acquired\\n"; $lock->releaseOutsideRequest();';
    $process = proc_open([PHP_BINARY, '-r', $script, base_path('vendor/autoload.php'), $path], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    try {
        expect(trim(fgets($pipes[1])))->toBe('waiting');
        stream_set_blocking($pipes[1], false);
        usleep(50_000);
        expect(stream_get_contents($pipes[1]))->toBe('')->and(proc_get_status($process)['running'])->toBeTrue();
        $request->endRequest();
        stream_set_blocking($pipes[1], true);
        expect(trim(fgets($pipes[1])))->toBe('acquired');
    } finally {
        $request->endRequest();
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
        proc_close($process);
        unlink($path);
    }
});

test('restore HTTP middleware holds exclusivity until the inner session response has finished', function (): void {
    $path = sys_get_temp_dir().'/restore-http-'.bin2hex(random_bytes(8));
    $lock = new SqliteRestoreRequestLock($path);
    $maintenance = Mockery::mock(MaintenanceMode::class);
    $maintenance->shouldReceive('active')->once()->andReturn(false);
    $middleware = new CoordinateSqliteRestore($lock, $maintenance);
    $probe = fopen($path, 'c+');
    try {
        $middleware->handle(Request::create('/superadmin/backups/sqlite/restore', 'POST'), function () use ($lock, $probe) {
            $lock->acquireExclusive();
            $lock->releaseOutsideRequest();
            expect(flock($probe, LOCK_SH | LOCK_NB))->toBeFalse();

            return response('Session persistence completed');
        });
        expect(flock($probe, LOCK_EX | LOCK_NB))->toBeTrue();
    } finally {
        fclose($probe);
        unlink($path);
    }
});

test('an interrupted restore remains blocked independently of database maintenance state', function (): void {
    $path = sys_get_temp_dir().'/restore-recovery-'.bin2hex(random_bytes(8));
    $lock = new SqliteRestoreRequestLock($path);
    try {
        $lock->acquireExclusive();
        $lock->markUnsafe();
        $lock->releaseOutsideRequest();
        $anotherRequest = new SqliteRestoreRequestLock($path);
        expect($anotherRequest->requiresRecovery())->toBeTrue();
        $anotherRequest->acquireExclusive();
        $anotherRequest->markSafe();
        $anotherRequest->releaseOutsideRequest();
        expect($lock->requiresRecovery())->toBeFalse();
    } finally {
        $lock->releaseOutsideRequest();
        @unlink($path.'.blocked');
        unlink($path);
    }
});
