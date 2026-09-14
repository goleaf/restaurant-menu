<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Actions\Media\DeleteLocalMediaFilesAfterCommitAction;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemImage;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DeleteMenuCategoryAction
{
    public function __construct(
        private readonly DeleteLocalMediaFilesAfterCommitAction $deleteLocalMediaFiles,
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

            $this->deleteLocalMediaFiles->handle($this->imagePaths($category, $categoryIds), function () use ($category, $categoryIds): void {
                foreach ($categoryIds->chunk(200) as $batch) {
                    $this->galleryImages($category, $batch)->delete();
                }

                if ($category->delete() !== true) {
                    throw new RuntimeException('Menu category deletion was cancelled.');
                }
            });
        });
    }

    /**
     * @param  Collection<int, int>  $categoryIds
     * @return Generator<int, string|null>
     */
    private function imagePaths(MenuCategory $category, Collection $categoryIds): Generator
    {
        foreach ($categoryIds->chunk(200) as $batch) {
            yield from $this->items($category, $batch)->select(['id', 'image'])->lazyById(200)->pluck('image');
            yield from $this->galleryImages($category, $batch)->lazyById(200)->pluck('path');
        }
    }

    /**
     * @param  Collection<int, int>  $categoryIds
     * @return Builder<MenuItem>
     */
    private function items(MenuCategory $category, Collection $categoryIds): Builder
    {
        return MenuItem::query()
            ->where('menu_id', $category->menu_id)
            ->whereIn('category_id', $categoryIds);
    }

    /**
     * @param  Collection<int, int>  $categoryIds
     * @return Builder<MenuItemImage>
     */
    private function galleryImages(MenuCategory $category, Collection $categoryIds): Builder
    {
        return MenuItemImage::query()
            ->select(['id', 'path'])
            ->whereIn('menu_item_id', $this->items($category, $categoryIds)->select('id'));
    }

    /**
     * @return Collection<int, int>
     */
    private function descendantIds(MenuCategory $category): Collection
    {
        $visited = [$category->id => true];
        $parentIds = collect([$category->id]);

        while ($parentIds->isNotEmpty()) {
            $nextParentIds = collect();

            foreach ($parentIds->chunk(200) as $batch) {
                $children = MenuCategory::query()
                    ->select(['id', 'parent_id'])
                    ->where('menu_id', $category->menu_id)
                    ->whereIn('parent_id', $batch)
                    ->lazyById(200);

                foreach ($children as $child) {
                    if (isset($visited[$child->id])) {
                        continue;
                    }

                    $visited[$child->id] = true;
                    $nextParentIds->push($child->id);
                }
            }

            $parentIds = $nextParentIds;
        }

        return collect(array_keys($visited));
    }
}
