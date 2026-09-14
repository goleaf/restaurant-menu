<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Actions\Media\DeleteLocalMediaFileAction;
use App\Models\MenuItem;
use App\Models\MenuItemImage;
use Illuminate\Support\Facades\DB;

final class DeleteMenuItemAction
{
    public function __construct(
        private readonly DeleteLocalMediaFileAction $deleteLocalMediaFile,
    ) {}

    public function handle(MenuItem $item): void
    {
        DB::transaction(function () use ($item): void {
            $item = MenuItem::query()
                ->select(['id', 'menu_id', 'category_id', 'name', 'price_cents', 'is_available', 'image', 'deleted_at'])
                ->whereKey($item->id)
                ->where('menu_id', $item->menu_id)
                ->lockForUpdate()
                ->firstOrFail();
            $imagePaths = $item->galleryImages()
                ->select(['id', 'menu_item_id', 'path'])
                ->pluck('path')
                ->prepend($item->image)
                ->filter(fn (mixed $path): bool => is_string($path) && filled($path))
                ->unique()
                ->values();

            MenuItemImage::query()
                ->where('menu_item_id', $item->id)
                ->delete();
            $item->deleteOrFail();

            DB::afterCommit(fn () => $imagePaths->each($this->deleteLocalMediaFile->handle(...)));
        });
    }
}
