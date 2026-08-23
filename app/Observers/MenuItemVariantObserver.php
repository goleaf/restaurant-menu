<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Branches\ForgetBranchCacheAction;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;

class MenuItemVariantObserver
{
    public function __construct(
        private readonly ForgetBranchCacheAction $forgetBranchCache,
    ) {}

    public function created(MenuItemVariant $menuItemVariant): void
    {
        $this->forgetGuestMenu($menuItemVariant);
    }

    public function updated(MenuItemVariant $menuItemVariant): void
    {
        $this->forgetGuestMenu($menuItemVariant);
    }

    public function deleted(MenuItemVariant $menuItemVariant): void
    {
        $this->forgetGuestMenu($menuItemVariant);
    }

    private function forgetGuestMenu(MenuItemVariant $variant): void
    {
        $this->forgetForItemId($variant->menu_item_id);

        $originalItemId = $variant->getOriginal('menu_item_id');

        if (is_numeric($originalItemId) && (int) $originalItemId !== $variant->menu_item_id) {
            $this->forgetForItemId((int) $originalItemId);
        }
    }

    private function forgetForItemId(?int $itemId): void
    {
        if ($itemId === null) {
            return;
        }

        $menuId = MenuItem::query()->select('menu_id')->whereKey($itemId)->value('menu_id');
        $branchId = is_numeric($menuId)
            ? Menu::query()->select('branch_id')->whereKey((int) $menuId)->value('branch_id')
            : null;

        if (is_numeric($branchId)) {
            $this->forgetBranchCache->handle((int) $branchId);
        }
    }
}
