<?php

use App\Actions\Menus\GetGuestMenuForBranchAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\AuditLogAction;
use App\Enums\MenuStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\Index as BranchesIndex;
use App\Livewire\Organizations\Brands\Branches\Menu\Index as MenuIndex;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
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

test('menu page safely falls back from unsupported persisted category icons', function () {
    [$organization, $brand, $branch, $manager] = createMenuCrudBranch();
    grantMenuCrudPermissions($manager, $organization, [SystemPermission::ManageMenu]);
    $menu = Menu::factory()->for($branch)->create(['name' => 'Legacy Menu']);
    $category = MenuCategory::factory()->for($menu)->create([
        'name' => 'Legacy Category',
        'icon' => 'pizza',
    ]);

    Livewire::actingAs($manager)
        ->test(MenuIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->assertSee('Legacy Category')
        ->call('startEditingCategory', $category->id)
        ->assertSet('editingCategoryIcon', 'bookmark');
});

test('branch list shows menu link to users with manage menu permission', function () {
    [$organization, $brand, $branch, $manager] = createMenuCrudBranch('Food Group', 'Bella Brand');
    $menuRoute = route('organizations.brands.branches.menu.index', [$organization, $brand, $branch]);

    Livewire::actingAs($manager)
        ->test(BranchesIndex::class, ['organization' => $organization, 'brand' => $brand])
        ->assertDontSee($menuRoute, false);

    grantMenuCrudPermissions($manager, $organization, [SystemPermission::ChangeAvailability]);

    Livewire::actingAs($manager->fresh())
        ->test(BranchesIndex::class, ['organization' => $organization, 'brand' => $brand])
        ->assertSee($menuRoute, false)
        ->assertSee('Stop-list');

    grantMenuCrudPermissions($manager, $organization, [SystemPermission::ManageMenu]);

    Livewire::actingAs($manager)
        ->test(BranchesIndex::class, ['organization' => $organization, 'brand' => $brand])
        ->assertSee($menuRoute, false)
        ->assertSee('Menu');
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

test('manager can manage modifier groups options and item assignments', function () {
    [$organization, $brand, $branch, $manager] = createMenuCrudBranch();
    grantMenuCrudPermissions($manager, $organization, [
        SystemPermission::ManageMenu,
        SystemPermission::ChangePrices,
        SystemPermission::ChangeAvailability,
    ]);
    $menu = Menu::factory()->for($branch)->create([
        'name' => 'Modifier Menu',
        'status' => MenuStatus::Active,
    ]);
    $category = MenuCategory::factory()->for($menu)->create(['name' => 'Pizza']);
    $item = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->create(['name' => 'Pepperoni']);
    $cacheKey = GetGuestMenuForBranchAction::cacheKey($branch->id, 'en');

    app(GetGuestMenuForBranchAction::class)->handle($branch->id, 'en');
    expect(Cache::store(GetGuestMenuForBranchAction::cacheStore())->has($cacheKey))->toBeTrue();

    Livewire::actingAs($manager)
        ->test(MenuIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->set('modifierGroupName', 'Pizza size')
        ->set('modifierGroupIsRequired', true)
        ->set('modifierGroupMinSelect', 1)
        ->set('modifierGroupMaxSelect', 1)
        ->set('modifierGroupSortOrder', 10)
        ->call('createModifierGroup')
        ->assertHasNoErrors()
        ->assertSee('Pizza size');

    $group = ModifierGroup::query()
        ->where('branch_id', $branch->id)
        ->where('name', 'Pizza size')
        ->firstOrFail();

    expect(Cache::store(GetGuestMenuForBranchAction::cacheStore())->has($cacheKey))->toBeFalse();

    app(GetGuestMenuForBranchAction::class)->handle($branch->id, 'en');

    Livewire::actingAs($manager)
        ->test(MenuIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->set('modifierOptionGroupId', (string) $group->id)
        ->set('modifierOptionName', 'Large')
        ->set('modifierOptionPriceDelta', '3.50')
        ->set('modifierOptionIsAvailable', true)
        ->set('modifierOptionSortOrder', 20)
        ->call('createModifierOption')
        ->assertHasNoErrors()
        ->assertSee('Large');

    $option = ModifierOption::query()
        ->where('modifier_group_id', $group->id)
        ->where('name', 'Large')
        ->firstOrFail();

    expect($option->price_delta)->toBe('3.50')
        ->and(Cache::store(GetGuestMenuForBranchAction::cacheStore())->has($cacheKey))->toBeFalse();

    app(GetGuestMenuForBranchAction::class)->handle($branch->id, 'en');

    Livewire::actingAs($manager)
        ->test(MenuIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->set('modifierItemMenuId', (string) $menu->id)
        ->set('modifierItemId', (string) $item->id)
        ->set('modifierItemGroupId', (string) $group->id)
        ->call('attachModifierGroupToItem')
        ->assertHasNoErrors()
        ->assertSee('Pizza size');

    expect($item->modifierGroups()->pluck('modifier_groups.id')->all())->toBe([$group->id])
        ->and(Cache::store(GetGuestMenuForBranchAction::cacheStore())->has($cacheKey))->toBeFalse();

    Livewire::actingAs($manager)
        ->test(MenuIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->call('startEditingModifierGroup', $group->id)
        ->set('editingModifierGroupName', 'Choose size')
        ->set('editingModifierGroupMinSelect', 1)
        ->set('editingModifierGroupMaxSelect', 2)
        ->call('updateModifierGroup')
        ->assertHasNoErrors()
        ->call('startEditingModifierOption', $option->id)
        ->set('editingModifierOptionName', 'Extra large')
        ->set('editingModifierOptionPriceDelta', '5.00')
        ->set('editingModifierOptionIsAvailable', false)
        ->call('updateModifierOption')
        ->assertHasNoErrors()
        ->call('detachModifierGroupFromItem', $item->id, $group->id)
        ->assertHasNoErrors()
        ->call('deleteModifierOption', $option->id)
        ->assertHasNoErrors()
        ->call('deleteModifierGroup', $group->id)
        ->assertHasNoErrors();

    expect($group->fresh())->toBeNull()
        ->and($option->fresh())->toBeNull()
        ->and($item->modifierGroups()->exists())->toBeFalse();
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

test('head chef can manage stop list without menu crud access', function () {
    [$organization, $brand, $branch, $headChef] = createMenuCrudBranch();
    $headChefRole = Role::query()
        ->where('code', SystemRole::HeadChef->value)
        ->firstOrFail();

    OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $headChef->id)
        ->update(['role_id' => $headChefRole->id]);

    grantMenuCrudPermissions($headChef, $organization, [SystemPermission::ChangeAvailability]);

    $menu = Menu::factory()
        ->for($branch)
        ->create([
            'name' => 'Chef Menu',
            'status' => MenuStatus::Active,
        ]);
    $category = MenuCategory::factory()
        ->for($menu)
        ->create(['name' => 'Mains']);
    $availableItem = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->create([
            'name' => 'Grilled fish',
            'price' => '17.00',
            'is_available' => true,
        ]);
    $stopListItem = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->create([
            'name' => 'Sold out steak',
            'price' => '22.00',
            'is_available' => false,
        ]);
    $cacheKey = GetGuestMenuForBranchAction::cacheKey($branch->id, 'en');

    app(GetGuestMenuForBranchAction::class)->handle($branch->id, 'en');

    expect(Cache::store(GetGuestMenuForBranchAction::cacheStore())->has($cacheKey))->toBeTrue();

    $this->actingAs($headChef->fresh())
        ->get(route('organizations.brands.branches.menu.index', [$organization, $brand, $branch]))
        ->assertOk()
        ->assertSeeText('Stop-list')
        ->assertSeeText('Currently out of stock')
        ->assertSeeText('Available dishes')
        ->assertSeeText('Sold out steak')
        ->assertSeeText('Grilled fish')
        ->assertDontSeeText('New dish');

    Livewire::actingAs($headChef->fresh())
        ->test(MenuIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->assertSet('canManageMenu', false)
        ->assertSet('canChangeAvailability', true)
        ->assertSee('data-section="menu-stop-list"', false)
        ->assertSee('Add to stop-list')
        ->assertSee('Return to menu')
        ->assertDontSee('New dish')
        ->call('setItemAvailability', $availableItem->id, false)
        ->assertHasNoErrors();

    $availableItem->refresh();

    expect($availableItem->is_available)->toBeFalse()
        ->and(Cache::store(GetGuestMenuForBranchAction::cacheStore())->has($cacheKey))->toBeFalse()
        ->and(AuditLog::query()
            ->where('action', AuditLogAction::MenuAvailabilityChanged->value)
            ->where('entity_type', 'menu_item')
            ->where('entity_id', $availableItem->id)
            ->exists())->toBeTrue();

    $payload = app(GetGuestMenuForBranchAction::class)->handle($branch->id, 'en');
    $guestItemPayload = collect($payload['categories'])
        ->flatMap(fn (array $category): array => $category['items'])
        ->firstWhere('id', $availableItem->id);

    expect($guestItemPayload['is_available'])->toBeFalse();

    Livewire::actingAs($headChef->fresh())
        ->test(MenuIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->call('setItemAvailability', $availableItem->id, true)
        ->assertHasNoErrors();

    expect($availableItem->refresh()->is_available)->toBeTrue()
        ->and($stopListItem->refresh()->is_available)->toBeFalse();
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

    expect(MenuItem::query()->whereKey($firstItem->id)->exists())->toBeFalse()
        ->and(MenuItem::withTrashed()->findOrFail($firstItem->id)->trashed())->toBeTrue();
    Storage::disk('public')->assertMissing($firstItem->image);

    Livewire::actingAs($manager)
        ->test(MenuIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->call('deleteCategory', $category->id)
        ->assertHasNoErrors();

    expect(MenuCategory::query()->whereKey($category->id)->exists())->toBeFalse()
        ->and(MenuItem::query()->whereKey($secondItem->id)->exists())->toBeFalse()
        ->and(MenuCategory::withTrashed()->findOrFail($category->id)->trashed())->toBeTrue()
        ->and(MenuItem::withTrashed()->findOrFail($secondItem->id)->trashed())->toBeTrue();
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
        ->and(MenuItem::query()->whereKey($remainingItem->id)->exists())->toBeFalse()
        ->and(Menu::withTrashed()->findOrFail($menu->id)->trashed())->toBeTrue()
        ->and(MenuCategory::withTrashed()->findOrFail($remainingCategory->id)->trashed())->toBeTrue()
        ->and(MenuItem::withTrashed()->findOrFail($remainingItem->id)->trashed())->toBeTrue();
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
    $restrictedRole = Role::query()
        ->where('code', SystemRole::Waiter->value)
        ->firstOrFail();

    $membership = OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $manager->id)
        ->firstOrFail();
    $membership->forceFill(['role_id' => $restrictedRole->id])->saveOrFail();

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
