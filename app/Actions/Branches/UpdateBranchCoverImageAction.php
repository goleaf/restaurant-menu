<?php

declare(strict_types=1);

namespace App\Actions\Branches;

use App\Actions\Media\RemoveLocalImageAction;
use App\Actions\Media\ReplaceLocalImageAction;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class UpdateBranchCoverImageAction
{
    public function __construct(
        private readonly ReplaceLocalImageAction $replaceLocalImage,
        private readonly RemoveLocalImageAction $removeLocalImage,
        private readonly SaveBranchMediaAction $saveMedia,
    ) {}

    public function handle(Branch $branch, ?UploadedFile $file, ?User $actor = null, ?string $expectedMediaFingerprint = null, ?string $requestId = null): Branch
    {
        if ($actor !== null) {
            if ($expectedMediaFingerprint === null) {
                throw ValidationException::withMessages(['cover' => __('settings.errors.conflict')]);
            }
            $result = $this->saveMedia->handle($actor, $branch, 'cover', $file, $expectedMediaFingerprint, $requestId ?? (string) Str::uuid());
            $branch->forceFill(['cover_image_path' => $result['path']])->syncOriginalAttributes(['cover_image_path']);

            return $branch;
        }

        $current = DB::transaction(function () use ($branch, $file): Branch {
            $current = Branch::query()
                ->select(['id', 'organization_id', 'brand_id', 'cover_image_path', 'updated_at'])
                ->where('organization_id', $branch->getRawOriginal('organization_id'))
                ->where('brand_id', $branch->getRawOriginal('brand_id'))
                ->lockForUpdate()
                ->findOrFail($branch->getKey());

            if ($file === null) {
                $this->removeLocalImage->handle($current->cover_image_path, function () use ($current): void {
                    if ($current->forceFill(['cover_image_path' => null])->save() !== true) {
                        throw new RuntimeException('The image reference could not be saved.');
                    }
                });

                return $current;
            }

            $this->replaceLocalImage->handle(
                file: $file,
                directory: "media/organizations/{$current->organization_id}/brands/{$current->brand_id}/branches/{$current->id}/covers",
                oldPath: $current->cover_image_path,
                persist: function (string $path) use ($current): void {
                    if ($current->forceFill(['cover_image_path' => $path])->save() !== true) {
                        throw new RuntimeException('The image reference could not be saved.');
                    }
                },
            );

            return $current;
        });

        $branch->forceFill($current->only(['cover_image_path', 'updated_at']))
            ->syncOriginalAttributes(['cover_image_path', 'updated_at']);

        return $branch;
    }
}
