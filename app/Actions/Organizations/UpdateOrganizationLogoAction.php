<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Actions\Media\RemoveLocalImageAction;
use App\Actions\Media\ReplaceLocalImageAction;
use App\Models\Organization;
use Illuminate\Http\UploadedFile;
use RuntimeException;

final class UpdateOrganizationLogoAction
{
    public function __construct(
        private readonly ReplaceLocalImageAction $replaceLocalImage,
        private readonly RemoveLocalImageAction $removeLocalImage,
    ) {}

    public function handle(Organization $organization, ?UploadedFile $file): Organization
    {
        if ($file instanceof UploadedFile) {
            $this->replaceLocalImage->handle(
                file: $file,
                directory: "media/organizations/{$organization->id}/logos",
                oldPath: $organization->logo_path,
                persist: function (string $path) use ($organization): void {
                    if ($organization->forceFill(['logo_path' => $path])->save() !== true) {
                        throw new RuntimeException('The image reference could not be saved.');
                    }
                },
            );
        } else {
            $this->removeLocalImage->handle(
                oldPath: $organization->logo_path,
                persist: function () use ($organization): void {
                    if ($organization->forceFill(['logo_path' => null])->save() !== true) {
                        throw new RuntimeException('The image reference could not be saved.');
                    }
                },
            );
        }

        return $organization;
    }
}
