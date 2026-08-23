<?php

declare(strict_types=1);

namespace App\Actions\Branches;

use App\Actions\Media\RemoveLocalImageAction;
use App\Actions\Media\ReplaceLocalImageAction;
use App\Models\Branch;
use Illuminate\Http\UploadedFile;

final class UpdateBranchLogoAction
{
    public function __construct(
        private readonly ReplaceLocalImageAction $replaceLocalImage,
        private readonly RemoveLocalImageAction $removeLocalImage,
    ) {}

    public function handle(Branch $branch, ?UploadedFile $file): Branch
    {
        if ($file instanceof UploadedFile) {
            $this->replaceLocalImage->handle(
                file: $file,
                directory: "media/organizations/{$branch->organization_id}/brands/{$branch->brand_id}/branches/{$branch->id}/logos",
                oldPath: $branch->logo_path,
                persist: function (string $path) use ($branch): void {
                    $branch->forceFill(['logo_path' => $path])->saveOrFail();
                },
            );
        } else {
            $this->removeLocalImage->handle(
                oldPath: $branch->logo_path,
                persist: function () use ($branch): void {
                    $branch->forceFill(['logo_path' => null])->saveOrFail();
                },
            );
        }

        return $branch;
    }
}
