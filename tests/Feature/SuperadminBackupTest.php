<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Livewire\Superadmin\Dashboard;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\Backups\SqliteRestoreAuthorization;
use App\Support\Backups\PreparedRestoreStore;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\FileOperationPage;

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
        $this->actingAs($superadmin)->withSession(['auth.password_confirmed_at' => now()->timestamp]);
        $snapshot = FileOperationPage::open($this, route('superadmin.dashboard'), 'superadmin.dashboard');
        $prepared = FileOperationPage::call($this, $snapshot, 'downloadBackup', ['sqliteBackup.reason' => 'Encrypted off-site recovery copy', 'sqliteBackup.confirmation' => 'BACKUP'])->assertOk();
        $response = $this->get($prepared->json('components.0.effects.redirect'))
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

    Storage::fake('local');
    $source = Storage::disk('local')->path('confirmation.sqlite');
    (new SQLite3($source))->close();
    config()->set('database.connections.sqlite.database', $source);
    $this->actingAs($superadmin)->withSession(['auth.password_confirmed_at' => now()->timestamp]);
    $snapshot = FileOperationPage::open($this, route('superadmin.dashboard'), 'superadmin.dashboard');
    $missing = FileOperationPage::call($this, $snapshot, 'downloadBackup', ['restoreBackup.reason' => 'Unfinished restore explanation', 'sqliteBackup.confirmation' => 'BACKUP'])->assertOk();
    $state = json_decode($missing->json('components.0.snapshot'), true);
    expect($state['memo']['errors'])->toHaveKey('sqliteBackup.reason');
    expect($state['data']['sqliteBackup'][0]['confirmation'])->toBe('BACKUP');
    $prepared = FileOperationPage::call($this, $missing->json('components.0.snapshot'), 'downloadBackup', ['sqliteBackup.reason' => 'Encrypted disaster recovery copy'])->assertOk();
    $state = json_decode($prepared->json('components.0.snapshot'), true);
    expect($state['memo']['errors'])->toBe([])
        ->and($state['data']['restoreBackup'][0]['reason'])->toBe('Unfinished restore explanation')
        ->and(collect($prepared->json('components.0.effects.dispatches'))->firstWhere('name', 'modal-close')['params']['name'])->toBe('sqlite-backup-download');
    $grant = array_key_last(session('prepared_downloads'));
    expect($prepared->json('components.0.effects.redirect'))->toBe(route('restaurant.files.download', ['grant' => $grant]))
        ->and(session('prepared_downloads.'.$grant.'.user_id'))->toBe($superadmin->id)
        ->and(AuditLog::query()->where('action', 'backup_downloaded')->firstOrFail()->new_values['reason'])->toBe('Encrypted disaster recovery copy');
});

test('backup download requires recent password confirmation and a one-time authorized reason', function (): void {
    $superadmin = createSuperadminForBackupTest();

    $this->actingAs($superadmin)
        ->get(route('superadmin.backups.sqlite.download'))
        ->assertRedirect(route('password.confirm'));

    $this->actingAs($superadmin)->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->get(route('superadmin.backups.sqlite.download'))->assertRedirect(route('superadmin.dashboard'));
    $this->get(route('restaurant.files.download', ['grant' => Str::random(64)]))->assertForbidden();
});

test('backup restore authorization requires typed confirmation and an audited reason', function (): void {
    $superadmin = createSuperadminForBackupTest();

    Livewire::actingAs($superadmin)
        ->test(Dashboard::class)
        ->set('restoreBackup.confirmation', 'RESTORE')
        ->call('prepareBackupRestore')
        ->assertHasErrors(['restoreBackup.reason'])
        ->set('restoreBackup.reason', 'Recovering the restaurant after verified data loss')
        ->call('prepareBackupRestore')
        ->assertHasNoErrors()
        ->assertDispatched('modal-close', name: 'sqlite-backup-restore')
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

});

test('restore finalization rejects raw uploads and preserves the unconsumed authorization', function (): void {
    $superadmin = createSuperadminForBackupTest();
    $authorization = ['issued_at' => now()->timestamp, 'nonce' => Str::random(64), 'reason' => 'Validated restore only', 'user_id' => $superadmin->id];
    $this->actingAs($superadmin)->withSession(['auth.password_confirmed_at' => now()->timestamp, 'sqlite_backup_restore_authorization' => $authorization])
        ->post(route('superadmin.backups.sqlite.restore.store'), ['backup' => UploadedFile::fake()->createWithContent('backup.sqlite', 'not a sqlite database')])
        ->assertSessionHasErrors(['backup', 'grant']);
    expect(session('sqlite_backup_restore_authorization'))->toMatchArray($authorization);
});

test('a consumed backup restore authorization nonce cannot be replayed', function (): void {
    $superadmin = createSuperadminForBackupTest();
    Storage::fake('local');
    Storage::disk('local')->put('backups/sqlite/restore-candidates/replay.sqlite', 'server-bound candidate');
    $authorization = ['issued_at' => now()->timestamp, 'nonce' => Str::random(64), 'reason' => 'Replay safety verification', 'user_id' => $superadmin->id];
    $this->actingAs($superadmin)->withSession(['auth.password_confirmed_at' => now()->timestamp, 'sqlite_backup_restore_authorization' => $authorization]);
    $request = Request::create('/restore');
    $request->setLaravelSession(session()->driver());
    $request->setUserResolver(fn () => $superadmin);
    $candidate = app(PreparedRestoreStore::class)->issue($request, ['path' => Storage::disk('local')->path('backups/sqlite/restore-candidates/replay.sqlite'), 'schema_fingerprint' => 'verified in preview']);
    app(SqliteRestoreAuthorization::class)->handle($request, consume: true);
    session()->put('sqlite_backup_restore_authorization', $authorization);
    session()->save();
    $this->withCookie(config('session.cookie'), session()->getId())
        ->post(route('superadmin.backups.sqlite.restore.store'), ['grant' => $candidate['grant']])->assertConflict();
    expect(File::get($candidate['path']))->toBe('server-bound candidate');
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

test('failed SQLite preparation keeps a localized error and cannot create an audit record', function (): void {
    $superadmin = createSuperadminForBackupTest();
    config()->set('database.connections.sqlite.database', storage_path('framework/testing/missing-source.sqlite'));
    $this->actingAs($superadmin)->withSession(['auth.password_confirmed_at' => now()->timestamp]);
    $snapshot = FileOperationPage::open($this, route('superadmin.dashboard'), 'superadmin.dashboard');
    $response = FileOperationPage::call($this, $snapshot, 'downloadBackup', ['sqliteBackup.reason' => 'Missing source recovery snapshot', 'sqliteBackup.confirmation' => 'BACKUP'])->assertOk();
    $state = json_decode($response->json('components.0.snapshot'), true);
    expect($state['memo']['errors'])->toBe(['sqliteBackup.reason' => [__('ui.files.prepare_failed')]])
        ->and(AuditLog::query()->where('action', 'backup_downloaded')->count())->toBe(0)
        ->and(session('prepared_downloads'))->toBeNull();
});

test('restore finalization rejects a changed file or authorization context before replacement', function (string $change, int $status): void {
    $superadmin = createSuperadminForBackupTest();
    Storage::fake('local');
    $relative = 'backups/sqlite/restore-candidates/verified.sqlite';
    Storage::disk('local')->put($relative, 'server-bound candidate');
    $authorization = ['issued_at' => now()->timestamp, 'nonce' => Str::random(64), 'reason' => 'Bound restore finalization', 'user_id' => $superadmin->id];
    $this->actingAs($superadmin)->withSession(['auth.password_confirmed_at' => now()->timestamp, 'sqlite_backup_restore_authorization' => $authorization]);
    $request = Request::create('/restore');
    $request->setLaravelSession(session()->driver());
    $request->setUserResolver(fn () => $superadmin);
    $candidate = app(PreparedRestoreStore::class)->issue($request, ['path' => Storage::disk('local')->path($relative), 'schema_fingerprint' => 'verified in preview']);
    match ($change) {
        'content' => Storage::disk('local')->put($relative, 'replaced after preview'),
        'intent' => session()->put('sqlite_backup_restore_authorization.nonce', Str::random(64)),
        'session' => session()->regenerate(),
        'expired' => session()->put('sqlite_backup_restore_authorization.issued_at', now()->subMinutes(6)->timestamp),
    };
    session()->save();
    $this->withCookie(config('session.cookie'), session()->getId())
        ->post(route('superadmin.backups.sqlite.restore.store'), ['grant' => $candidate['grant']])->assertStatus($status);
    expect(User::query()->findOrFail($superadmin->id)->email)->toBe($superadmin->email)
        ->and(session('sqlite_restore_candidate.grant'))->toBe($candidate['grant'])
        ->and(AuditLog::query()->where('action', 'backup_restored')->count())->toBe(0)
        ->and(Storage::disk('local')->exists($relative))->toBeTrue();
})->with(['changed bytes' => ['content', 409], 'another intent' => ['intent', 403], 'another session' => ['session', 403], 'expired intent' => ['expired', 403]]);
