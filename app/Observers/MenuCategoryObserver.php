<?php

namespace App\Observers;

use App\Actions\Branches\ForgetBranchCacheAction;
use App\Models\Menu;
use App\Models\MenuCategory;

class MenuCategoryObserver
{
    /**
     * Handle the MenuCategory "created" event.
     */
    public function created(MenuCategory $menuCategory): void
    {
        $this->forgetGuestMenu($menuCategory);
    }

    /**
     * Handle the MenuCategory "updated" event.
     */
    public function updated(MenuCategory $menuCategory): void
    {
        $this->forgetGuestMenu($menuCategory);
    }

    /**
     * Handle the MenuCategory "deleted" event.
     */
    public function deleted(MenuCategory $menuCategory): void
    {
        $this->softDeleteChildren($menuCategory);
        $this->softDeleteItems($menuCategory);
        $this->forgetGuestMenu($menuCategory);
    }

    /**
     * Handle the MenuCategory "restored" event.
     */
    public function restored(MenuCategory $menuCategory): void
    {
        $this->forgetGuestMenu($menuCategory);
    }

    /**
     * Handle the MenuCategory "force deleted" event.
     */
    public function forceDeleted(MenuCategory $menuCategory): void
    {
        $this->forgetGuestMenu($menuCategory);
    }

    private function forgetGuestMenu(MenuCategory $menuCategory): void
    {
        $this->forgetForMenuId($menuCategory->menu_id);

        $originalMenuId = $menuCategory->getOriginal('menu_id');

        if (is_numeric($originalMenuId) && (int) $originalMenuId !== $menuCategory->menu_id) {
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
            app(ForgetBranchCacheAction::class)->handle((int) $branchId);
        }
    }

    private function softDeleteChildren(MenuCategory $menuCategory): void
    {
        if ($menuCategory->isForceDeleting()) {
            return;
        }

        $menuCategory->children()
            ->select(['id', 'menu_id', 'parent_id'])
            ->get()
            ->each
            ->delete();
    }

    private function softDeleteItems(MenuCategory $menuCategory): void
    {
        if ($menuCategory->isForceDeleting()) {
            return;
        }

        $menuCategory->items()
            ->select(['id', 'menu_id', 'category_id'])
            ->get()
            ->each
            ->delete();
    }
}
