<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Enums\MenuOperationKind;
use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\MenuItemImage;
use App\Models\User;
use RuntimeException;

final class PromoteMenuItemImageAction
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
            MenuOperationKind::ImagePromote, $expectedImageIdentity, $requestId, $actor,
            function (MenuItem $currentItem, ?MenuItemImage $currentImage): array {
                if (! $currentImage instanceof MenuItemImage) {
                    throw new RuntimeException('The gallery image reference is missing.');
                }

                $oldPrimaryPath = $currentItem->image;
                $currentItem->image = $currentImage->path;

                if ($currentItem->save() !== true) {
                    throw new RuntimeException('The primary image reference could not be saved.');
                }

                if (filled($oldPrimaryPath)) {
                    $currentImage->path = $oldPrimaryPath;

                    if ($currentImage->save() !== true) {
                        throw new RuntimeException('The gallery image reference could not be saved.');
                    }
                } elseif ($currentImage->delete() !== true) {
                    throw new RuntimeException('The promoted gallery image could not be removed.');
                }

                return [];
            },
        );
    }
}
