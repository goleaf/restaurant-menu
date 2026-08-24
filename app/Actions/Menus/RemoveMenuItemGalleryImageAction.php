<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Actions\Media\RemoveLocalImageAction;
use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\MenuItemImage;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class RemoveMenuItemGalleryImageAction
{
    public function __construct(
        private readonly RemoveLocalImageAction $removeLocalImage,
    ) {}

    public function handle(Branch $branch, MenuItem $item, MenuItemImage $image): MenuItem
    {
        if ((int) $image->menu_item_id !== (int) $item->id) {
            throw new InvalidArgumentException('The image does not belong to the selected menu item.');
        }

        $scopedItem = MenuItem::query()
            ->select(['id', 'menu_id', 'image'])
            ->whereKey($item->id)
            ->whereHas('menu', fn ($query) => $query->where('branch_id', $branch->id))
            ->first();
        $scopedImage = MenuItemImage::query()
            ->select(['id', 'menu_item_id', 'path', 'sort_order'])
            ->whereKey($image->id)
            ->where('menu_item_id', $item->id)
            ->first();

        if (! $scopedItem instanceof MenuItem || ! $scopedImage instanceof MenuItemImage) {
            throw new InvalidArgumentException('The image does not belong to the selected branch and menu item.');
        }

        $oldGalleryPath = $scopedImage->path;

        $this->removeLocalImage->handle(
            oldPath: $oldGalleryPath,
            persist: function () use ($branch, $item, $scopedImage, $oldGalleryPath): void {
                DB::transaction(function () use ($branch, $item, $scopedImage, $oldGalleryPath): void {
                    $currentItem = MenuItem::query()
                        ->select(['id', 'menu_id'])
                        ->whereKey($item->id)
                        ->whereHas('menu', fn ($query) => $query->where('branch_id', $branch->id))
                        ->lockForUpdate()
                        ->first();
                    $currentImage = MenuItemImage::query()
                        ->select(['id', 'menu_item_id', 'path', 'sort_order'])
                        ->whereKey($scopedImage->id)
                        ->where('menu_item_id', $item->id)
                        ->lockForUpdate()
                        ->first();

                    if (! $currentItem instanceof MenuItem || ! $currentImage instanceof MenuItemImage) {
                        throw new InvalidArgumentException('The image does not belong to the selected branch and menu item.');
                    }

                    if ($currentImage->path !== $oldGalleryPath) {
                        throw new RuntimeException('The gallery image changed before it could be removed.');
                    }

                    $currentImage->deleteOrFail();
                });
            },
        );

        return $scopedItem->refresh()->load('galleryImages');
    }
}
