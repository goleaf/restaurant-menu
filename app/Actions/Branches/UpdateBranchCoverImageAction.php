<?php

declare(strict_types=1);

namespace App\Actions\Branches;

use App\Actions\Media\ReplaceLocalImageAction;
use App\Models\Branch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class UpdateBranchCoverImageAction
{
    public function __construct(
        private readonly ReplaceLocalImageAction $replaceLocalImage,
    ) {}

    public function handle(Branch $branch, UploadedFile $file): Branch
    {
        $current = DB::transaction(function () use ($branch, $file): Branch {
            $current = Branch::query()
                ->select(['id', 'organization_id', 'brand_id', 'cover_image_path', 'updated_at'])
                ->where('organization_id', $branch->getRawOriginal('organization_id'))
                ->where('brand_id', $branch->getRawOriginal('brand_id'))
                ->lockForUpdate()
                ->findOrFail($branch->getKey());

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
