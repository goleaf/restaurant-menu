<?php

declare(strict_types=1);

namespace App\Support\Validation;

use App\Rules\Backups\SqliteBackupHeader;
use App\Support\Backups\SqliteBackupConstraints;
use Illuminate\Contracts\Validation\ValidationRule;

final class SqliteRestoreRules
{
    /** @return array<string, list<string|ValidationRule>> */
    public static function upload(): array
    {
        return ['backup' => ['bail', 'required', 'file', 'min:1', 'max:'.intdiv(SqliteBackupConstraints::MAXIMUM_BYTES, 1024), 'extensions:sqlite,sqlite3,db', 'mimetypes:application/vnd.sqlite3,application/x-sqlite3,application/octet-stream', new SqliteBackupHeader]];
    }
}
