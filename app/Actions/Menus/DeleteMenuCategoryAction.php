<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Actions\Media\DeleteLocalMediaFileAction;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemImage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class DeleteMenuCategoryAction
{
    public function __construct(
        private readonly DeleteLocalMediaFileAction $deleteLocalMediaFile,
    ) {}

    public function handle(MenuCategory $category): void
    {
        DB::transaction(function () use ($category): void {
            $category = MenuCategory::query()
                ->select(['id', 'menu_id', 'parent_id', 'name', 'deleted_at'])
                ->whereKey($category->id)
                ->where('menu_id', $category->menu_id)
                ->lockForUpdate()
                ->firstOrFail();
            $categoryIds = $this->descendantIds($category);
            $items = MenuItem::query()
                ->select(['id', 'category_id', 'image'])
                ->whereIn('category_id', $categoryIds)
                ->get();
            $itemIds = $items->pluck('id');
            $galleryPaths = MenuItemImage::query()
                ->select(['id', 'menu_item_id', 'path'])
                ->whereIn('menu_item_id', $itemIds)
                ->pluck('path');
            $imagePaths = $items->pluck('image')
                ->merge($galleryPaths)
                ->filter(fn (mixed $path): bool => is_string($path) && filled($path))
                ->unique()
                ->values();

            MenuItemImage::query()
                ->whereIn('menu_item_id', $itemIds)
                ->delete();
            $category->deleteOrFail();

            DB::afterCommit(fn () => $imagePaths->each($this->deleteLocalMediaFile->handle(...)));
        });
    }

    /**
     * @return Collection<int, int>
     */
    private function descendantIds(MenuCategory $category): Collection
    {
        $ids = collect([$category->id]);
        $parentIds = collect([$category->id]);

        while ($parentIds->isNotEmpty()) {
            $parentIds = MenuCategory::query()
                ->select(['id', 'parent_id'])
                ->whereIn('parent_id', $parentIds)
                ->pluck('id');
            $ids = $ids->merge($parentIds);
        }

        return $ids->unique()->values();
    }
}
