<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Actions\Media\ReplaceLocalImageAction;
use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

final class ReplaceMenuItemImageAction
{
    public function __construct(
        private readonly ReplaceLocalImageAction $replaceLocalImage,
    ) {}

    public function handle(Branch $branch, MenuItem $item, UploadedFile $file): MenuItem
    {
        if (! Menu::query()->whereKey($item->menu_id)->where('branch_id', $branch->id)->exists()) {
            throw new InvalidArgumentException('The menu item must belong to the selected branch.');
        }

        $this->replaceLocalImage->handle(
            file: $file,
            directory: "media/organizations/{$branch->organization_id}/brands/{$branch->brand_id}/branches/{$branch->id}/menu-items/{$item->id}/images",
            oldPath: $item->image,
            persist: function (string $path) use ($item): void {
                $item->forceFill(['image' => $path])->saveOrFail();
            },
        );

        return $item;
    }
}
