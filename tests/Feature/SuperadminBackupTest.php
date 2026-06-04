<?php

use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\File;

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
    File::put($sqlitePath, 'SQLite backup test content');

    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', $sqlitePath);
    Date::setTestNow(CarbonImmutable::parse('2026-06-04 12:34:56'));

    $superadmin = createSuperadminForBackupTest();

    try {
        $this->actingAs($superadmin)
            ->get(route('superadmin.backups.sqlite.download'))
            ->assertOk()
            ->assertDownload('restaurant-menu-sqlite-backup-2026-06-04-123456.sqlite')
            ->assertHeader('content-type', 'application/vnd.sqlite3');
    } finally {
        Date::setTestNow();
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
