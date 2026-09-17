<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Backups;

use App\Support\Validation\SqliteRestoreRules;
use Illuminate\Http\UploadedFile;
use Livewire\Form;

final class RestoreUploadForm extends Form
{
    public mixed $backup = null;

    public function file(): UploadedFile
    {
        $data = $this->validate(SqliteRestoreRules::upload(), ['backup.file' => __('ui.superadmin.backup_restore.invalid_file')], ['backup' => __('validation.attributes.sqlite_backup')]);

        return $data['backup'];
    }
}
