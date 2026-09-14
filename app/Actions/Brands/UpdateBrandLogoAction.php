<?php

declare(strict_types=1);

namespace App\Actions\Brands;

use App\Actions\Media\RemoveLocalImageAction;
use App\Actions\Media\ReplaceLocalImageAction;
use App\Models\Brand;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class UpdateBrandLogoAction
{
    public function __construct(
        private readonly ReplaceLocalImageAction $replaceLocalImage,
        private readonly RemoveLocalImageAction $removeLocalImage,
    ) {}

    public function handle(Brand $brand, ?UploadedFile $file): Brand
    {
        $current = DB::transaction(function () use ($brand, $file): Brand {
            $current = Brand::query()
                ->select(['id', 'organization_id', 'logo_path', 'updated_at'])
                ->where('organization_id', $brand->getRawOriginal('organization_id'))
                ->lockForUpdate()
                ->findOrFail($brand->getKey());

            if ($file instanceof UploadedFile) {
                $this->replaceLocalImage->handle(
                    file: $file,
                    directory: "media/organizations/{$current->organization_id}/brands/{$current->id}/logos",
                    oldPath: $current->logo_path,
                    persist: function (string $path) use ($current): void {
                        if ($current->forceFill(['logo_path' => $path])->save() !== true) {
                            throw new RuntimeException('The image reference could not be saved.');
                        }
                    },
                );
            } else {
                $this->removeLocalImage->handle(
                    oldPath: $current->logo_path,
                    persist: function () use ($current): void {
                        if ($current->forceFill(['logo_path' => null])->save() !== true) {
                            throw new RuntimeException('The image reference could not be saved.');
                        }
                    },
                );
            }

            return $current;
        });

        $brand->forceFill($current->only(['logo_path', 'updated_at']))
            ->syncOriginalAttributes(['logo_path', 'updated_at']);

        return $brand;
    }
}
