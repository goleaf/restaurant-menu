<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Storage::fake('tmp-for-tests');
    $this->administrator = User::factory()->create();
    $this->administrator->roles()->attach(Role::factory()->create(['code' => SystemRole::Superadmin]));
    $this->restoreAuthorization = ['user_id' => $this->administrator->id, 'issued_at' => now()->timestamp, 'nonce' => Str::random(64), 'reason' => 'Restore isolated test database'];
});

test('authorized restore upload retains the existing 256 MiB limit without changing other uploads', function (): void {
    $rules = config('livewire.temporary_file_upload.rules');
    $this->actingAs($this->administrator)->withSession([
        'auth.password_confirmed_at' => now()->timestamp,
        'sqlite_backup_restore_authorization' => $this->restoreAuthorization,
    ])->postJson(URL::temporarySignedRoute('livewire.upload-file', now()->addMinutes(5), ['restore_attempt' => hash('sha256', $this->restoreAuthorization['nonce'])]), [
        'files' => [UploadedFile::fake()->create('backup.sqlite', 13_000)],
    ])->assertOk()->assertJsonCount(1, 'paths')
        ->assertSessionHas('sqlite_backup_restore_authorization', $this->restoreAuthorization);

    expect(config('livewire.temporary_file_upload.rules'))->toBe($rules);
});

test('ordinary uploads keep the default limit even after visiting restore', function (bool $expired): void {
    if ($expired) {
        $this->restoreAuthorization['issued_at'] = now()->subMinutes(6)->timestamp;
    }
    $this->actingAs($this->administrator)->withSession(['sqlite_backup_restore_authorization' => $this->restoreAuthorization])
        ->postJson(URL::temporarySignedRoute('livewire.upload-file', now()->addMinutes(5)), [
            'files' => [UploadedFile::fake()->create('backup.sqlite', 13_000)],
        ])->assertUnprocessable()->assertJsonValidationErrors('files.0');

    $this->postJson(URL::temporarySignedRoute('livewire.upload-file', now()->addMinutes(5)), [
        'files' => [UploadedFile::fake()->create('photo.jpg', 1)],
    ])->assertOk();
})->with([true, false]);

test('restore upload is bound to its signed current authorization nonce', function (): void {
    $url = URL::temporarySignedRoute('livewire.upload-file', now()->addMinutes(5), ['restore_attempt' => hash('sha256', $this->restoreAuthorization['nonce'])]);
    $this->restoreAuthorization['nonce'] = Str::random(64);
    $this->actingAs($this->administrator)->withSession([
        'auth.password_confirmed_at' => now()->timestamp,
        'sqlite_backup_restore_authorization' => $this->restoreAuthorization,
    ])->postJson($url, ['files' => [UploadedFile::fake()->create('backup.sqlite', 1)]])->assertForbidden();

    expect(Storage::disk('tmp-for-tests')->allFiles())->toBeEmpty();
});

test('restore upload rejects stale authorization or changed actor before storage', function (bool $stale): void {
    if ($stale) {
        $this->restoreAuthorization['issued_at'] = now()->subMinutes(6)->timestamp;
    } else {
        $this->restoreAuthorization['user_id'] = User::factory()->create()->id;
    }

    $this->actingAs($this->administrator)->withSession([
        'auth.password_confirmed_at' => now()->timestamp,
        'sqlite_backup_restore_authorization' => $this->restoreAuthorization,
    ])->postJson(URL::temporarySignedRoute('livewire.upload-file', now()->addMinutes(5), ['restore_attempt' => hash('sha256', $this->restoreAuthorization['nonce'])]), [
        'files' => [UploadedFile::fake()->create('backup.sqlite', 13_000)],
    ])->assertForbidden();

    expect(Storage::disk('tmp-for-tests')->allFiles())->toBeEmpty();
})->with([true, false]);

test('restore upload requires fresh password confirmation and a valid signed URL', function (): void {
    $this->actingAs($this->administrator)->withSession([
        'auth.password_confirmed_at' => now()->subHours(4)->timestamp,
        'sqlite_backup_restore_authorization' => $this->restoreAuthorization,
    ])->postJson(URL::temporarySignedRoute('livewire.upload-file', now()->addMinutes(5), ['restore_attempt' => hash('sha256', $this->restoreAuthorization['nonce'])]), [
        'files' => [UploadedFile::fake()->create('backup.sqlite', 13_000)],
    ])->assertStatus(423);

    $this->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->postJson(route('livewire.upload-file'), ['files' => [UploadedFile::fake()->create('backup.sqlite', 13_000)]])
        ->assertUnauthorized();

    expect(Storage::disk('tmp-for-tests')->allFiles())->toBeEmpty();
});

test('restore upload rejects files larger than the existing maximum and restores configuration on failure', function (): void {
    $rules = config('livewire.temporary_file_upload.rules');
    $this->actingAs($this->administrator)->withSession([
        'auth.password_confirmed_at' => now()->timestamp,
        'sqlite_backup_restore_authorization' => $this->restoreAuthorization,
    ])->postJson(URL::temporarySignedRoute('livewire.upload-file', now()->addMinutes(5), ['restore_attempt' => hash('sha256', $this->restoreAuthorization['nonce'])]), [
        'files' => [UploadedFile::fake()->create('backup.sqlite', 262_145)],
    ])->assertUnprocessable()->assertJsonValidationErrors('files.0');

    expect(config('livewire.temporary_file_upload.rules'))->toBe($rules);
});
