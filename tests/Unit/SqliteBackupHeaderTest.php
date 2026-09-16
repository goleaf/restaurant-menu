<?php

use App\Rules\Backups\SqliteBackupHeader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

uses(Tests\TestCase::class);

test('sqlite upload header rule reports an actual translated field failure', function (string $locale): void {
    app()->setLocale($locale);
    $file = UploadedFile::fake()->createWithContent('backup.sqlite', str_repeat('x', 1024));
    $validator = Validator::make(['backup' => $file], ['backup' => ['file', new SqliteBackupHeader]]);
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->toArray())->toBe(['backup' => [__('validation.sqlite_backup_invalid')]]);
})->with(['en','lt','ru']);

test('sqlite header validation is bounded and does not modify the upload', function (): void {
    $content = "SQLite format 3\0".str_repeat('x', 1024);
    $file = UploadedFile::fake()->createWithContent('backup.sqlite', $content);
    expect(Validator::make(['backup' => $file], ['backup' => ['file', new SqliteBackupHeader]])->passes())->toBeTrue()
        ->and(file_get_contents($file->getRealPath()))->toBe($content);
});
