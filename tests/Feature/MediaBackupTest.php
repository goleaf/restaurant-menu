<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\FileOperationPage;

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
    $this->actingAs($superadmin)->withSession(['auth.password_confirmed_at' => now()->timestamp]);
    $snapshot = FileOperationPage::open($this, route('superadmin.dashboard'), 'superadmin.dashboard');
    $prepared = FileOperationPage::call($this, $snapshot, 'downloadMediaBackup', ['mediaBackup.reason' => 'Expired media backup authorization', 'mediaBackup.confirmation' => 'MEDIA'])->assertOk();
    $url = $prepared->json('components.0.effects.redirect');
    session()->forget('auth.password_confirmed_at');
    session()->save();
    $this->get($url)->assertRedirect(route('password.confirm'));
    $this->withSession(['auth.password_confirmed_at' => now()->timestamp]);
    $this->travel(6)->minutes();
    $this->get($url)->assertForbidden();
});

test('media ZIP confirmation requires an audited reason and exact typed confirmation', function (): void {
    $superadmin = createSuperadminForMediaBackupTest();

    $this->actingAs($superadmin)->withSession(['auth.password_confirmed_at' => now()->timestamp]);
    $snapshot = FileOperationPage::open($this, route('superadmin.dashboard'), 'superadmin.dashboard');
    $missing = FileOperationPage::call($this, $snapshot, 'downloadMediaBackup', ['mediaBackup.confirmation' => 'MEDIA'])->assertOk();
    expect(json_decode($missing->json('components.0.snapshot'), true)['memo']['errors'])->toHaveKey('mediaBackup.reason');
    $wrong = FileOperationPage::call($this, $missing->json('components.0.snapshot'), 'downloadMediaBackup', ['mediaBackup.reason' => 'Encrypted off-site media recovery copy', 'mediaBackup.confirmation' => 'media'])->assertOk();
    expect(json_decode($wrong->json('components.0.snapshot'), true)['memo']['errors'])->toHaveKey('mediaBackup.confirmation');
    $prepared = FileOperationPage::call($this, $wrong->json('components.0.snapshot'), 'downloadMediaBackup', ['mediaBackup.confirmation' => 'MEDIA'])->assertOk();
    expect(json_decode($prepared->json('components.0.snapshot'), true)['memo']['errors'])->toBe([]);
    expect(collect($prepared->json('components.0.effects.dispatches'))->firstWhere('name', 'modal-close')['params']['name'])->toBe('media-backup-download');
    $grant = array_key_last(session('prepared_downloads'));
    expect($grant)->toBeString()->toHaveLength(64)
        ->and($prepared->json('components.0.effects.redirect'))->toBe(route('restaurant.files.download', ['grant' => $grant]))
        ->and(session('prepared_downloads.'.$grant.'.user_id'))->toBe($superadmin->id)
        ->and(AuditLog::query()->where('action', 'media_backup_downloaded')->firstOrFail()->new_values['reason'])->toBe('Encrypted off-site media recovery copy');
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
        $this->actingAs($superadmin)->withSession(['auth.password_confirmed_at' => now()->timestamp]);
        $snapshot = FileOperationPage::open($this, route('superadmin.dashboard'), 'superadmin.dashboard');
        $prepared = FileOperationPage::call($this, $snapshot, 'downloadMediaBackup', ['mediaBackup.reason' => 'Encrypted off-site media recovery copy', 'mediaBackup.confirmation' => 'MEDIA'])->assertOk();
        $url = $prepared->json('components.0.effects.redirect');
        $grant = array_key_last(session('prepared_downloads'));
        $authorization = session('prepared_downloads.'.$grant);
        $response = $this->get($url)
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
            ->and(Str::startsWith($archivePath, Storage::disk('local')->path('prepared-downloads/')))->toBeTrue()
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
            ->and(session('prepared_downloads.'.$grant))->toBeNull();

        session()->put('prepared_downloads', [$grant => $authorization]);
        session()->save();
        $this->get($url)->assertConflict();

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

    $this->actingAs($superadmin)->withSession(['auth.password_confirmed_at' => now()->timestamp]);
    $snapshot = FileOperationPage::open($this, route('superadmin.dashboard'), 'superadmin.dashboard');
    $prepared = FileOperationPage::call($this, $snapshot, 'downloadMediaBackup', ['mediaBackup.reason' => 'Empty media archive verification', 'mediaBackup.confirmation' => 'MEDIA'])->assertOk();
    $response = $this->get($prepared->json('components.0.effects.redirect'))->assertOk();

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

test('retrying the same signed backup attempt never creates another archive or audit record', function (): void {
    $superadmin = createSuperadminForMediaBackupTest();
    $this->actingAs($superadmin)->withSession(['auth.password_confirmed_at' => now()->timestamp]);
    $snapshot = FileOperationPage::open($this, route('superadmin.dashboard'), 'superadmin.dashboard');
    $input = ['mediaBackup.reason' => 'Single recovery attempt', 'mediaBackup.confirmation' => 'MEDIA'];
    $first = FileOperationPage::call($this, $snapshot, 'downloadMediaBackup', $input)->assertOk();
    $second = FileOperationPage::call($this, $snapshot, 'downloadMediaBackup', $input)->assertOk();
    expect($second->json('components.0.effects.redirect'))->toBe($first->json('components.0.effects.redirect'))
        ->and(AuditLog::query()->where('action', 'media_backup_downloaded')->count())->toBe(1)
        ->and(Storage::disk('local')->files('prepared-downloads'))->toHaveCount(1);
    FileOperationPage::call($this, $snapshot, 'downloadMediaBackup', [...$input, 'mediaBackup.reason' => 'Changed input on old attempt'])->assertConflict();
    $this->get($first->json('components.0.effects.redirect'))->assertOk();
    FileOperationPage::call($this, $snapshot, 'downloadMediaBackup', $input)->assertConflict();
    expect(AuditLog::query()->where('action', 'media_backup_downloaded')->count())->toBe(1);
});
