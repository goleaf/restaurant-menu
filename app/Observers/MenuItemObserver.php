<?php

namespace App\Observers;

use App\Actions\Menus\GetGuestMenuForBranchAction;
use App\Models\Menu;
use App\Models\MenuItem;

class MenuItemObserver
{
    /**
     * Handle the MenuItem "created" event.
     */
    public function created(MenuItem $menuItem): void
    {
        $this->forgetGuestMenu($menuItem);
    }

    /**
     * Handle the MenuItem "updated" event.
     */
    public function updated(MenuItem $menuItem): void
    {
        $this->forgetGuestMenu($menuItem);
    }

    /**
     * Handle the MenuItem "deleted" event.
     */
    public function deleted(MenuItem $menuItem): void
    {
        $this->forgetGuestMenu($menuItem);
    }

    /**
     * Handle the MenuItem "restored" event.
     */
    public function restored(MenuItem $menuItem): void
    {
        $this->forgetGuestMenu($menuItem);
    }

    /**
     * Handle the MenuItem "force deleted" event.
     */
    public function forceDeleted(MenuItem $menuItem): void
    {
        $this->forgetGuestMenu($menuItem);
    }

    private function forgetGuestMenu(MenuItem $menuItem): void
    {
        $this->forgetForMenuId($menuItem->menu_id);

        $originalMenuId = $menuItem->getOriginal('menu_id');

        if (is_numeric($originalMenuId) && (int) $originalMenuId !== $menuItem->menu_id) {
            $this->forgetForMenuId((int) $originalMenuId);
        }
    }

    private function forgetForMenuId(?int $menuId): void
    {
        if ($menuId === null) {
            return;
        }

        $branchId = Menu::query()
            ->select('branch_id')
            ->whereKey($menuId)
            ->value('branch_id');

        if (is_numeric($branchId)) {
            GetGuestMenuForBranchAction::forgetForBranch((int) $branchId);
        }
    }
}
