<?php

declare(strict_types=1);

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Backups\CreateConsistentSqliteBackupAction;
use App\Actions\Backups\RestoreSqliteBackupAction;
use App\Enums\SystemRole;
use App\Exceptions\InvalidSqliteBackupException;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('a compatible sqlite backup restores data and retains a safety snapshot', function (): void {
    $sandbox = sqliteRestoreSandbox('compatible');
    $candidateArtifactsBefore = sqliteRestoreCandidateArtifacts();
    $originalDefault = config('database.default');
    $originalCacheDefault = config('cache.default');
    $originalSessionConnection = config('session.connection');
    $originalSessionDriver = config('session.driver');

    try {
        Artisan::call('migrate', [
            '--database' => $sandbox['connection'],
            '--force' => true,
        ]);

        $restoredUser = User::factory()
            ->connection($sandbox['connection'])
            ->create(['name' => 'Name in verified backup']);

        config()->set('database.default', $sandbox['connection']);
        config()->set('cache.default', 'database');
        config()->set('session.connection', $sandbox['connection']);
        config()->set('session.driver', 'database');
        app(SessionManager::class)->forgetDrivers();

        Cache::put('stale-after-restore', 'cached before snapshot', now()->addHour());
        $sessionHandler = app(SessionManager::class)->driver()->getHandler();
        $sessionHandler->write('session-that-must-be-invalidated', serialize(['user_id' => $restoredUser->id]));

        $sourceBackup = app(CreateConsistentSqliteBackupAction::class)->handle();

        User::on($sandbox['connection'])
            ->whereKey($restoredUser->id)
            ->update(['name' => 'Name after accidental change']);

        $actor = User::factory()->make([
            'id' => 999_999,
            'email' => 'restore-operator@example.test',
        ]);

        $result = app(RestoreSqliteBackupAction::class)->handle(
            uploadedPath: $sourceBackup,
            actor: $actor,
            reason: 'Recovering after verified data loss',
        );

        DB::purge($sandbox['connection']);

        expect(User::on($sandbox['connection'])->findOrFail($restoredUser->id)->name)
            ->toBe('Name in verified backup')
            ->and($result['safety_backup_path'])->toBeFile()
            ->and(File::get($result['safety_backup_path']))->toStartWith('SQLite format 3')
            ->and(AuditLog::on($sandbox['connection'])
                ->where('action', 'backup_restored')
                ->value('new_values'))
            ->toContain('Recovering after verified data loss')
            ->and(Cache::get('stale-after-restore'))->toBeNull()
            ->and($sessionHandler->read('session-that-must-be-invalidated'))->toBe('')
            ->and(array_values(array_diff(sqliteRestoreCandidateArtifacts(), $candidateArtifactsBefore)))->toBe([]);
    } finally {
        config()->set('cache.default', $originalCacheDefault);
        config()->set('database.default', $originalDefault);
        config()->set('session.connection', $originalSessionConnection);
        config()->set('session.driver', $originalSessionDriver);
        app(SessionManager::class)->forgetDrivers();
        app('cache')->forgetDriver('database');
        DB::purge($sandbox['connection']);
        config()->set("database.connections.{$sandbox['connection']}", null);
        File::deleteDirectory($sandbox['directory']);

        if (isset($sourceBackup)) {
            File::delete($sourceBackup);
        }

        if (isset($result['safety_backup_path'])) {
            File::delete($result['safety_backup_path']);
        }
    }
});

test('an incompatible sqlite schema is rejected without changing live data', function (): void {
    $sandbox = sqliteRestoreSandbox('incompatible-live');
    $candidate = sqliteRestoreSandbox('incompatible-candidate');
    $originalDefault = config('database.default');

    try {
        Artisan::call('migrate', [
            '--database' => $sandbox['connection'],
            '--force' => true,
        ]);

        $liveUser = User::factory()
            ->connection($sandbox['connection'])
            ->create(['name' => 'Live data must survive']);

        Schema::connection($candidate['connection'])->create('unrelated_records', function (Blueprint $table): void {
            $table->id();
            $table->string('value');
        });

        config()->set('database.default', $sandbox['connection']);

        $actor = User::factory()->make([
            'id' => 999_999,
            'email' => 'restore-operator@example.test',
        ]);

        expect(fn () => app(RestoreSqliteBackupAction::class)->handle(
            uploadedPath: $candidate['database'],
            actor: $actor,
            reason: 'Attempting incompatible recovery',
        ))->toThrow(InvalidSqliteBackupException::class);

        DB::purge($sandbox['connection']);

        expect(User::on($sandbox['connection'])->findOrFail($liveUser->id)->name)
            ->toBe('Live data must survive');
    } finally {
        config()->set('database.default', $originalDefault);

        foreach ([$sandbox, $candidate] as $database) {
            DB::purge($database['connection']);
            config()->set("database.connections.{$database['connection']}", null);
            File::deleteDirectory($database['directory']);
        }
    }
});

test('the protected restore endpoint restores sqlite and signs every session out', function (): void {
    $sandbox = sqliteRestoreSandbox('http-success');
    $originalDefault = config('database.default');

    try {
        Artisan::call('migrate', [
            '--database' => $sandbox['connection'],
            '--force' => true,
        ]);

        config()->set('database.default', $sandbox['connection']);
        app(SystemPermissionsSeeder::class)->run();

        $superadmin = User::factory()
            ->connection($sandbox['connection'])
            ->create([
                'name' => 'Name in uploaded backup',
                'email' => 'restore-http@example.test',
            ]);
        $role = Role::on($sandbox['connection'])
            ->where('code', SystemRole::Superadmin->value)
            ->firstOrFail();
        $superadmin->roles()->syncWithoutDetachingOrFail([$role->id]);

        $sourceBackup = app(CreateConsistentSqliteBackupAction::class)->handle();

        User::on($sandbox['connection'])
            ->whereKey($superadmin->id)
            ->update(['name' => 'Name after accidental change']);

        $response = $this->actingAs($superadmin)
            ->withSession([
                'auth.password_confirmed_at' => now()->timestamp,
                'sqlite_backup_restore_authorization' => [
                    'issued_at' => now()->timestamp,
                    'nonce' => Str::random(64),
                    'reason' => 'HTTP disaster recovery verification',
                    'user_id' => $superadmin->id,
                ],
            ])
            ->post(route('superadmin.backups.sqlite.restore.store'), [
                'backup' => new UploadedFile(
                    path: $sourceBackup,
                    originalName: 'verified-backup.sqlite',
                    mimeType: 'application/vnd.sqlite3',
                    test: true,
                ),
            ]);

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', __('ui.superadmin.backup_restore.completed'));

        DB::purge($sandbox['connection']);

        $audit = AuditLog::on($sandbox['connection'])
            ->where('action', 'backup_restored')
            ->firstOrFail();
        $safetyBackupPath = storage_path('app/private/backups/sqlite/'.($audit->new_values['safety_snapshot'] ?? ''));

        expect(User::on($sandbox['connection'])->findOrFail($superadmin->id)->name)
            ->toBe('Name in uploaded backup')
            ->and(User::on($sandbox['connection'])->findOrFail($superadmin->id)->remember_token)->toBeNull()
            ->and($audit->new_values)->toMatchArray([
                'initiated_by_user_id' => $superadmin->id,
                'reason' => 'HTTP disaster recovery verification',
            ])
            ->and($safetyBackupPath)->toBeFile();
    } finally {
        config()->set('database.default', $originalDefault);
        DB::purge($sandbox['connection']);
        config()->set("database.connections.{$sandbox['connection']}", null);
        File::deleteDirectory($sandbox['directory']);

        if (isset($sourceBackup)) {
            File::delete($sourceBackup);
        }

        if (isset($safetyBackupPath)) {
            File::delete($safetyBackupPath);
        }
    }
});

test('a restore failure after replacement automatically rolls the live database back', function (): void {
    $sandbox = sqliteRestoreSandbox('automatic-rollback');
    $originalDefault = config('database.default');
    $backupDirectory = storage_path('app/private/backups/sqlite');
    $backupFilesBefore = collect(File::glob($backupDirectory.'/*.sqlite'));

    try {
        Artisan::call('migrate', [
            '--database' => $sandbox['connection'],
            '--force' => true,
        ]);

        config()->set('database.default', $sandbox['connection']);

        $restoredUser = User::factory()
            ->connection($sandbox['connection'])
            ->create(['name' => 'Older backup state']);
        $sourceBackup = app(CreateConsistentSqliteBackupAction::class)->handle();

        User::on($sandbox['connection'])
            ->whereKey($restoredUser->id)
            ->update(['name' => 'Current live state']);

        $auditLog = Mockery::mock(RecordAuditLogAction::class);
        $auditLog->shouldReceive('handle')
            ->once()
            ->andThrow(new RuntimeException('Simulated post-replacement failure.'));
        app()->instance(RecordAuditLogAction::class, $auditLog);

        $actor = User::factory()->make([
            'id' => 999_999,
            'email' => 'restore-operator@example.test',
        ]);

        expect(fn () => app(RestoreSqliteBackupAction::class)->handle(
            uploadedPath: $sourceBackup,
            actor: $actor,
            reason: 'Testing automatic rollback',
        ))->toThrow(RuntimeException::class, 'Simulated post-replacement failure.');

        DB::purge($sandbox['connection']);

        expect(User::on($sandbox['connection'])->findOrFail($restoredUser->id)->name)
            ->toBe('Current live state')
            ->and(app(MaintenanceMode::class)->active())->toBeFalse();
    } finally {
        app()->forgetInstance(RecordAuditLogAction::class);
        config()->set('database.default', $originalDefault);
        DB::purge($sandbox['connection']);
        config()->set("database.connections.{$sandbox['connection']}", null);
        File::deleteDirectory($sandbox['directory']);

        if (isset($sourceBackup)) {
            File::delete($sourceBackup);
        }

        collect(File::glob($backupDirectory.'/*.sqlite'))
            ->diff($backupFilesBefore)
            ->each(fn (string $path): bool => File::delete($path));
    }
});

/**
 * @return array{connection: string, database: string, directory: string}
 */
function sqliteRestoreSandbox(string $suffix): array
{
    $connection = 'sqlite_restore_'.str_replace('-', '_', $suffix);
    $directory = storage_path('framework/testing/'.$connection);
    $database = $directory.'/database.sqlite';

    File::ensureDirectoryExists($directory);
    File::put($database, '');

    config()->set("database.connections.{$connection}", [
        'driver' => 'sqlite',
        'database' => $database,
        'prefix' => '',
        'foreign_key_constraints' => true,
        'busy_timeout' => 5000,
        'journal_mode' => 'WAL',
        'synchronous' => 'NORMAL',
        'transaction_mode' => 'IMMEDIATE',
    ]);

    return [
        'connection' => $connection,
        'database' => $database,
        'directory' => $directory,
    ];
}

/**
 * @return list<string>
 */
function sqliteRestoreCandidateArtifacts(): array
{
    return collect(File::glob(storage_path('app/private/backups/sqlite/restore-candidates/*')))
        ->sort()
        ->values()
        ->all();
}
