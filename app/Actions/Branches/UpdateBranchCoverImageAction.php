<?php

declare(strict_types=1);

namespace App\Actions\Branches;

use App\Actions\Media\ReplaceLocalImageAction;
use App\Models\Branch;
use Illuminate\Http\UploadedFile;

final class UpdateBranchCoverImageAction
{
    public function __construct(
        private readonly ReplaceLocalImageAction $replaceLocalImage,
    ) {}

    public function handle(Branch $branch, UploadedFile $file): Branch
    {
        $this->replaceLocalImage->handle(
            file: $file,
            directory: "media/organizations/{$branch->organization_id}/brands/{$branch->brand_id}/branches/{$branch->id}/covers",
            oldPath: $branch->cover_image_path,
            persist: function (string $path) use ($branch): void {
                $branch->forceFill(['cover_image_path' => $path])->saveOrFail();
            },
        );

        return $branch;
    }
}
