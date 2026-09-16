<?php

declare(strict_types=1);

namespace App\Rules\Backups;

use App\Support\Backups\SqliteBackupConstraints;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use SplFileObject;

final class SqliteBackupHeader implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            return;
        }

        $path = $value->getRealPath();
        if (! is_string($path) || (new SplFileObject($path, 'rb'))->fread(16) !== SqliteBackupConstraints::HEADER) {
            $fail('validation.sqlite_backup_invalid')->translate();
        }
    }
}
