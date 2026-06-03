<?php

namespace App\Observers;

use App\Actions\Menus\GetGuestMenuForBranchAction;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuItemTranslation;

class MenuItemTranslationObserver
{
    /**
     * Handle the MenuItemTranslation "created" event.
     */
    public function created(MenuItemTranslation $menuItemTranslation): void
    {
        $this->forgetGuestMenu($menuItemTranslation);
    }

    /**
     * Handle the MenuItemTranslation "updated" event.
     */
    public function updated(MenuItemTranslation $menuItemTranslation): void
    {
        $this->forgetGuestMenu($menuItemTranslation);
    }

    /**
     * Handle the MenuItemTranslation "deleted" event.
     */
    public function deleted(MenuItemTranslation $menuItemTranslation): void
    {
        $this->forgetGuestMenu($menuItemTranslation);
    }

    /**
     * Handle the MenuItemTranslation "restored" event.
     */
    public function restored(MenuItemTranslation $menuItemTranslation): void
    {
        $this->forgetGuestMenu($menuItemTranslation);
    }

    /**
     * Handle the MenuItemTranslation "force deleted" event.
     */
    public function forceDeleted(MenuItemTranslation $menuItemTranslation): void
    {
        $this->forgetGuestMenu($menuItemTranslation);
    }

    private function forgetGuestMenu(MenuItemTranslation $menuItemTranslation): void
    {
        $this->forgetForItemId($menuItemTranslation->menu_item_id);

        $originalItemId = $menuItemTranslation->getOriginal('menu_item_id');

        if (is_numeric($originalItemId) && (int) $originalItemId !== $menuItemTranslation->menu_item_id) {
            $this->forgetForItemId((int) $originalItemId);
        }
    }

    private function forgetForItemId(?int $itemId): void
    {
        if ($itemId === null) {
            return;
        }

        $menuId = MenuItem::query()
            ->select('menu_id')
            ->whereKey($itemId)
            ->value('menu_id');

        if (! is_numeric($menuId)) {
            return;
        }

        $branchId = Menu::query()
            ->select('branch_id')
            ->whereKey((int) $menuId)
            ->value('branch_id');

        if (is_numeric($branchId)) {
            GetGuestMenuForBranchAction::forgetForBranch((int) $branchId);
        }
    }
}
