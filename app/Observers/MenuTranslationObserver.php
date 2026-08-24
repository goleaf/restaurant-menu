<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Branches\ForgetBranchCacheAction;
use App\Models\Menu;
use App\Models\MenuTranslation;

class MenuTranslationObserver
{
    public function __construct(
        private readonly ForgetBranchCacheAction $forgetBranchCache,
    ) {}

    public function created(MenuTranslation $menuTranslation): void
    {
        $this->forgetGuestMenu($menuTranslation);
    }

    /**
     * Handle the MenuTranslation "updated" event.
     */
    public function updated(MenuTranslation $menuTranslation): void
    {
        $this->forgetGuestMenu($menuTranslation);
    }

    /**
     * Handle the MenuTranslation "deleted" event.
     */
    public function deleted(MenuTranslation $menuTranslation): void
    {
        $this->forgetGuestMenu($menuTranslation);
    }

    /**
     * Handle the MenuTranslation "restored" event.
     */
    public function restored(MenuTranslation $menuTranslation): void
    {
        $this->forgetGuestMenu($menuTranslation);
    }

    /**
     * Handle the MenuTranslation "force deleted" event.
     */
    public function forceDeleted(MenuTranslation $menuTranslation): void
    {
        $this->forgetGuestMenu($menuTranslation);
    }

    private function forgetGuestMenu(MenuTranslation $translation): void
    {
        $branchId = Menu::withTrashed()
            ->whereKey($translation->menu_id)
            ->value('branch_id');

        if (is_numeric($branchId)) {
            $this->forgetBranchCache->handle((int) $branchId);
        }
    }
}
