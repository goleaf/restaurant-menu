<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Models\Branch;
use App\Models\Menu;
use App\Models\RestaurantOnboarding;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class UseExistingSetupMenuAction
{
    public function handle(User $actor, int $setupId, int $menuId, int $expectedVersion): RestaurantOnboarding
    {
        return DB::transaction(function () use ($actor, $setupId, $menuId, $expectedVersion): RestaurantOnboarding {
            $actor = User::query()->whereKey($actor->id)->firstOrFail();
            $setup = RestaurantOnboarding::query()->where('user_id', $actor->id)->whereKey($setupId)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $setup);
            $branch = Branch::query()->where('organization_id', $setup->organization_id)->where('brand_id', $setup->brand_id)->whereKey($setup->branch_id)->firstOrFail();
            Gate::forUser($actor)->authorize('manageMenu', $branch);
            $menu = Menu::query()->where('branch_id', $branch->id)->whereKey($menuId)->firstOrFail();
            if ((int) $setup->menu_id === $menu->id) {
                return $setup;
            }
            if ($setup->setup_version !== $expectedVersion) {
                throw ValidationException::withMessages(['existingMenuId' => __('center.conflict')]);
            }
            if ($setup->forceFill(['menu_id' => $menu->id, 'menu_category_id' => null, 'menu_item_id' => null, 'setup_version' => $setup->setup_version + 1])->save() !== true) {
                throw new \RuntimeException('The menu checkpoint could not be saved.');
            }

            return $setup;
        }, attempts: 3);
    }
}
