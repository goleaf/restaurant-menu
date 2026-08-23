<?php

use App\Actions\Menus\GetGuestMenuForBranchAction;
use App\Actions\Menus\UpdateMenuItemAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\AuditLogAction;
use App\Enums\MenuItemVariantType;
use App\Enums\MenuStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\Index as BranchesIndex;
use App\Livewire\Organizations\Brands\Branches\Menu\Availability as MenuAvailability;
use App\Livewire\Organizations\Brands\Branches\Menu\Catalog as MenuCatalog;
use App\Livewire\Organizations\Brands\Branches\Menu\KitchenDepartments as MenuKitchenDepartments;
use App\Livewire\Organizations\Brands\Branches\Menu\Modifiers as MenuModifiers;
use App\Livewire\Organizations\Brands\Branches\Menu\Variants as MenuVariants;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
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

test('menu workflow children independently enforce their permissions', function () {
    [$organization, $brand, $branch, $manager] = createMenuCrudBranch();
    grantMenuCrudPermissions($manager, $organization, [SystemPermission::ChangeAvailability]);
    $parameters = [
        'organizationId' => $organization->id,
        'brandId' => $brand->id,
        'branchId' => $branch->id,
    ];

    Livewire::actingAs($manager)
        ->test(MenuAvailability::class, $parameters)
        ->assertOk();

    Livewire::actingAs($manager)
        ->test(MenuCatalog::class, $parameters)
        ->assertForbidden();

    Livewire::actingAs($manager)
        ->test(MenuKitchenDepartments::class, $parameters)
        ->assertForbidden();

    Livewire::actingAs($manager)
        ->test(MenuModifiers::class, $parameters)
        ->assertForbidden();
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
        ->test(MenuCatalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->assertSee('Legacy Category')
        ->call('startEditingCategory', $category->id)
        ->assertSet('editingCategoryIcon', 'bookmark');
});

test('dependent menu selectors never expose ids from another branch', function () {
    [$organization, $brand, $branch, $manager] = createMenuCrudBranch();
    grantMenuCrudPermissions($manager, $organization, [SystemPermission::ManageMenu]);
    $ownMenu = Menu::factory()->for($branch)->create();
    MenuCategory::factory()->for($ownMenu)->create();
    [, , $foreignBranch] = createMenuCrudBranch('Foreign Group', 'Foreign Brand');
    $foreignMenu = Menu::factory()->for($foreignBranch)->create();
    $foreignCategory = MenuCategory::factory()->for($foreignMenu)->create();
    MenuItem::factory()->for($foreignMenu)->for($foreignCategory, 'category')->create();
    $parameters = [
        'organizationId' => $organization->id,
        'brandId' => $brand->id,
        'branchId' => $branch->id,
    ];

    Livewire::actingAs($manager)
        ->test(MenuCatalog::class, $parameters)
        ->set('itemMenuId', (string) $foreignMenu->id)
        ->assertSet('itemCategoryId', '');

    Livewire::actingAs($manager)
        ->test(MenuModifiers::class, $parameters)
        ->set('modifierItemMenuId', (string) $foreignMenu->id)
        ->assertSet('modifierItemId', '');
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
        ->test(MenuCatalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
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
        ->test(MenuCatalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
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
        ->test(MenuCatalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->set('itemMenuId', (string) $menu->id)
        ->set('itemCategoryId', (string) $category->id)
        ->set('itemName', 'Margherita')
        ->set('itemDescription', 'Tomato, mozzarella, basil')
        ->set('itemPrice', '12.50')
        ->set('itemWeight', '450')
        ->set('itemCalories', '720')
        ->set('itemAllergens', ['gluten', 'milk'])
        ->set('itemDietaryLabels', ['vegetarian'])
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
        ->test(MenuCatalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->set('itemImages.'.$item->id, UploadedFile::fake()->image('margherita.jpg')->size(512))
        ->call('saveItemImage', $item->id)
        ->assertHasNoErrors();

    $item->refresh();

    expect($menu->refresh()->status)->toBe(MenuStatus::Active)
        ->and($menu->sort_order)->toBe(10)
        ->and($category->refresh()->description)->toBe('Classic pizza selection')
        ->and($category->icon)->toBe('cake')
        ->and($category->sort_order)->toBe(20)
        ->and($item->price_cents)->toBe(1250)
        ->and($item->allergens)->toBe(['gluten', 'milk'])
        ->and($item->dietary_labels)->toBe(['vegetarian'])
        ->and($item->weight)->toBe('450.00')
        ->and($item->calories)->toBe(720)
        ->and($item->sort_order)->toBe(30)
        ->and($item->image)->toStartWith('media/organizations/'.$organization->id.'/brands/'.$brand->id.'/branches/'.$branch->id.'/menu-items/'.$item->id.'/images/');

    Storage::disk('public')->assertExists($item->image);
});

test('menu item allergen and dietary selections reject unknown values and normalize updates', function () {
    [$organization, $brand, $branch, $manager] = createMenuCrudBranch();
    grantMenuCrudPermissions($manager, $organization, [SystemPermission::ManageMenu]);
    $menu = Menu::factory()->for($branch)->create();
    $category = MenuCategory::factory()->for($menu)->create();
    $item = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->create([
            'allergens' => ['eggs'],
            'dietary_labels' => ['vegetarian'],
        ]);
    $parameters = [
        'organizationId' => $organization->id,
        'brandId' => $brand->id,
        'branchId' => $branch->id,
    ];

    $component = Livewire::actingAs($manager)
        ->test(MenuCatalog::class, $parameters)
        ->assertSeeText('Gluten-containing cereals')
        ->assertSeeText('Dietary labels')
        ->call('startEditingItem', $item->id)
        ->assertSet('editingItemAllergens', ['eggs'])
        ->assertSet('editingItemDietaryLabels', ['vegetarian'])
        ->set('editingItemAllergens', ['unknown-allergen'])
        ->set('editingItemDietaryLabels', ['unknown-diet'])
        ->call('updateItem')
        ->assertHasErrors(['editingItemAllergens.0', 'editingItemDietaryLabels.0']);

    $component
        ->set('editingItemAllergens', ['milk', 'gluten'])
        ->set('editingItemDietaryLabels', ['vegan', 'vegetarian'])
        ->call('updateItem')
        ->assertHasNoErrors();

    expect($item->refresh()->allergens)->toBe(['gluten', 'milk'])
        ->and($item->dietary_labels)->toBe(['vegetarian', 'vegan']);
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
        ->test(MenuModifiers::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
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
        ->test(MenuModifiers::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
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

    expect($option->price_delta_cents)->toBe(350)
        ->and(Cache::store(GetGuestMenuForBranchAction::cacheStore())->has($cacheKey))->toBeFalse();

    app(GetGuestMenuForBranchAction::class)->handle($branch->id, 'en');

    Livewire::actingAs($manager)
        ->test(MenuModifiers::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->set('modifierItemMenuId', (string) $menu->id)
        ->set('modifierItemId', (string) $item->id)
        ->set('modifierItemGroupId', (string) $group->id)
        ->call('attachModifierGroupToItem')
        ->assertHasNoErrors()
        ->assertSee('Pizza size');

    expect($item->modifierGroups()->pluck('modifier_groups.id')->all())->toBe([$group->id])
        ->and(Cache::store(GetGuestMenuForBranchAction::cacheStore())->has($cacheKey))->toBeFalse();

    Livewire::actingAs($manager)
        ->test(MenuModifiers::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
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

test('manager can manage localized dish variants and portion sizes', function () {
    [$organization, $brand, $branch, $manager] = createMenuCrudBranch();
    grantMenuCrudPermissions($manager, $organization, [
        SystemPermission::ManageMenu,
        SystemPermission::ChangePrices,
        SystemPermission::ChangeAvailability,
    ]);
    $menu = Menu::factory()->for($branch)->create(['name' => 'Portion Menu']);
    $category = MenuCategory::factory()->for($menu)->create(['name' => 'Pizza']);
    $item = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->create(['name' => 'Margherita', 'price_cents' => 1100]);
    $parameters = [
        'organizationId' => $organization->id,
        'brandId' => $brand->id,
        'branchId' => $branch->id,
    ];

    Livewire::actingAs($manager)
        ->test(MenuVariants::class, $parameters)
        ->set('variantMenuId', (string) $menu->id)
        ->set('variantItemId', (string) $item->id)
        ->set('variantType', MenuItemVariantType::Portion->value)
        ->set('variantName', 'Large')
        ->set('variantPrice', '18.90')
        ->set('variantWeight', '650')
        ->set('variantIsDefault', true)
        ->set('variantTranslations.en', 'Large')
        ->set('variantTranslations.lt', 'Didelė')
        ->set('variantTranslations.ru', 'Большая')
        ->call('createVariant')
        ->assertHasNoErrors()
        ->assertSee('Large')
        ->assertSee('Didelė');

    $variant = MenuItemVariant::query()->where('menu_item_id', $item->id)->firstOrFail();

    expect($variant->type)->toBe(MenuItemVariantType::Portion)
        ->and($variant->price_cents)->toBe(1890)
        ->and($variant->is_default)->toBeTrue()
        ->and($variant->localizedName('lt'))->toBe('Didelė');

    Livewire::actingAs($manager)
        ->test(MenuVariants::class, $parameters)
        ->set('variantMenuId', (string) $menu->id)
        ->set('variantItemId', (string) $item->id)
        ->set('variantType', MenuItemVariantType::Portion->value)
        ->set('variantName', 'Large')
        ->set('variantPrice', '19.50')
        ->call('createVariant')
        ->assertHasErrors(['variantName']);

    expect(MenuItemVariant::query()->where('menu_item_id', $item->id)->count())->toBe(1);

    Livewire::actingAs($manager)
        ->test(MenuVariants::class, $parameters)
        ->call('startEditingVariant', $variant->id)
        ->set('editingVariantName', 'Family')
        ->set('editingVariantPrice', '24.50')
        ->set('editingVariantTranslations.lt', 'Šeimos')
        ->call('updateVariant')
        ->assertHasNoErrors()
        ->assertSee('Family')
        ->call('deleteVariant', $variant->id)
        ->assertHasNoErrors();

    expect($variant->fresh())->toBeNull();
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
            'price_cents' => 800,
            'is_available' => true,
        ]);

    Livewire::actingAs($manager)
        ->test(MenuCatalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->assertSet('canChangePrices', false)
        ->assertSet('canChangeAvailability', false)
        ->call('startEditingItem', $item->id)
        ->set('editingItemPrice', '99.99')
        ->set('editingItemIsAvailable', false)
        ->call('updateItem')
        ->assertHasNoErrors();

    $item->refresh();

    expect($item->price_cents)->toBe(800)
        ->and($item->is_available)->toBeTrue();

    Livewire::actingAs($manager)
        ->test(MenuCatalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->call('setItemAvailability', $item->id, false)
        ->assertForbidden();

    grantMenuCrudPermissions($manager, $organization, [
        SystemPermission::ChangePrices,
        SystemPermission::ChangeAvailability,
    ]);

    Livewire::actingAs($manager->fresh())
        ->test(MenuCatalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->assertSet('canChangePrices', true)
        ->assertSet('canChangeAvailability', true)
        ->call('startEditingItem', $item->id)
        ->set('editingItemPrice', '9.50')
        ->set('editingItemIsAvailable', false)
        ->call('updateItem')
        ->assertHasNoErrors()
        ->assertSee('Unavailable');

    $item->refresh();

    expect($item->price_cents)->toBe(950)
        ->and($item->is_available)->toBeFalse();
});

test('menu item action independently preserves restricted price and availability fields', function () {
    [$organization, , $branch, $manager] = createMenuCrudBranch();
    grantMenuCrudPermissions($manager, $organization, [SystemPermission::ManageMenu]);
    $menu = Menu::factory()->for($branch)->create();
    $category = MenuCategory::factory()->for($menu)->create();
    $item = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->create([
            'price_cents' => 800,
            'is_available' => true,
        ]);

    app(UpdateMenuItemAction::class)->handle(
        actor: $manager,
        branch: $branch,
        item: $item,
        menu: $menu,
        category: $category,
        kitchenDepartmentId: null,
        data: [
            'name' => 'Server-authorized item',
            'description' => null,
            'price' => '99.99',
            'weight' => null,
            'volume' => null,
            'calories' => null,
            'is_available' => false,
            'sort_order' => 0,
        ],
    );

    expect($item->refresh()->price_cents)->toBe(800)
        ->and($item->is_available)->toBeTrue();
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
            'price_cents' => 1700,
            'is_available' => true,
        ]);
    $stopListItem = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->create([
            'name' => 'Sold out steak',
            'price_cents' => 2200,
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
        ->test(MenuAvailability::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
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
        ->test(MenuAvailability::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
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
    $childCategory = MenuCategory::factory()->for($menu)->create([
        'parent_id' => $category->id,
        'name' => 'Nested cleanup category',
    ]);
    $nestedItem = MenuItem::factory()
        ->for($menu)
        ->for($childCategory, 'category')
        ->create([
            'name' => 'Nested cleanup dish',
            'image' => 'media/test/nested-cleanup.jpg',
        ]);

    Storage::disk('public')->put($firstItem->image, 'first');
    Storage::disk('public')->put($secondItem->image, 'second');
    Storage::disk('public')->put($nestedItem->image, 'nested');

    Livewire::actingAs($manager)
        ->test(MenuCatalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->call('deleteItem', $firstItem->id)
        ->assertHasNoErrors();

    expect(MenuItem::query()->whereKey($firstItem->id)->exists())->toBeFalse()
        ->and(MenuItem::withTrashed()->findOrFail($firstItem->id)->trashed())->toBeTrue();
    Storage::disk('public')->assertMissing($firstItem->image);

    Livewire::actingAs($manager)
        ->test(MenuCatalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->call('deleteCategory', $category->id)
        ->assertHasNoErrors();

    expect(MenuCategory::query()->whereKey($category->id)->exists())->toBeFalse()
        ->and(MenuItem::query()->whereKey($secondItem->id)->exists())->toBeFalse()
        ->and(MenuItem::query()->whereKey($nestedItem->id)->exists())->toBeFalse()
        ->and(MenuCategory::withTrashed()->findOrFail($category->id)->trashed())->toBeTrue()
        ->and(MenuItem::withTrashed()->findOrFail($secondItem->id)->trashed())->toBeTrue();
    Storage::disk('public')->assertMissing($secondItem->image);
    Storage::disk('public')->assertMissing($nestedItem->image);

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
        ->test(MenuCatalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
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
        ->test(MenuCatalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $otherBranch->id])
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
