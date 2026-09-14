<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Actions\Media\DeleteLocalMediaFilesAfterCommitAction;
use App\Models\Menu;
use App\Models\MenuItemImage;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DeleteMenuAction
{
    public function __construct(
        private readonly DeleteLocalMediaFilesAfterCommitAction $deleteLocalMediaFiles,
    ) {}

    public function handle(Menu $menu): void
    {
        DB::transaction(function () use ($menu): void {
            $menu = Menu::query()
                ->select(['id', 'branch_id', 'name', 'status', 'sort_order', 'deleted_at'])
                ->whereKey($menu->id)
                ->where('branch_id', $menu->branch_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->deleteLocalMediaFiles->handle($this->imagePaths($menu), function () use ($menu): void {
                $this->galleryImages($menu)->delete();

                if ($menu->delete() !== true) {
                    throw new RuntimeException('Menu deletion was cancelled.');
                }
            });
        });
    }

    /**
     * @return Generator<int, string|null>
     */
    private function imagePaths(Menu $menu): Generator
    {
        yield from $menu->items()->select(['id', 'menu_id', 'image'])->reorder()->lazyById(200)->pluck('image');
        yield from $this->galleryImages($menu)->lazyById(200)->pluck('path');
    }

    /**
     * @return Builder<MenuItemImage>
     */
    private function galleryImages(Menu $menu): Builder
    {
        return MenuItemImage::query()
            ->select(['id', 'path'])
            ->whereIn('menu_item_id', $menu->items()->select('menu_items.id')->reorder());
    }
}
