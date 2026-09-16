<?php

declare(strict_types=1);

namespace App\Support\Backups;

final class SqliteBackupConstraints
{
    public const int MAXIMUM_BYTES = 268_435_456;

    public const string HEADER = "SQLite format 3\0";
}
