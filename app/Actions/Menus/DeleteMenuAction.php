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

        DB::transaction(function () use ($menu, $itemIds): void {
            MenuItemImage::query()
                ->whereIn('menu_item_id', $itemIds)
                ->delete();
            $menu->deleteOrFail();
        });

        $imagePaths->each($this->deleteLocalMediaFile->handle(...));
    }
}
