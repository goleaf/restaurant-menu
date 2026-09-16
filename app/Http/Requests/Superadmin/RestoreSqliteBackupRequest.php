<?php

declare(strict_types=1);

namespace App\Http\Requests\Superadmin;

use App\Support\Backups\SqliteBackupConstraints;
use App\Rules\Backups\SqliteBackupHeader;
use Illuminate\Contracts\Validation\ValidationRule;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class RestoreSqliteBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->isSuperadmin();
    }

    /**
     * @return array<string, list<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'backup' => [
                'bail',
                'required',
                'file',
                'min:1',
                'max:'.intdiv(SqliteBackupConstraints::MAXIMUM_BYTES, 1024),
                'extensions:sqlite,sqlite3,db',
                'mimetypes:application/vnd.sqlite3,application/x-sqlite3,application/octet-stream',
                new SqliteBackupHeader,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'backup' => __('validation.attributes.sqlite_backup'),
        ];
    }
}
