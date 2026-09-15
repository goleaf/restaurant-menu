<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Enums\MenuOperationKind;
use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\User;
use RuntimeException;

final class RemoveMenuItemImageAction
{
    public function __construct(
        private readonly RunMenuItemImageOperationAction $runOperation,
    ) {}

    public function handle(
        Branch $branch,
        MenuItem $item,
        ?string $expectedImageIdentity = null,
        ?string $requestId = null,
        ?User $actor = null,
    ): MenuItem {
        return $this->runOperation->handle(
            $branch, $item, null, MenuOperationKind::ImageRemove,
            $expectedImageIdentity, $requestId, $actor,
            function (MenuItem $currentItem): array {
                $oldPrimaryPath = $currentItem->image;
                $promotedImage = $currentItem->galleryImages()
                    ->select(['id', 'menu_item_id', 'path', 'sort_order', 'presentation'])
                    ->lockForUpdate()
                    ->first();

                $currentItem->image = $promotedImage?->path;
                $currentItem->image_presentation = $promotedImage?->presentation;

                if ($currentItem->save() !== true) {
                    throw new RuntimeException('The primary image reference could not be saved.');
                }

                if ($promotedImage !== null && $promotedImage->delete() !== true) {
                    throw new RuntimeException('The promoted gallery image could not be removed.');
                }

                return filled($oldPrimaryPath) ? [$oldPrimaryPath] : [];
            },
        );
    }
}
