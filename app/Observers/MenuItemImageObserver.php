<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Branches\ForgetBranchCacheAction;
use App\Actions\Menus\MarkMenuCopySourceChangedAction;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuItemImage;

final class MenuItemImageObserver
{
    public function __construct(
        private readonly ForgetBranchCacheAction $forgetBranchCache,
        private readonly MarkMenuCopySourceChangedAction $markCopySourceChanged,
    ) {}

    public function created(MenuItemImage $image): void
    {
        $this->changed((int) $image->menu_item_id);
    }

    public function updated(MenuItemImage $image): void
    {
        if (! $image->wasChanged(['menu_item_id', 'path', 'sort_order'])) {
            return;
        }
        $this->changed((int) $image->menu_item_id);
        if ($image->wasChanged('menu_item_id')) {
            $this->changed((int) $image->getOriginal('menu_item_id'));
        }
    }

    public function deleted(MenuItemImage $image): void
    {
        $this->changed((int) $image->menu_item_id);
    }

    private function changed(int $itemId): void
    {
        $item = MenuItem::withTrashed()->select(['id', 'menu_id'])->whereKey($itemId)->first();
        if (! $item instanceof MenuItem) {
            return;
        }
        MenuItem::withTrashed()->whereKey($itemId)->increment('media_version');
        $this->markCopySourceChanged->handle($itemId);
        $branchId = Menu::withTrashed()->whereKey($item->menu_id)->value('branch_id');
        if (is_numeric($branchId)) {
            $this->forgetBranchCache->handle((int) $branchId);
        }
    }
}
