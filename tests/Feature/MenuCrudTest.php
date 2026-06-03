<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\MenuStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Livewire\Organizations\Brands\Branches\Index as BranchesIndex;
use App\Livewire\Organizations\Brands\Branches\Menu\Index as MenuIndex;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('menu page requires authentication', function () {
    [$organization, $brand, $branch] = createMenuCrudBranch();

    $this->get(route('organizations.brands.branches.menu.index', [$organization, $brand, $branch]))
        ->assertRedirect(route('login'));
});

test('menu page requires manage menu permission', function () {
    [$organization, $brand, $branch, $manager] = createMenuCrudBranch();

    $this->actingAs($manager)
        ->get(route('organizations.brands.branches.menu.index', [$organization, $brand, $branch]))
        ->assertForbidden();

    grantMenuCrudPermissions($manager, $organization, [SystemPermission::ManageMenu]);

    $this->actingAs($manager)
        ->get(route('organizations.brands.branches.menu.index', [$organization, $brand, $branch]))
        ->assertOk()
        ->assertSee('Menu');
});

test('branch list shows menu link to users with manage menu permission', function () {
    [$organization, $brand, $branch, $manager] = createMenuCrudBranch('Food Group', 'Bella Brand');
    $menuRoute = route('organizations.brands.branches.menu.index', [$organization, $brand, $branch]);

    Livewire::actingAs($manager)
        ->test(BranchesIndex::class, ['organization' => $organization, 'brand' => $brand])
        ->assertDontSee($menuRoute, false);

    grantMenuCrudPermissions($manager, $organization, [SystemPermission::ManageMenu]);

    Livewire::actingAs($manager)
        ->test(BranchesIndex::class, ['organization' => $organization, 'brand' => $brand])
        ->assertSee($menuRoute, false);
});

test('manager can create menu categories dishes and upload local dish photo', function () {
    Storage::fake('public');
    [$organization, $brand, $branch, $manager] = createMenuCrudBranch();
    grantMenuCrudPermissions($manager, $organization, [
        SystemPermission::ManageMenu,
        SystemPermission::ChangePrices,
        SystemPermission::ChangeAvailability,
    ]);

    Livewire::actingAs($manager)
        ->test(MenuIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->assertSee('No menus yet.')
        ->set('menuName', 'Dinner Menu')
        ->set('menuStatus', MenuStatus::Active->value)
        ->set('menuSortOrder', 10)
        ->call('createMenu')
        ->assertHasNoErrors()
        ->assertSee('Dinner Menu');

    $menu = Menu::query()
        ->where('branch_id', $branch->id)
        ->where('name', 'Dinner Menu')
        ->firstOrFail();

    Livewire::actingAs($manager)
        ->test(MenuIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->set('categoryMenuId', (string) $menu->id)
        ->set('categoryName', 'Pizza')
        ->set('categoryDescription', 'Classic pizza selection')
        ->set('categoryIcon', 'cake')
        ->set('categorySortOrder', 20)
        ->call('createCategory')
        ->assertHasNoErrors()
        ->assertSee('Pizza');

    $category = MenuCategory::query()
        ->where('menu_id', $menu->id)
        ->where('name', 'Pizza')
        ->firstOrFail();

    Livewire::actingAs($manager)
        ->test(MenuIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->set('itemMenuId', (string) $menu->id)
        ->set('itemCategoryId', (string) $category->id)
        ->set('itemName', 'Margherita')
        ->set('itemDescription', 'Tomato, mozzarella, basil')
        ->set('itemPrice', '12.50')
        ->set('itemWeight', '450')
        ->set('itemCalories', '720')
        ->set('itemSortOrder', 30)
        ->set('itemIsAvailable', true)
        ->call('createItem')
        ->assertHasNoErrors()
        ->assertSee('Margherita');

    $item = MenuItem::query()
        ->where('menu_id', $menu->id)
        ->where('category_id', $category->id)
        ->where('name', 'Margherita')
        ->firstOrFail();

    Livewire::actingAs($manager)
        ->test(MenuIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->set('itemImages.'.$item->id, UploadedFile::fake()->image('margherita.jpg')->size(512))
        ->call('saveItemImage', $item->id)
        ->assertHasNoErrors();

    $item->refresh();

    expect($menu->refresh()->status)->toBe(MenuStatus::Active)
        ->and($menu->sort_order)->toBe(10)
        ->and($category->refresh()->description)->toBe('Classic pizza selection')
        ->and($category->icon)->toBe('cake')
        ->and($category->sort_order)->toBe(20)
        ->and($item->price)->toBe('12.50')
        ->and($item->weight)->toBe('450.00')
        ->and($item->calories)->toBe(720)
        ->and($item->sort_order)->toBe(30)
        ->and($item->image)->toStartWith('media/organizations/'.$organization->id.'/brands/'.$brand->id.'/branches/'.$branch->id.'/menu-items/'.$item->id.'/images/');

    Storage::disk('public')->assertExists($item->image);
});

test('price and availability changes require dedicated permissions', function () {
    [$organization, $brand, $branch, $manager] = createMenuCrudBranch();
    grantMenuCrudPermissions($manager, $organization, [SystemPermission::ManageMenu]);
    $menu = Menu::factory()->for($branch)->create(['name' => 'Limited Menu']);
    $category = MenuCategory::factory()->for($menu)->create(['name' => 'Starters']);
    $item = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->create([
            'name' => 'Soup',
            'price' => '8.00',
            'is_available' => true,
        ]);

    Livewire::actingAs($manager)
        ->test(MenuIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->assertSet('canChangePrices', false)
        ->assertSet('canChangeAvailability', false)
        ->call('startEditingItem', $item->id)
        ->set('editingItemPrice', '99.99')
        ->set('editingItemIsAvailable', false)
        ->call('updateItem')
        ->assertHasNoErrors();

    $item->refresh();

    expect($item->price)->toBe('8.00')
        ->and($item->is_available)->toBeTrue();

    Livewire::actingAs($manager)
        ->test(MenuIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->call('setItemAvailability', $item->id, false)
        ->assertForbidden();

    grantMenuCrudPermissions($manager, $organization, [
        SystemPermission::ChangePrices,
        SystemPermission::ChangeAvailability,
    ]);

    Livewire::actingAs($manager->fresh())
        ->test(MenuIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->assertSet('canChangePrices', true)
        ->assertSet('canChangeAvailability', true)
        ->call('startEditingItem', $item->id)
        ->set('editingItemPrice', '9.50')
        ->set('editingItemIsAvailable', false)
        ->call('updateItem')
        ->assertHasNoErrors()
        ->assertSee('Unavailable');

    $item->refresh();

    expect($item->price)->toBe('9.50')
        ->and($item->is_available)->toBeFalse();
});

test('manager can delete dishes categories and menus while cleaning local dish photos', function () {
    Storage::fake('public');
    [$organization, $brand, $branch, $manager] = createMenuCrudBranch();
    grantMenuCrudPermissions($manager, $organization, [SystemPermission::ManageMenu]);
    $menu = Menu::factory()->for($branch)->create(['name' => 'Cleanup Menu']);
    $category = MenuCategory::factory()->for($menu)->create(['name' => 'Cleanup Category']);
    $firstItem = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->create([
            'name' => 'First cleanup dish',
            'image' => 'media/test/first-cleanup.jpg',
        ]);
    $secondItem = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->create([
            'name' => 'Second cleanup dish',
            'image' => 'media/test/second-cleanup.jpg',
        ]);

    Storage::disk('public')->put($firstItem->image, 'first');
    Storage::disk('public')->put($secondItem->image, 'second');

    Livewire::actingAs($manager)
        ->test(MenuIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->call('deleteItem', $firstItem->id)
        ->assertHasNoErrors();

    expect(MenuItem::query()->whereKey($firstItem->id)->exists())->toBeFalse();
    Storage::disk('public')->assertMissing($firstItem->image);

    Livewire::actingAs($manager)
        ->test(MenuIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->call('deleteCategory', $category->id)
        ->assertHasNoErrors();

    expect(MenuCategory::query()->whereKey($category->id)->exists())->toBeFalse()
        ->and(MenuItem::query()->whereKey($secondItem->id)->exists())->toBeFalse();
    Storage::disk('public')->assertMissing($secondItem->image);

    $remainingCategory = MenuCategory::factory()->for($menu)->create(['name' => 'Remaining Category']);
    $remainingItem = MenuItem::factory()
        ->for($menu)
        ->for($remainingCategory, 'category')
        ->create([
            'name' => 'Remaining cleanup dish',
            'image' => 'media/test/remaining-cleanup.jpg',
        ]);
    Storage::disk('public')->put($remainingItem->image, 'remaining');

    Livewire::actingAs($manager)
        ->test(MenuIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->call('deleteMenu', $menu->id)
        ->assertHasNoErrors();

    expect(Menu::query()->whereKey($menu->id)->exists())->toBeFalse()
        ->and(MenuCategory::query()->whereKey($remainingCategory->id)->exists())->toBeFalse()
        ->and(MenuItem::query()->whereKey($remainingItem->id)->exists())->toBeFalse();
    Storage::disk('public')->assertMissing($remainingItem->image);
});

test('branch must belong to route brand and organization on menu page', function () {
    [$organization, $brand, , $manager] = createMenuCrudBranch();
    grantMenuCrudPermissions($manager, $organization, [SystemPermission::ManageMenu]);
    [, , $otherBranch] = createMenuCrudBranch('Other Menu Group', 'Other Menu Brand');

    Livewire::actingAs($manager)
        ->test(MenuIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $otherBranch])
        ->assertForbidden();
});

function createMenuCrudBranch(string $organizationName = 'Menu Group', string $brandName = 'Menu Brand'): array
{
    $manager = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($manager, ['name' => $organizationName]);
    $brand = Brand::factory()->for($organization)->create(['name' => $brandName]);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create(['name' => $brandName.' Branch']);

    return [$organization, $brand, $branch, $manager->fresh()];
}

/**
 * @param  list<SystemPermission>  $permissions
 */
function grantMenuCrudPermissions(User $user, Organization $organization, array $permissions): void
{
    $membership = OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $user->id)
        ->where('status', OrganizationUserStatus::Active->value)
        ->firstOrFail();

    $permissionRows = Permission::query()
        ->whereIn('code', array_map(
            fn (SystemPermission $permission): string => $permission->value,
            $permissions,
        ))
        ->get();

    foreach ($permissionRows as $permission) {
        $membership->role->permissions()->updateExistingPivot($permission->id, ['enabled' => true]);
    }
}
