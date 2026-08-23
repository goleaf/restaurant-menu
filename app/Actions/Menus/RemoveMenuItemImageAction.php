<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Actions\Media\RemoveLocalImageAction;
use App\Models\MenuItem;

final class RemoveMenuItemImageAction
{
    public function __construct(
        private readonly RemoveLocalImageAction $removeLocalImage,
    ) {}

    public function handle(MenuItem $item): MenuItem
    {
        $this->removeLocalImage->handle(
            oldPath: $item->image,
            persist: function () use ($item): void {
                $item->forceFill(['image' => null])->saveOrFail();
            },
        );

        return $item;
    }
}
