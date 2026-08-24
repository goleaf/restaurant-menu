<?php

declare(strict_types=1);

namespace App\Actions\DraftOrders;

use App\Actions\Menus\GetMenuAvailabilityStatusAction;
use App\Enums\MenuStatus;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Validation\ValidationException;

class EnsureDraftMenuItemAvailableAction
{
    public function __construct(
        private readonly GetMenuAvailabilityStatusAction $getMenuAvailabilityStatus,
    ) {}

    public function handle(MenuItem $menuItem, int $branchId, string $field = 'menu_item'): void
    {
        $menu = $menuItem->menu;
        $category = $menuItem->category;

        if (! $menu instanceof Menu
            || ! $category instanceof MenuCategory
            || $menu->branch_id !== $branchId
            || $menu->status !== MenuStatus::Active
            || ! $category->is_active
            || ! $menuItem->is_available
            || $menuItem->isTemporarilyHidden()) {
            throw ValidationException::withMessages([
                $field => __('menu.guest.item_no_longer_available'),
            ]);
        }

        $availability = $this->getMenuAvailabilityStatus->handle($menu);

        if (! $availability['is_available']) {
            throw ValidationException::withMessages([
                $field => __('ui.actions.draftorders.addguestdraftorderitemaction.message', [
                    'label' => $availability['label'],
                    'detail' => $availability['detail'],
                ]),
            ]);
        }
    }
}
