<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Branches\ForgetBranchCacheAction;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuCategoryTranslation;

class MenuCategoryTranslationObserver
{
    public function __construct(
        private readonly ForgetBranchCacheAction $forgetBranchCache,
    ) {}

    /**
     * Handle the MenuCategoryTranslation "created" event.
     */
    public function created(MenuCategoryTranslation $menuCategoryTranslation): void
    {
        $this->forgetGuestMenu($menuCategoryTranslation);
    }

    /**
     * Handle the MenuCategoryTranslation "updated" event.
     */
    public function updated(MenuCategoryTranslation $menuCategoryTranslation): void
    {
        $this->forgetGuestMenu($menuCategoryTranslation);
    }

    /**
     * Handle the MenuCategoryTranslation "deleted" event.
     */
    public function deleted(MenuCategoryTranslation $menuCategoryTranslation): void
    {
        $this->forgetGuestMenu($menuCategoryTranslation);
    }

    /**
     * Handle the MenuCategoryTranslation "restored" event.
     */
    public function restored(MenuCategoryTranslation $menuCategoryTranslation): void
    {
        $this->forgetGuestMenu($menuCategoryTranslation);
    }

    /**
     * Handle the MenuCategoryTranslation "force deleted" event.
     */
    public function forceDeleted(MenuCategoryTranslation $menuCategoryTranslation): void
    {
        $this->forgetGuestMenu($menuCategoryTranslation);
    }

    private function forgetGuestMenu(MenuCategoryTranslation $menuCategoryTranslation): void
    {
        $this->forgetForCategoryId($menuCategoryTranslation->menu_category_id);

        $originalCategoryId = $menuCategoryTranslation->getOriginal('menu_category_id');

        if (is_numeric($originalCategoryId) && (int) $originalCategoryId !== $menuCategoryTranslation->menu_category_id) {
            $this->forgetForCategoryId((int) $originalCategoryId);
        }
    }

    private function forgetForCategoryId(?int $categoryId): void
    {
        if ($categoryId === null) {
            return;
        }

        $menuId = MenuCategory::query()
            ->select('menu_id')
            ->whereKey($categoryId)
            ->value('menu_id');

        if (! is_numeric($menuId)) {
            return;
        }

        $branchId = Menu::query()
            ->select('branch_id')
            ->whereKey((int) $menuId)
            ->value('branch_id');

        if (is_numeric($branchId)) {
            $this->forgetBranchCache->handle((int) $branchId);
        }
    }
}
