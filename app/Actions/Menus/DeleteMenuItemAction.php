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
        $imagePaths = $item->galleryImages()
            ->select(['id', 'menu_item_id', 'path'])
            ->pluck('path')
            ->prepend($item->image)
            ->filter(fn (mixed $path): bool => is_string($path) && filled($path))
            ->unique()
            ->values();

        DB::transaction(function () use ($item): void {
            MenuItemImage::query()
                ->where('menu_item_id', $item->id)
                ->delete();
            $item->deleteOrFail();
        });

        $imagePaths->each($this->deleteLocalMediaFile->handle(...));
    }
}
