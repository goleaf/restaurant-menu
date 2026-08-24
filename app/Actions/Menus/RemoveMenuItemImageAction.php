<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Actions\Media\RemoveLocalImageAction;
use App\Models\Branch;
use App\Models\MenuItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class RemoveMenuItemImageAction
{
    public function __construct(
        private readonly RemoveLocalImageAction $removeLocalImage,
    ) {}

    public function handle(Branch $branch, MenuItem $item): MenuItem
    {
        $scopedItem = $this->branchItem($branch, $item);
        $oldPrimaryPath = $scopedItem->image;

        $this->removeLocalImage->handle(
            oldPath: $oldPrimaryPath,
            persist: function () use ($branch, $item, $oldPrimaryPath): void {
                DB::transaction(function () use ($branch, $item, $oldPrimaryPath): void {
                    $currentItem = $this->branchItem($branch, $item, lockForUpdate: true);

                    if ($currentItem->image !== $oldPrimaryPath) {
                        throw new RuntimeException('The primary image changed before it could be removed.');
                    }

                    $promotedImage = $currentItem->galleryImages()
                        ->select(['id', 'menu_item_id', 'path', 'sort_order'])
                        ->lockForUpdate()
                        ->first();

                    $currentItem->image = $promotedImage?->path;
                    $currentItem->saveOrFail();
                    $promotedImage?->deleteOrFail();
                });
            },
        );

        return $scopedItem->refresh()->load('galleryImages');
    }

    private function branchItem(Branch $branch, MenuItem $item, bool $lockForUpdate = false): MenuItem
    {
        $query = MenuItem::query()
            ->select(['id', 'menu_id', 'image'])
            ->whereKey($item->id)
            ->whereHas('menu', fn ($menuQuery) => $menuQuery->where('branch_id', $branch->id));

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $scopedItem = $query->first();

        if (! $scopedItem instanceof MenuItem) {
            throw new InvalidArgumentException('The menu item does not belong to the selected branch.');
        }

        return $scopedItem;
    }
}
