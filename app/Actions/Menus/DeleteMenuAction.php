<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Actions\Media\DeleteLocalMediaFileAction;
use App\Models\Menu;

final class DeleteMenuAction
{
    public function __construct(
        private readonly DeleteLocalMediaFileAction $deleteLocalMediaFile,
    ) {}

    public function handle(Menu $menu): void
    {
        $imagePaths = $menu->items()
            ->select(['id', 'menu_id', 'image'])
            ->pluck('image');

        $menu->deleteOrFail();

        $imagePaths->each($this->deleteLocalMediaFile->handle(...));
    }
}
