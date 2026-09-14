<?php

declare(strict_types=1);

namespace App\Actions\Brands;

use App\Actions\Media\RemoveLocalImageAction;
use App\Actions\Media\ReplaceLocalImageAction;
use App\Models\Brand;
use Illuminate\Http\UploadedFile;
use RuntimeException;

final class UpdateBrandLogoAction
{
    public function __construct(
        private readonly ReplaceLocalImageAction $replaceLocalImage,
        private readonly RemoveLocalImageAction $removeLocalImage,
    ) {}

    public function handle(Brand $brand, ?UploadedFile $file): Brand
    {
        if ($file instanceof UploadedFile) {
            $this->replaceLocalImage->handle(
                file: $file,
                directory: "media/organizations/{$brand->organization_id}/brands/{$brand->id}/logos",
                oldPath: $brand->logo_path,
                persist: function (string $path) use ($brand): void {
                    if ($brand->forceFill(['logo_path' => $path])->save() !== true) {
                        throw new RuntimeException('The image reference could not be saved.');
                    }
                },
            );
        } else {
            $this->removeLocalImage->handle(
                oldPath: $brand->logo_path,
                persist: function () use ($brand): void {
                    if ($brand->forceFill(['logo_path' => null])->save() !== true) {
                        throw new RuntimeException('The image reference could not be saved.');
                    }
                },
            );
        }

        return $brand;
    }
}
