<?php

declare(strict_types=1);

namespace App\Http\Requests\Superadmin;

use App\Actions\Backups\PrepareSqliteRestoreCandidateAction;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;
use SplFileObject;

final class RestoreSqliteBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->isSuperadmin();
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'backup' => [
                'required',
                'file',
                'min:1',
                'max:'.intdiv(PrepareSqliteRestoreCandidateAction::MAXIMUM_BYTES, 1024),
                'extensions:sqlite,sqlite3,db',
                'mimetypes:application/vnd.sqlite3,application/x-sqlite3,application/octet-stream',
            ],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $backup = $this->file('backup');

                if (! $backup instanceof UploadedFile || ! $backup->isValid()) {
                    return;
                }

                $path = $backup->getRealPath();

                if (! is_string($path)) {
                    $validator->errors()->add('backup', __('validation.sqlite_backup_invalid'));

                    return;
                }

                $file = new SplFileObject($path, 'rb');

                if ($file->fread(16) !== "SQLite format 3\0") {
                    $validator->errors()->add('backup', __('validation.sqlite_backup_invalid'));
                }
            },
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
