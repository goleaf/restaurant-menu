<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Livewire\Superadmin\Dashboard;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('ordinary users cannot see download or restore local sqlite backups', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('superadmin.dashboard'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('superadmin.backups.sqlite.download'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('superadmin.backups.sqlite.restore'))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('superadmin.backups.sqlite.restore.store'), [
            'backup' => UploadedFile::fake()->createWithContent('backup.sqlite', "SQLite format 3\0"),
        ])
        ->assertForbidden();
});

test('superadmin can see the local backup warning on the platform dashboard', function () {
    $superadmin = createSuperadminForBackupTest();

    $this->actingAs($superadmin)
        ->get(route('superadmin.dashboard'))
        ->assertOk()
        ->assertSee('Local backups')
        ->assertSee('SQLite backup contains sensitive data')
        ->assertSee('Download SQLite')
        ->assertSee('Restore SQLite')
        ->assertSee('Download media ZIP');
});

test('superadmin can download the configured sqlite database file', function () {
    $sqlitePath = storage_path('framework/testing/local-sqlite-backup-test.sqlite');

    File::ensureDirectoryExists(dirname($sqlitePath));
    (new SQLite3($sqlitePath))->close();

    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', $sqlitePath);
    Date::setTestNow(CarbonImmutable::parse('2026-06-04 12:34:56'));

    $superadmin = createSuperadminForBackupTest();

    try {
        $response = $this->actingAs($superadmin)
            ->withSession([
                'auth.password_confirmed_at' => now()->timestamp,
                'sqlite_backup_download_authorization' => [
                    'issued_at' => now()->timestamp,
                    'reason' => 'Encrypted off-site recovery copy',
                    'user_id' => $superadmin->id,
                ],
            ])
            ->get(route('superadmin.backups.sqlite.download'))
            ->assertOk()
            ->assertDownload('restaurant-menu-sqlite-backup-2026-06-04-123456.sqlite')
            ->assertHeader('content-type', 'application/vnd.sqlite3');

        $backupPath = $response->baseResponse->getFile()->getPathname();

        expect($backupPath)->not->toBe($sqlitePath)
            ->and(File::get($backupPath))->toStartWith('SQLite format 3');

        ob_start();
        $response->baseResponse->sendContent();
        ob_end_clean();

        expect(File::exists($backupPath))->toBeFalse();

        expect(AuditLog::query()->where('action', 'backup_downloaded')->value('new_values'))
            ->toContain('Encrypted off-site recovery copy');
    } finally {
        Date::setTestNow();
        File::delete($sqlitePath);
    }
});

test('backup authorization requires typed confirmation and an audited reason', function (): void {
    $superadmin = createSuperadminForBackupTest();

    Livewire::actingAs($superadmin)
        ->test(Dashboard::class)
        ->set('backupDownloadConfirmation', 'BACKUP')
        ->call('downloadBackup')
        ->assertHasErrors(['backupDownloadReason'])
        ->set('backupDownloadReason', 'Encrypted disaster recovery copy')
        ->call('downloadBackup')
        ->assertHasNoErrors()
        ->assertRedirect(route('superadmin.backups.sqlite.download'));

    expect(session('sqlite_backup_download_authorization'))
        ->toMatchArray([
            'reason' => 'Encrypted disaster recovery copy',
            'user_id' => $superadmin->id,
        ]);
});

test('backup download requires recent password confirmation and a one-time authorized reason', function (): void {
    $superadmin = createSuperadminForBackupTest();

    $this->actingAs($superadmin)
        ->get(route('superadmin.backups.sqlite.download'))
        ->assertRedirect(route('password.confirm'));

    $this->actingAs($superadmin)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->get(route('superadmin.backups.sqlite.download'))
        ->assertForbidden();
});

test('backup restore authorization requires typed confirmation and an audited reason', function (): void {
    $superadmin = createSuperadminForBackupTest();

    Livewire::actingAs($superadmin)
        ->test(Dashboard::class)
        ->set('backupRestoreConfirmation', 'RESTORE')
        ->call('prepareBackupRestore')
        ->assertHasErrors(['backupRestoreReason'])
        ->set('backupRestoreReason', 'Recovering the restaurant after verified data loss')
        ->call('prepareBackupRestore')
        ->assertHasNoErrors()
        ->assertRedirect(route('superadmin.backups.sqlite.restore'));

    expect(session('sqlite_backup_restore_authorization'))
        ->toMatchArray([
            'reason' => 'Recovering the restaurant after verified data loss',
            'user_id' => $superadmin->id,
        ]);

    expect(session('sqlite_backup_restore_authorization.nonce'))
        ->toBeString()
        ->toHaveLength(64);
});

test('backup restore upload requires recent password confirmation and one-time authorization', function (): void {
    $superadmin = createSuperadminForBackupTest();

    $this->actingAs($superadmin)
        ->get(route('superadmin.backups.sqlite.restore'))
        ->assertRedirect(route('password.confirm'));

    $this->actingAs($superadmin)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->get(route('superadmin.backups.sqlite.restore'))
        ->assertForbidden();

    $authorization = [
        'issued_at' => now()->timestamp,
        'nonce' => Str::random(64),
        'reason' => 'Recovering the restaurant after verified data loss',
        'user_id' => $superadmin->id,
    ];

    $this->actingAs($superadmin)
        ->withSession([
            'auth.password_confirmed_at' => now()->timestamp,
            'sqlite_backup_restore_authorization' => $authorization,
        ])
        ->get(route('superadmin.backups.sqlite.restore'))
        ->assertOk()
        ->assertSee('Restore SQLite backup')
        ->assertSee('Choose a verified SQLite backup');

    $this->actingAs($superadmin)
        ->withSession([
            'auth.password_confirmed_at' => now()->timestamp,
            'sqlite_backup_restore_authorization' => $authorization,
        ])
        ->post(route('superadmin.backups.sqlite.restore.store'), [
            'backup' => UploadedFile::fake()->createWithContent('backup.sqlite', 'not a sqlite database'),
        ])
        ->assertSessionHasErrors(['backup']);

    expect(session('sqlite_backup_restore_authorization'))->toMatchArray($authorization);
});

test('a consumed backup restore authorization nonce cannot be replayed', function (): void {
    $superadmin = createSuperadminForBackupTest();
    $connection = 'sqlite_restore_replay_fixture';
    $sqlitePath = storage_path('framework/testing/replayed-restore.sqlite');
    File::ensureDirectoryExists(dirname($sqlitePath));
    File::put($sqlitePath, '');
    config()->set("database.connections.{$connection}", [
        'driver' => 'sqlite',
        'database' => $sqlitePath,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    Schema::connection($connection)->create('restore_fixture', function (Blueprint $table): void {
        $table->id();
    });

    $authorization = [
        'issued_at' => now()->timestamp,
        'nonce' => Str::random(64),
        'reason' => 'Replay safety verification',
        'user_id' => $superadmin->id,
    ];
    $session = [
        'auth.password_confirmed_at' => now()->timestamp,
        'sqlite_backup_restore_authorization' => $authorization,
    ];

    try {
        $this->actingAs($superadmin)
            ->withSession($session)
            ->post(route('superadmin.backups.sqlite.restore.store'), [
                'backup' => new UploadedFile(
                    path: $sqlitePath,
                    originalName: 'first-restore.sqlite',
                    mimeType: 'application/vnd.sqlite3',
                    test: true,
                ),
            ])
            ->assertRedirect(route('superadmin.dashboard'));

        $this->actingAs($superadmin)
            ->withSession($session)
            ->post(route('superadmin.backups.sqlite.restore.store'), [
                'backup' => new UploadedFile(
                    path: $sqlitePath,
                    originalName: 'replayed-restore.sqlite',
                    mimeType: 'application/vnd.sqlite3',
                    test: true,
                ),
            ])
            ->assertConflict();
    } finally {
        DB::purge($connection);
        config()->set("database.connections.{$connection}", null);
        File::delete($sqlitePath);
    }
});

function createSuperadminForBackupTest(): User
{
    $user = User::factory()->create([
        'name' => 'Backup Superadmin',
        'email' => 'backup-superadmin@example.test',
    ]);

    $role = Role::query()
        ->where('code', SystemRole::Superadmin->value)
        ->firstOrFail();

    $user->roles()->syncWithoutDetachingOrFail([$role->id]);

    return $user;
}
