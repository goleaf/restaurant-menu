<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Enums\MenuOperationKind;
use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\MenuItemImage;
use App\Models\User;
use RuntimeException;

final class RemoveMenuItemGalleryImageAction
{
    public function __construct(
        private readonly RunMenuItemImageOperationAction $runOperation,
    ) {}

    public function handle(
        Branch $branch,
        MenuItem $item,
        MenuItemImage|int $image,
        ?string $expectedImageIdentity = null,
        ?string $requestId = null,
        ?User $actor = null,
    ): MenuItem {
        return $this->runOperation->handle(
            $branch, $item, $image instanceof MenuItemImage ? $image->id : $image,
            MenuOperationKind::ImageRemove, $expectedImageIdentity, $requestId, $actor,
            function (MenuItem $currentItem, ?MenuItemImage $currentImage): array {
                if (! $currentImage instanceof MenuItemImage) {
                    throw new RuntimeException('The gallery image reference is missing.');
                }

                $oldGalleryPath = $currentImage->path;

                if ($currentImage->delete() !== true) {
                    throw new RuntimeException('The gallery image could not be removed.');
                }

                return [$oldGalleryPath];
            },
        );
    }
}
