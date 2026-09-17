<?php

declare(strict_types=1);

namespace App\Livewire\Superadmin\Backups;

use App\Actions\Backups\PrepareSqliteRestoreCandidateAction;
use App\Exceptions\InvalidSqliteBackupException;
use App\Livewire\Forms\Backups\RestoreUploadForm;
use App\Services\Backups\SqliteRestoreAuthorization;
use App\Support\Backups\PreparedRestoreStore;
use App\Support\Backups\RestoreUploadUrl;
use App\Support\Backups\SqliteBackupConstraints;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\Response;

final class RestoreSqlite extends Component
{
    use WithFileUploads;

    private PreparedRestoreStore $restoreCandidates;

    private RequirePassword $password;

    private RestoreUploadUrl $restoreUploadUrl;

    public RestoreUploadForm $upload;

    #[Locked]
    public string $grant = '';

    /** @var array{name: string, bytes: int, sha256: string}|null */
    #[Locked]
    public ?array $candidate = null;

    public function boot(PreparedRestoreStore $restoreCandidates, RequirePassword $password, RestoreUploadUrl $restoreUploadUrl): void
    {
        $this->restoreCandidates = $restoreCandidates;
        $this->password = $password;
        $this->restoreUploadUrl = $restoreUploadUrl;
    }

    #[Renderless]
    public function _startUpload(mixed $name, mixed $fileInfo, mixed $isMultiple): void
    {
        abort_unless($name === 'upload.backup' && $isMultiple === false, 422);
        $this->discardPreview();
        $this->dispatch('upload:generatedSignedUrl', name: $name, url: $this->restoreUploadUrl->issue(request()))->self();
    }

    public function mount(SqliteRestoreAuthorization $authorization): void
    {
        $authorization->handle(request());
        $this->requireFreshPassword();
    }

    public function updatedUploadBackup(): void
    {
        $this->discardPreview();
    }

    public function preview(PrepareSqliteRestoreCandidateAction $prepare, PreparedRestoreStore $candidates, SqliteRestoreAuthorization $authorization): void
    {
        $authorization->handle(request());
        $this->requireFreshPassword();
        $this->discardPreview();
        $file = $this->upload->file();
        try {
            $candidate = $prepare->handle($file->getPathname(), (string) config('database.default'));
        } catch (InvalidSqliteBackupException) {
            $this->upload->addError('backup', __('ui.superadmin.backup_restore.incompatible'));

            return;
        }
        $bound = $candidates->issue(request(), $candidate);
        $this->grant = $bound['grant'];
        $this->candidate = ['name' => $file->getClientOriginalName(), 'bytes' => $bound['bytes'], 'sha256' => $bound['sha256']];
    }

    public function render(SqliteRestoreAuthorization $authorization): View
    {
        $intent = $authorization->handle(request());
        $this->requireFreshPassword();

        return view('livewire.superadmin.backups.restore-sqlite', ['restoreReason' => $intent['reason'], 'maximumSizeMegabytes' => intdiv(SqliteBackupConstraints::MAXIMUM_BYTES, 1024 * 1024)])->title(__('ui.superadmin.backup_restore.title'));
    }

    private function requireFreshPassword(): void
    {
        $confirmation = $this->password->handle(request(), static fn (): Response => new Response(status: 204));
        abort_unless($confirmation instanceof Response && $confirmation->getStatusCode() === 204, 423);
    }

    private function discardPreview(): void
    {
        $this->restoreCandidates->forget(request());
        $this->grant = '';
        $this->candidate = null;
    }
}
