<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Branches\ForgetBranchCacheAction;
use App\Actions\Menus\AdvanceDishConfigurationVersionAction;
use App\Actions\Menus\MarkMenuCopySourceChangedAction;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;

class MenuItemVariantObserver
{
    public function __construct(
        private readonly ForgetBranchCacheAction $forgetBranchCache,
        private readonly AdvanceDishConfigurationVersionAction $versions,
        private readonly MarkMenuCopySourceChangedAction $markCopySourceChanged,
    ) {}

    public function created(MenuItemVariant $menuItemVariant): void
    {
        $this->forgetGuestMenu($menuItemVariant);
    }

    public function updated(MenuItemVariant $menuItemVariant): void
    {
        if (array_diff(array_keys($menuItemVariant->getChanges()), ['updated_at', 'content_version']) === []) {
            return;
        }
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

        $this->versions->variants($itemId);
        $this->markCopySourceChanged->handle($itemId);

        $menuId = MenuItem::query()->select('menu_id')->whereKey($itemId)->value('menu_id');
        $branchId = is_numeric($menuId)
            ? Menu::query()->select('branch_id')->whereKey((int) $menuId)->value('branch_id')
            : null;

        if (is_numeric($branchId)) {
            $this->forgetBranchCache->handle((int) $branchId);
        }
    }
}
