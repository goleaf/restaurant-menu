<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Actions\Media\DeleteLocalMediaFileAction;
use App\Models\MenuItem;

final class DeleteMenuItemAction
{
    public function __construct(
        private readonly DeleteLocalMediaFileAction $deleteLocalMediaFile,
    ) {}

    public function handle(MenuItem $item): void
    {
        $imagePath = $item->image;

        $item->deleteOrFail();
        $this->deleteLocalMediaFile->handle($imagePath);
    }
}
