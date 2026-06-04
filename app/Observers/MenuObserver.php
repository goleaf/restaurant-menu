<?php

namespace App\Observers;

use App\Actions\Branches\ForgetBranchCacheAction;
use App\Models\Menu;

class MenuObserver
{
    /**
     * Handle the Menu "created" event.
     */
    public function created(Menu $menu): void
    {
        $this->forgetGuestMenu($menu);
    }

    /**
     * Handle the Menu "updated" event.
     */
    public function updated(Menu $menu): void
    {
        $this->forgetGuestMenu($menu);
    }

    /**
     * Handle the Menu "deleted" event.
     */
    public function deleted(Menu $menu): void
    {
        $this->softDeleteCategories($menu);
        $this->forgetGuestMenu($menu);
    }

    /**
     * Handle the Menu "restored" event.
     */
    public function restored(Menu $menu): void
    {
        $this->forgetGuestMenu($menu);
    }

    /**
     * Handle the Menu "force deleted" event.
     */
    public function forceDeleted(Menu $menu): void
    {
        $this->forgetGuestMenu($menu);
    }

    private function forgetGuestMenu(Menu $menu): void
    {
        app(ForgetBranchCacheAction::class)->handle((int) $menu->branch_id);
    }

    private function softDeleteCategories(Menu $menu): void
    {
        if ($menu->isForceDeleting()) {
            return;
        }

        $menu->categories()
            ->select(['id', 'menu_id', 'parent_id'])
            ->whereNull('parent_id')
            ->get()
            ->each
            ->delete();

        $menu->categories()
            ->select(['id', 'menu_id', 'parent_id'])
            ->get()
            ->each
            ->delete();
    }
}
