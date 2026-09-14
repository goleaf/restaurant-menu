<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Actions\Media\DeleteLocalMediaFileAction;
use App\Models\Menu;
use App\Models\MenuItemImage;
use Illuminate\Support\Facades\DB;

final class DeleteMenuAction
{
    public function __construct(
        private readonly DeleteLocalMediaFileAction $deleteLocalMediaFile,
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
            $items = $menu->items()
                ->select(['id', 'menu_id', 'image'])
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
            $menu->deleteOrFail();

            DB::afterCommit(fn () => $imagePaths->each($this->deleteLocalMediaFile->handle(...)));
        });
    }
}
