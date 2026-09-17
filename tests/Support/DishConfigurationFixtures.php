<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Role;
use App\Models\User;

final class DishConfigurationFixtures
{
    /** @return array{User,Branch,MenuItem} */
    public static function context(): array
    {
        $actor = User::factory()->create();
        $actor->roles()->attach(Role::query()->where('code', SystemRole::Superadmin->value)->firstOrFail());
        $branch = Branch::factory()->create();
        $menu = Menu::factory()->for($branch)->create();
        $category = MenuCategory::factory()->for($menu)->create();
        $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['price_cents' => 1000]);

        return [$actor, $branch, $item];
    }
}
