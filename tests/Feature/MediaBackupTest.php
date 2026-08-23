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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    Storage::fake('public');
    Storage::fake('local');
});

test('only a recently confirmed superadmin with one-time authorization can download the media ZIP', function (): void {
    $ordinaryUser = User::factory()->create();
    $superadmin = createSuperadminForMediaBackupTest();
    $route = route('superadmin.backups.media.download');

    $this->get($route)->assertRedirect(route('login'));
    $this->actingAs($ordinaryUser)->get($route)->assertForbidden();
    $this->actingAs($superadmin)->get($route)->assertRedirect(route('password.confirm'));
    $this->actingAs($superadmin)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->get($route)
        ->assertForbidden();

    $this->actingAs($superadmin)
        ->withSession([
            'auth.password_confirmed_at' => now()->timestamp,
            'media_backup_download_authorization' => [
                'issued_at' => now()->subMinutes(6)->timestamp,
                'nonce' => Str::random(64),
                'reason' => 'Expired media backup authorization',
                'user_id' => $superadmin->id,
            ],
        ])
        ->get($route)
        ->assertForbidden();
});

test('media ZIP confirmation requires an audited reason and exact typed confirmation', function (): void {
    $superadmin = createSuperadminForMediaBackupTest();

    Livewire::actingAs($superadmin)
        ->test(Dashboard::class)
        ->set('mediaBackupDownloadConfirmation', 'MEDIA')
        ->call('downloadMediaBackup')
        ->assertHasErrors(['mediaBackupDownloadReason'])
        ->set('mediaBackupDownloadReason', 'Encrypted off-site media recovery copy')
        ->set('mediaBackupDownloadConfirmation', 'media')
        ->call('downloadMediaBackup')
        ->assertHasErrors(['mediaBackupDownloadConfirmation'])
        ->set('mediaBackupDownloadConfirmation', 'MEDIA')
        ->call('downloadMediaBackup')
        ->assertHasNoErrors()
        ->assertRedirect(route('superadmin.backups.media.download'));

    expect(session('media_backup_download_authorization'))
        ->toMatchArray([
            'reason' => 'Encrypted off-site media recovery copy',
            'user_id' => $superadmin->id,
        ]);

    expect(session('media_backup_download_authorization.nonce'))
        ->toBeString()
        ->toHaveLength(64);
});

test('superadmin downloads all stored photographs with integrity manifest and temporary archive cleanup', function (): void {
    $firstImage = tinyPngForMediaBackupTest();
    $secondImage = $firstImage.'second-image';

    Storage::disk('public')->put('media/organizations/1/logos/first.png', $firstImage);
    Storage::disk('public')->put('media/organizations/1/brands/2/menu-items/3/images/dish.png', $secondImage);
    Storage::disk('public')->put('media/organizations/1/notes.txt', 'not a photograph');

    $outsidePath = storage_path('framework/testing/media-backup-outside.png');
    File::ensureDirectoryExists(dirname($outsidePath));
    File::put($outsidePath, $firstImage);
    File::link($outsidePath, Storage::disk('public')->path('media/linked-outside.png'));

    $superadmin = createSuperadminForMediaBackupTest();
    Date::setTestNow(CarbonImmutable::parse('2026-08-23 17:18:19'));

    try {
        $authorization = [
            'issued_at' => now()->timestamp,
            'nonce' => Str::random(64),
            'reason' => 'Encrypted off-site media recovery copy',
            'user_id' => $superadmin->id,
        ];

        $response = $this->actingAs($superadmin)
            ->withSession([
                'auth.password_confirmed_at' => now()->timestamp,
                'media_backup_download_authorization' => $authorization,
            ])
            ->get(route('superadmin.backups.media.download'))
            ->assertOk()
            ->assertDownload('restaurant-menu-media-backup-2026-08-23-171819.zip')
            ->assertHeader('content-type', 'application/zip')
            ->assertHeader('x-content-type-options', 'nosniff');

        $archivePath = $response->baseResponse->getFile()->getPathname();
        $archive = new ZipArchive;
        $cacheControl = (string) $response->headers->get('cache-control');

        expect($cacheControl)
            ->toContain('no-store', 'private')
            ->not->toContain('public')
            ->and(Str::startsWith($archivePath, Storage::disk('local')->path('backups/media/')))->toBeTrue()
            ->and($archive->open($archivePath))->toBeTrue();

        $entries = collect(range(0, $archive->numFiles - 1))
            ->map(fn (int $index): string|false => $archive->getNameIndex($index))
            ->filter(fn (string|false $entry): bool => is_string($entry))
            ->values()
            ->all();
        $manifest = json_decode(
            (string) $archive->getFromName('manifest.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $archive->close();

        expect($entries)
            ->toContain(
                'manifest.json',
                'media/organizations/1/logos/first.png',
                'media/organizations/1/brands/2/menu-items/3/images/dish.png',
            )
            ->not->toContain(
                'media/organizations/1/notes.txt',
                'media/linked-outside.png',
            )
            ->and($manifest['file_count'])->toBe(2)
            ->and($manifest['total_bytes'])->toBe(strlen($firstImage) + strlen($secondImage))
            ->and($manifest['files'])->toContain([
                'path' => 'media/organizations/1/logos/first.png',
                'sha256' => hash('sha256', $firstImage),
                'size' => strlen($firstImage),
            ])
            ->and(Storage::disk('public')->exists('media/organizations/1/logos/first.png'))->toBeTrue()
            ->and(Storage::disk('public')->exists('media/organizations/1/brands/2/menu-items/3/images/dish.png'))->toBeTrue();

        ob_start();
        $response->baseResponse->sendContent();
        ob_end_clean();

        expect(File::exists($archivePath))->toBeFalse()
            ->and(session('media_backup_download_authorization'))->toBeNull();

        $this->actingAs($superadmin)
            ->withSession([
                'auth.password_confirmed_at' => now()->timestamp,
                'media_backup_download_authorization' => $authorization,
            ])
            ->get(route('superadmin.backups.media.download'))
            ->assertConflict();

        $auditLog = AuditLog::query()
            ->where('action', 'media_backup_downloaded')
            ->firstOrFail();

        expect($auditLog->new_values)
            ->toMatchArray([
                'reason' => 'Encrypted off-site media recovery copy',
                'file_count' => 2,
                'total_bytes' => strlen($firstImage) + strlen($secondImage),
            ]);
    } finally {
        Date::setTestNow();
        File::delete($outsidePath);
    }
});

test('empty media storage still produces a valid manifest archive', function (): void {
    $superadmin = createSuperadminForMediaBackupTest();

    $response = $this->actingAs($superadmin)
        ->withSession([
            'auth.password_confirmed_at' => now()->timestamp,
            'media_backup_download_authorization' => [
                'issued_at' => now()->timestamp,
                'nonce' => Str::random(64),
                'reason' => 'Empty media archive verification',
                'user_id' => $superadmin->id,
            ],
        ])
        ->get(route('superadmin.backups.media.download'))
        ->assertOk();

    $archive = new ZipArchive;
    expect($archive->open($response->baseResponse->getFile()->getPathname()))->toBeTrue();

    $manifest = json_decode(
        (string) $archive->getFromName('manifest.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $archive->close();

    expect($manifest['file_count'])->toBe(0)
        ->and($manifest['total_bytes'])->toBe(0)
        ->and($manifest['files'])->toBe([]);

    ob_start();
    $response->baseResponse->sendContent();
    ob_end_clean();
});

test('superadmin dashboard exposes the protected media ZIP backup control', function (): void {
    $superadmin = createSuperadminForMediaBackupTest();

    $this->actingAs($superadmin)
        ->get(route('superadmin.dashboard'))
        ->assertOk()
        ->assertSee(__('ui.superadmin.dashboard.download_media_zip'))
        ->assertDontSee(__('ui.superadmin.dashboard.media_zip_later'));
});

function createSuperadminForMediaBackupTest(): User
{
    $user = User::factory()->create([
        'name' => 'Media Backup Superadmin',
        'email' => 'media-backup-superadmin@example.test',
    ]);
    $role = Role::query()
        ->where('code', SystemRole::Superadmin->value)
        ->firstOrFail();

    $user->roles()->syncWithoutDetachingOrFail([$role->id]);

    return $user;
}

function tinyPngForMediaBackupTest(): string
{
    return (string) base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        true,
    );
}
