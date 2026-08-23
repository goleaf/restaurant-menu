<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Actions\Media\DeleteLocalMediaFileAction;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Support\Collection;

final class DeleteMenuCategoryAction
{
    public function __construct(
        private readonly DeleteLocalMediaFileAction $deleteLocalMediaFile,
    ) {}

    public function handle(MenuCategory $category): void
    {
        $categoryIds = $this->descendantIds($category);
        $imagePaths = MenuItem::query()
            ->select(['id', 'category_id', 'image'])
            ->whereIn('category_id', $categoryIds)
            ->pluck('image');

        $category->deleteOrFail();

        $imagePaths->each($this->deleteLocalMediaFile->handle(...));
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
