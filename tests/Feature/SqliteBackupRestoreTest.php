<?php

declare(strict_types=1);

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Backups\CreateConsistentSqliteBackupAction;
use App\Actions\Backups\RestoreSqliteBackupAction;
use App\Enums\SystemRole;
use App\Exceptions\InvalidSqliteBackupException;
use App\Livewire\Superadmin\Backups\RestoreSqlite;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Support\SqliteRestoreRequestLock;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\EventBus;
use Livewire\Livewire;
use Tests\Support\FileOperationPage;

beforeEach(function (): void {
    $temporaryLocalRoot = storage_path('framework/testing/sqlite_restore_local_'.getmypid().'_'.Str::lower(Str::random(8)));

    config()->set([
        'testing.sqlite_restore.original_local_root' => config('filesystems.disks.local.root'),
        'testing.sqlite_restore.temporary_local_root' => $temporaryLocalRoot,
        'filesystems.disks.local.root' => $temporaryLocalRoot,
        'cache.stores.file.path' => $temporaryLocalRoot.'/cache',
        'cache.stores.file.lock_path' => $temporaryLocalRoot.'/cache-locks',
        'session.files' => $temporaryLocalRoot.'/sessions',
    ]);
    app()->instance(SqliteRestoreRequestLock::class, new SqliteRestoreRequestLock($temporaryLocalRoot.'/requests.lock'));
    File::ensureDirectoryExists($temporaryLocalRoot.'/sessions');
    app(FilesystemManager::class)->forgetDisk('local');

    app()->instance(MaintenanceMode::class, new class implements MaintenanceMode
    {
        /** @var array<string, mixed>|null */
        private ?array $payload = null;

        /** @param array<string, mixed> $payload */
        public function activate(array $payload): void
        {
            $this->payload = $payload;
        }

        public function deactivate(): void
        {
            $this->payload = null;
        }

        public function active(): bool
        {
            return $this->payload !== null;
        }

        /** @return array<string, mixed> */
        public function data(): array
        {
            return $this->payload ?? [];
        }
    });
});

afterEach(function (): void {
    $filesystem = app(FilesystemManager::class);
    $temporaryLocalRoot = config('testing.sqlite_restore.temporary_local_root');
    $originalLocalRoot = config('testing.sqlite_restore.original_local_root');

    $filesystem->forgetDisk('local');
    config()->set('filesystems.disks.local.root', $originalLocalRoot);
    config()->set('testing.sqlite_restore', null);

    if (is_string($temporaryLocalRoot)) {
        File::deleteDirectory($temporaryLocalRoot);
    }
});

test('a compatible sqlite backup restores data and retains a safety snapshot', function (string $cacheDriver, string $sessionDriver): void {
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
        config()->set('cache.default', $cacheDriver);
        config()->set('session.connection', $sandbox['connection']);
        config()->set('session.driver', $sessionDriver);
        app(SessionManager::class)->forgetDrivers();

        Cache::put('stale-after-restore', 'cached before snapshot', now()->addHour());
        $sessionHandler = app(SessionManager::class)->driver()->getHandler();
        $this->travel(2)->days();
        $sessionHandler->write('session-that-must-be-invalidated', serialize(['user_id' => $restoredUser->id]));

        if ($sessionDriver === 'file') {
            touch(config('session.files').'/session-that-must-be-invalidated', now()->timestamp);
        }
        $this->travelBack();
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
})->with([['database', 'database'], ['file', 'file']]);

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

        $this->actingAs($superadmin)->withSession([
            'auth.password_confirmed_at' => now()->timestamp,
            'sqlite_backup_restore_authorization' => [
                'issued_at' => now()->timestamp,
                'nonce' => Str::random(64),
                'reason' => 'HTTP disaster recovery verification',
                'user_id' => $superadmin->id,
            ],
        ]);
        $snapshot = FileOperationPage::open($this, route('superadmin.backups.sqlite.restore'), 'superadmin.backups.restore-sqlite');
        $uploaded = FileOperationPage::upload($this, $snapshot, new UploadedFile($sourceBackup, 'verified.sqlite', 'application/vnd.sqlite3', test: true));
        $preview = FileOperationPage::call($this, $uploaded, 'preview')->assertOk();
        $state = json_decode($preview->json('components.0.snapshot'), true, flags: JSON_THROW_ON_ERROR);
        expect($state['memo']['errors'])->toBe([]);
        $response = $this->withCookie(config('session.cookie'), session()->getId())->post(route('superadmin.backups.sqlite.restore.store'), ['grant' => $state['data']['grant']]);

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', __('ui.superadmin.backup_restore.completed'));

        DB::purge($sandbox['connection']);

        $audit = AuditLog::on($sandbox['connection'])
            ->where('action', 'backup_restored')
            ->firstOrFail();
        $safetyBackupPath = app(FilesystemManager::class)
            ->disk('local')
            ->path('backups/sqlite/'.($audit->new_values['safety_snapshot'] ?? ''));

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
    $backupDirectory = app(FilesystemManager::class)
        ->disk('local')
        ->path('backups/sqlite');
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
    $connection = implode('_', [
        'sqlite_restore',
        str_replace('-', '_', $suffix),
        (string) getmypid(),
        Str::lower(Str::random(8)),
    ]);
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
    $candidateDirectory = app(FilesystemManager::class)
        ->disk('local')
        ->path('backups/sqlite/restore-candidates');

    return collect(File::glob($candidateDirectory.'/*'))
        ->sort()
        ->values()
        ->all();
}

test('a failed restore and failed rollback keep maintenance active', function (): void {
    $sandbox = sqliteRestoreSandbox('double-failure');
    $originalDefault = config('database.default');
    $backupDirectory = app(FilesystemManager::class)
        ->disk('local')
        ->path('backups/sqlite');
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
            ->andReturnUsing(function () use ($backupDirectory, $sourceBackup): never {
                foreach (File::glob($backupDirectory.'/*.sqlite') as $path) {
                    if ($path !== $sourceBackup) {
                        File::delete($path);
                    }
                }
                throw new RuntimeException('Simulated post-replacement failure.');
            });
        app()->instance(RecordAuditLogAction::class, $auditLog);

        $actor = User::factory()->make([
            'id' => 999_999,
            'email' => 'restore-operator@example.test',
        ]);

        expect(fn () => app(RestoreSqliteBackupAction::class)->handle(
            uploadedPath: $sourceBackup,
            actor: $actor,
            reason: 'Testing automatic rollback',
        ))->toThrow(RuntimeException::class, 'SQLite restoration failed and the automatic rollback could not be completed.');

        DB::purge($sandbox['connection']);

        expect(app(MaintenanceMode::class)->active())->toBeTrue()
            ->and(app(SqliteRestoreRequestLock::class)->requiresRecovery())->toBeTrue();
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

test('Livewire previews a bound restore candidate without replacing the database', function (): void {
    $sandbox = sqliteRestoreSandbox('livewire-preview');
    $originalDefault = config('database.default');
    $listeners = [];
    foreach (['mount', 'hydrate', 'call'] as $event) {
        $listeners[] = app(EventBus::class)->before($event, static function (): void {
            request()->setLaravelSession(session()->driver());
        });
    }
    try {
        Artisan::call('migrate', ['--database' => $sandbox['connection'], '--force' => true]);
        config()->set('database.default', $sandbox['connection']);
        app(SystemPermissionsSeeder::class)->run();
        $superadmin = User::factory()->connection($sandbox['connection'])->create(['name' => 'Before restore preview']);
        $role = Role::on($sandbox['connection'])->where('code', SystemRole::Superadmin->value)->firstOrFail();
        $superadmin->roles()->syncWithoutDetachingOrFail([$role->id]);
        $sourceBackup = app(CreateConsistentSqliteBackupAction::class)->handle();
        User::on($sandbox['connection'])->whereKey($superadmin->id)->update(['name' => 'Keep until explicit finalization']);
        $this->actingAs($superadmin)->withSession([
            'auth.password_confirmed_at' => now()->timestamp,
            'sqlite_backup_restore_authorization' => ['issued_at' => now()->timestamp, 'nonce' => Str::random(64), 'reason' => 'Verify restore candidate', 'user_id' => $superadmin->id],
        ]);
        $component = Livewire::test(RestoreSqlite::class)
            ->set('upload.backup', UploadedFile::fake()->createWithContent('verified.sqlite', File::get($sourceBackup)))
            ->call('preview')->assertHasNoErrors();
        expect($component->get('grant'))->toMatch('/^[A-Za-z0-9]{64}$/D')
            ->and(User::on($sandbox['connection'])->findOrFail($superadmin->id)->name)->toBe('Keep until explicit finalization');
        $candidate = session('sqlite_restore_candidate');
        expect($candidate['sha256'])->toBe(hash_file('sha256', $candidate['path']));
        $component->call('_startUpload', 'upload.backup', [['name' => 'replacement.sqlite', 'size' => 2048, 'type' => 'application/vnd.sqlite3']], false)->assertSet('grant', '')->assertSet('candidate', null);
        expect(is_file($candidate['path']))->toBeFalse()
            ->and(session('sqlite_restore_candidate'))->toBeNull();
        $component->set('upload.backup', null)->assertSet('grant', '');
    } finally {
        foreach ($listeners as $stop) {
            $stop();
        }
        config()->set('database.default', $originalDefault);
        DB::purge($sandbox['connection']);
        config()->set("database.connections.{$sandbox['connection']}", null);
        File::deleteDirectory($sandbox['directory']);
    }
});
