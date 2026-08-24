<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\MenuItemImage;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class PromoteMenuItemImageAction
{
    public function handle(Branch $branch, MenuItem $item, MenuItemImage $image): MenuItem
    {
        if ((int) $image->menu_item_id !== (int) $item->id) {
            throw new InvalidArgumentException('The image does not belong to the selected menu item.');
        }

        return DB::transaction(function () use ($branch, $item, $image): MenuItem {
            $scopedItem = MenuItem::query()
                ->select(['id', 'menu_id', 'image'])
                ->whereKey($item->id)
                ->whereHas('menu', fn ($query) => $query->where('branch_id', $branch->id))
                ->lockForUpdate()
                ->first();
            $scopedImage = MenuItemImage::query()
                ->select(['id', 'menu_item_id', 'path', 'sort_order'])
                ->whereKey($image->id)
                ->where('menu_item_id', $item->id)
                ->lockForUpdate()
                ->first();

            if (! $scopedItem instanceof MenuItem || ! $scopedImage instanceof MenuItemImage) {
                throw new InvalidArgumentException('The image does not belong to the selected branch and menu item.');
            }

            $oldPrimaryPath = $scopedItem->image;
            $scopedItem->image = $scopedImage->path;
            $scopedItem->saveOrFail();

            if (filled($oldPrimaryPath)) {
                $scopedImage->path = $oldPrimaryPath;
                $scopedImage->saveOrFail();
            } else {
                $scopedImage->deleteOrFail();
            }

            return $scopedItem->refresh()->load('galleryImages');
        });
    }
}
