<?php

declare(strict_types=1);

use App\Actions\Menus\CreateMenuAction;
use App\Actions\Menus\CreateMenuCategoryAction;
use App\Actions\Menus\UpdateMenuAction;
use App\Actions\Menus\UpdateMenuCategoryAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\MenuStatus;
use App\Enums\OrganizationUserStatus;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
});

test('menu mutation actions authorize their explicit actor without a browser session', function (string $operation): void {
    [$actor, $organization, $branch, $menu, $category] = menuMutationAuthorizationContext();

    expect(auth()->check())->toBeFalse();

    $result = runMenuMutationAuthorizationOperation($operation, $actor, $branch, $menu, $category);

    expect($result->exists)->toBeTrue()
        ->and($result->name)->toBe('Changed name');
})->with(['create menu', 'update menu', 'create category', 'update category']);

test('menu mutation actions reject an unrelated actor without persisting changes', function (string $operation): void {
    [$owner, $organization, $branch, $menu, $category] = menuMutationAuthorizationContext();
    $actor = User::factory()->create();
    $counts = [Menu::query()->count(), MenuCategory::query()->count()];

    expect(fn () => runMenuMutationAuthorizationOperation($operation, $actor, $branch, $menu, $category))
        ->toThrow(AuthorizationException::class);

    expect([Menu::query()->count(), MenuCategory::query()->count()])->toBe($counts)
        ->and($menu->fresh()->name)->toBe('Original menu')
        ->and($category->fresh()->name)->toBe('Original category');
})->with(['create menu', 'update menu', 'create category', 'update category']);

test('menu mutation actions reject membership revoked after the actor was loaded', function (string $operation): void {
    [$actor, $organization, $branch, $menu, $category] = menuMutationAuthorizationContext();
    $actor->load('roles', 'organizationMemberships');
    $organization->memberships()->where('user_id', $actor->id)->firstOrFail()
        ->forceFill(['status' => OrganizationUserStatus::Suspended])->save();

    expect(fn () => runMenuMutationAuthorizationOperation($operation, $actor, $branch, $menu, $category))
        ->toThrow(AuthorizationException::class);
})->with(['create menu', 'update menu', 'create category', 'update category']);

test('menu updates persist only operation fields from current scoped records', function (): void {
    [$actor, $organization, $branch, $menu, $category] = menuMutationAuthorizationContext();
    $foreign = Menu::factory()->create();
    $menu->branch_id = $foreign->branch_id;
    $category->menu_id = $foreign->id;

    runMenuMutationAuthorizationOperation('update menu', $actor, $branch, $menu, $category);
    runMenuMutationAuthorizationOperation('update category', $actor, $branch, $menu, $category);

    expect($menu->fresh()->branch_id)->toBe($branch->id)
        ->and($category->fresh()->menu_id)->toBe($menu->id);
});

/** @return array{User, Organization, Branch, Menu, MenuCategory} */
function menuMutationAuthorizationContext(): array
{
    $actor = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($actor, ['name' => 'Action authorization restaurant']);
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $menu = Menu::factory()->for($branch)->create(['name' => 'Original menu']);
    $category = MenuCategory::factory()->for($menu)->create(['name' => 'Original category']);

    return [$actor, $organization, $branch, $menu, $category];
}

function runMenuMutationAuthorizationOperation(string $operation, User $actor, Branch $branch, Menu $menu, MenuCategory $category): Menu|MenuCategory
{
    $menuData = ['name' => 'Changed name', 'status' => MenuStatus::Active, 'sort_order' => 3];
    $categoryData = ['parent_id' => null, 'name' => 'Changed name', 'description' => null, 'icon' => 'book-open', 'sort_order' => 3, 'is_active' => true];

    return match ($operation) {
        'create menu' => app(CreateMenuAction::class)->handle($branch, $menuData, $actor),
        'update menu' => app(UpdateMenuAction::class)->handle($menu, $menuData, $actor),
        'create category' => app(CreateMenuCategoryAction::class)->handle($menu, $categoryData, $actor),
        'update category' => app(UpdateMenuCategoryAction::class)->handle($category, $categoryData, $actor),
    };
}
