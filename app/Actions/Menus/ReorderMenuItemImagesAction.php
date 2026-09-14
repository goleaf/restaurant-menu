<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\MenuItemImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

final class ReorderMenuItemImagesAction
{
    /**
     * @param  array<array-key, mixed>  $imageIds
     */
    public function handle(Branch $branch, MenuItem $item, array $imageIds): MenuItem
    {
        if (! array_is_list($imageIds) || count($imageIds) >= MenuItem::MAX_IMAGES) {
            $this->invalidOrder();
        }

        foreach ($imageIds as $id) {
            if (! is_int($id) || $id < 1) {
                $this->invalidOrder();
            }
        }

        if (count(array_unique($imageIds)) !== count($imageIds)) {
            $this->invalidOrder();
        }

        return DB::transaction(function () use ($branch, $item, $imageIds): MenuItem {
            $scopedItem = MenuItem::query()
                ->select(['id', 'menu_id', 'image'])
                ->whereKey($item->id)
                ->whereHas('menu', fn ($query) => $query->where('branch_id', $branch->id))
                ->lockForUpdate()
                ->first();

            if (! $scopedItem instanceof MenuItem) {
                throw new InvalidArgumentException('The menu item does not belong to the selected branch.');
            }

            $images = $scopedItem->galleryImages()
                ->select(['id', 'menu_item_id', 'path', 'sort_order'])
                ->limit(MenuItem::MAX_IMAGES)
                ->lockForUpdate()
                ->get();
            $currentIds = $images->modelKeys();

            if (count($currentIds) !== count($imageIds) || array_diff($currentIds, $imageIds) !== []) {
                $this->invalidOrder();
            }

            if ($currentIds !== $imageIds) {
                $highestPosition = (int) $images->max('sort_order');

                if ($highestPosition > PHP_INT_MAX - MenuItem::MAX_IMAGES) {
                    $this->invalidOrder();
                }

                foreach ($images as $index => $image) {
                    $this->savePosition($image, $highestPosition + 1 + $index);
                }

                $imagesById = $images->keyBy('id');

                foreach ($imageIds as $position => $id) {
                    $this->savePosition($imagesById[$id], $position);
                }
            }

            return $scopedItem->refresh()->load('galleryImages');
        });
    }

    private function savePosition(MenuItemImage $image, int $position): void
    {
        $image->sort_order = $position;

        if ($image->save() !== true) {
            throw new RuntimeException('The gallery image position could not be saved.');
        }
    }

    private function invalidOrder(): never
    {
        throw ValidationException::withMessages(['images' => __('uploads.errors.invalid_order')]);
    }
}
