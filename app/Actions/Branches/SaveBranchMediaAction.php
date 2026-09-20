<?php

declare(strict_types=1);

namespace App\Actions\Branches;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Media\DeleteLocalMediaFileAction;
use App\Actions\Media\DeleteRolledBackLocalImageAction;
use App\Actions\Media\StoreLocalImageAction;
use App\Enums\AuditLogAction;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class SaveBranchMediaAction
{
    public function __construct(
        private readonly ExecuteBranchSettingsChangeAction $execute,
        private readonly StoreLocalImageAction $store,
        private readonly DeleteLocalMediaFileAction $delete,
        private readonly DeleteRolledBackLocalImageAction $rollback,
        private readonly RecordAuditLogAction $audit,
    ) {}

    /** @return array{fingerprint: string, path: string|null} */
    public function handle(User $actor, Branch $branch, string $kind, ?UploadedFile $file, string $expectedFingerprint, string $requestId): array
    {
        Validator::make(['kind' => $kind, 'requestId' => $requestId], [
            'kind' => ['required', Rule::in(['logo', 'cover'])], 'requestId' => ['required', 'uuid'],
        ])->validate();
        $current = Branch::query()->select(['id', 'organization_id', 'brand_id', 'deleted_at'])
            ->where('organization_id', $branch->getRawOriginal('organization_id'))
            ->where('brand_id', $branch->getRawOriginal('brand_id'))->whereKey($branch->id)->firstOrFail();
        $currentActor = User::query()->select(['id'])->whereKey($actor->id)->firstOrFail();
        $gate = Gate::forUser($currentActor);
        if ($gate->denies('update', $current)) {
            $gate->authorize('manageSettings', $current);
        }
        $column = $kind === 'logo' ? 'logo_path' : 'cover_image_path';
        $directory = "media/organizations/{$current->organization_id}/brands/{$current->brand_id}/branches/{$current->id}/".($kind === 'logo' ? 'logos' : 'covers');
        $fileHash = $file === null ? null : hash_file('sha256', $file->getPathname());
        $newPath = $file === null ? null : $this->store->handle($file, $directory);
        $registered = false;
        $committed = false;

        try {
            $result = $this->execute->handle($currentActor, $branch, $kind, $requestId, [
                'expected' => $expectedFingerprint, 'file' => $fileHash,
            ], function (User $actor, Branch $branch) use ($kind, $column, $newPath, $expectedFingerprint, &$registered, &$committed): array {
                $oldPath = $branch->getAttribute($column);
                if (! hash_equals($expectedFingerprint, hash('sha256', (string) $oldPath))) {
                    throw ValidationException::withMessages([$kind => __('settings.errors.conflict')]);
                }
                if ($newPath !== null) {
                    DB::connection()->afterRollBack(fn () => $this->rollback->handle($newPath));
                }
                DB::afterCommit(function () use ($oldPath, $newPath, &$committed): void {
                    $committed = true;
                    if ($oldPath !== $newPath) {
                        $this->delete->handle($oldPath);
                    }
                });
                $registered = true;
                if ($oldPath !== $newPath) {
                    if ($branch->forceFill([$column => $newPath])->save() !== true) {
                        throw new RuntimeException('The restaurant image reference could not be saved.');
                    }
                    $this->audit->handle(
                        action: AuditLogAction::BranchSettingsChanged, entityType: 'branch', entityId: $branch->id,
                        actorUser: $actor, organizationId: $branch->organization_id, branchId: $branch->id,
                        oldValues: ['group' => $kind, $column => $oldPath], newValues: ['group' => $kind, $column => $newPath],
                    );
                }

                return ['fingerprint' => hash('sha256', (string) $newPath), 'path' => $newPath];
            }, allowStructuralUpdate: true);
        } catch (Throwable $exception) {
            if (! $registered && ! $committed && $newPath !== null) {
                $this->rollback->handle($newPath);
            }

            throw $exception;
        }
        if (! $registered && $newPath !== null) {
            $this->rollback->handle($newPath);
        }

        return $result;
    }
}
