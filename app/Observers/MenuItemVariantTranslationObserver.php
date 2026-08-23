<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Branches\ForgetBranchCacheAction;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\MenuItemVariantTranslation;

class MenuItemVariantTranslationObserver
{
    public function __construct(
        private readonly ForgetBranchCacheAction $forgetBranchCache,
    ) {}

    public function created(MenuItemVariantTranslation $menuItemVariantTranslation): void
    {
        $this->forgetGuestMenu($menuItemVariantTranslation);
    }

    public function updated(MenuItemVariantTranslation $menuItemVariantTranslation): void
    {
        $this->forgetGuestMenu($menuItemVariantTranslation);
    }

    public function deleted(MenuItemVariantTranslation $menuItemVariantTranslation): void
    {
        $this->forgetGuestMenu($menuItemVariantTranslation);
    }

    private function forgetGuestMenu(MenuItemVariantTranslation $translation): void
    {
        $this->forgetForVariantId($translation->menu_item_variant_id);

        $originalVariantId = $translation->getOriginal('menu_item_variant_id');

        if (is_numeric($originalVariantId) && (int) $originalVariantId !== $translation->menu_item_variant_id) {
            $this->forgetForVariantId((int) $originalVariantId);
        }
    }

    private function forgetForVariantId(?int $variantId): void
    {
        if ($variantId === null) {
            return;
        }

        $itemId = MenuItemVariant::query()->select('menu_item_id')->whereKey($variantId)->value('menu_item_id');
        $menuId = is_numeric($itemId)
            ? MenuItem::query()->select('menu_id')->whereKey((int) $itemId)->value('menu_id')
            : null;
        $branchId = is_numeric($menuId)
            ? Menu::query()->select('branch_id')->whereKey((int) $menuId)->value('branch_id')
            : null;

        if (is_numeric($branchId)) {
            $this->forgetBranchCache->handle((int) $branchId);
        }
    }
}
