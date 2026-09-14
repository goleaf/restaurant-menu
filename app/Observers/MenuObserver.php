<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Branches\ForgetBranchCacheAction;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Support\MenuDeletionContext;
use RuntimeException;

class MenuObserver
{
    public function __construct(
        private readonly ForgetBranchCacheAction $forgetBranchCache,
    ) {}

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
        if (! MenuDeletionContext::contains($menu)) {
            $this->softDeleteCategories($menu);
            $this->softDeleteRemainingItems($menu);
        }
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
        $this->forgetBranchCache->handle((int) $menu->branch_id);
    }

    private function softDeleteCategories(Menu $menu): void
    {
        if ($menu->isForceDeleting()) {
            return;
        }

        $menu->categories()
            ->select(['id', 'menu_id', 'parent_id'])
            ->whereNull('parent_id')
            ->reorder()
            ->lazyById(200)
            ->each(function (MenuCategory $category): void {
                if ($category->delete() !== true) {
                    throw new RuntimeException('Menu category deletion was cancelled.');
                }
            });

        $menu->categories()
            ->select(['id', 'menu_id', 'parent_id'])
            ->reorder()
            ->lazyById(200)
            ->each(function (MenuCategory $candidate) use ($menu): void {
                $isActive = $menu->categories()
                    ->whereKey($candidate->id)
                    ->reorder()
                    ->exists();

                if ($isActive && $candidate->delete() !== true) {
                    throw new RuntimeException('Menu category deletion was cancelled.');
                }
            });
    }

    private function softDeleteRemainingItems(Menu $menu): void
    {
        if ($menu->isForceDeleting()) {
            return;
        }

        $menu->items()
            ->select(['id', 'menu_id', 'category_id'])
            ->reorder()
            ->lazyById(200)
            ->each(function (MenuItem $item): void {
                if ($item->delete() !== true) {
                    throw new RuntimeException('Menu item deletion was cancelled.');
                }
            });
    }
}
