<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Livewire\Superadmin\Dashboard;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('ordinary users cannot see or download local sqlite backups', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('superadmin.dashboard'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('superadmin.backups.sqlite.download'))
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
        ->assertSee('Media ZIP later');
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
