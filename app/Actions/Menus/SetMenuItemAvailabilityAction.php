<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class SetMenuItemAvailabilityAction
{
    public function handle(User $actor, Branch $branch, MenuItem $item, bool $isAvailable): MenuItem
    {
        $menu = Menu::query()
            ->whereKey($item->menu_id)
            ->where('branch_id', $branch->id)
            ->first();

        if (! $menu instanceof Menu) {
            throw new InvalidArgumentException('The menu item must belong to the selected branch.');
        }

        Gate::forUser($actor)->authorize('changeAvailability', $menu);

        $item->updateOrFail(['is_available' => $isAvailable]);

        return $item;
    }
}
