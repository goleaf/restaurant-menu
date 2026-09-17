<?php

use App\Actions\Menus\GetGuestMenuForBranchAction;
use App\Actions\Menus\UpdateMenuItemAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Data\Menus\MenuItemData;
use App\Enums\AuditLogAction;
use App\Enums\MenuItemVariantType;
use App\Enums\MenuStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\Availability\Index as AvailabilityCenter;
use App\Livewire\Organizations\Brands\Branches\Index as BranchesIndex;
use App\Livewire\Organizations\Brands\Branches\Menu\Availability as MenuAvailability;
use App\Livewire\Organizations\Brands\Branches\Menu\Catalog as MenuCatalog;
use App\Livewire\Organizations\Brands\Branches\Menu\Dish;
use App\Livewire\Organizations\Brands\Branches\Menu\KitchenDepartments as MenuKitchenDepartments;
use App\Livewire\Organizations\Brands\Branches\Menu\Modifiers as MenuModifiers;
use App\Livewire\Organizations\Brands\Branches\Menu\Variants as MenuVariants;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemImage;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    ParallelTesting::resolveTokenUsing(fn (): string => 'menu-crud-'.getmypid());
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
        ->assertRedirect(route('organizations.brands.branches.availability.index', [$organization, $brand, $branch, 'section' => 'stoplist']));

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
        ->assertSet('editingCategoryForm.categoryIcon', 'bookmark');
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
        ->test(Dish::class, compact('organization', 'brand', 'branch'))
        ->set('editingItemForm.itemMenuId', (string) $foreignMenu->id)
        ->assertSet('editingItemForm.itemCategoryId', '');

    Livewire::actingAs($manager)
        ->test(MenuModifiers::class, $parameters)
        ->set('assignment.modifierItemMenuId', (string) $foreignMenu->id)
        ->assertSet('assignment.modifierItemId', '');
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
        ->set('menuForm.menuName', 'Dinner Menu')
        ->set('menuForm.menuTranslations.en', 'Dinner Menu')
        ->set('menuForm.menuTranslations.lt', 'Vakarienės meniu')
        ->set('menuForm.menuTranslations.ru', 'Меню ужина')
        ->set('menuForm.menuStatus', MenuStatus::Active->value)
        ->set('menuForm.menuSortOrder', 10)
        ->call('createMenu')
        ->assertHasNoErrors()
        ->assertSee('Dinner Menu');

    $menu = Menu::query()
        ->where('branch_id', $branch->id)
        ->where('name', 'Dinner Menu')
        ->firstOrFail();

    Livewire::actingAs($manager)
        ->test(MenuCatalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->set('categoryForm.categoryMenuId', (string) $menu->id)
        ->set('categoryForm.categoryName', 'Pizza')
        ->set('categoryForm.categoryTranslations.en.name', 'Pizza')
        ->set('categoryForm.categoryTranslations.lt.name', 'Pica')
        ->set('categoryForm.categoryTranslations.ru.name', 'Пицца')
        ->set('categoryForm.categoryDescription', 'Classic pizza selection')
        ->set('categoryForm.categoryIcon', 'cake')
        ->set('categoryForm.categorySortOrder', 20)
        ->call('createCategory')
        ->assertHasNoErrors()
        ->assertSee('Pizza');

    $category = MenuCategory::query()
        ->where('menu_id', $menu->id)
        ->where('name', 'Pizza')
        ->firstOrFail();

    Livewire::actingAs($manager)
        ->test(Dish::class, compact('organization', 'brand', 'branch'))
        ->set('editingItemForm.itemMenuId', (string) $menu->id)
        ->set('editingItemForm.itemCategoryId', (string) $category->id)
        ->set('editingItemForm.itemName', 'Margherita')
        ->set('editingItemForm.itemTranslations.en.name', 'Margherita')
        ->set('editingItemForm.itemTranslations.lt.name', 'Margarita')
        ->set('editingItemForm.itemTranslations.ru.name', 'Маргарита')
        ->set('editingItemForm.itemTranslations.en.description', 'Tomato, mozzarella, basil')
        ->set('editingItemForm.itemPrice', '12.50')
        ->set('editingItemForm.itemWeight', '450')
        ->set('editingItemForm.itemCalories', '720')
        ->set('editingItemForm.itemAllergens', ['gluten', 'milk'])
        ->set('editingItemForm.itemDietaryLabels', ['vegetarian'])
        ->set('editingItemForm.itemSortOrder', 30)
        ->set('editingItemForm.itemIsAvailable', true)
        ->call('saveItem')
        ->assertHasNoErrors()
        ->assertSet('editingItemForm.itemName', 'Margherita');

    $item = MenuItem::query()
        ->where('menu_id', $menu->id)
        ->where('category_id', $category->id)
        ->where('name', 'Margherita')
        ->firstOrFail();

    Livewire::actingAs($manager)
        ->test(Dish::class, compact('organization', 'brand', 'branch', 'item'))
        ->call('selectSection', 'photos')
        ->set('editingItemForm.itemTranslations.en.name', 'Dish')
        ->set('editingItemForm.itemTranslations.lt.name', 'Patiekalas')
        ->set('editingItemForm.itemTranslations.ru.name', 'Блюдо')
        ->set('itemImageUploads.'.$item->id, [UploadedFile::fake()->image('margherita.jpg')->size(512)])
        ->call('saveItemImages', $item->id)
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

    $imagePath = $item->image;

    Livewire::actingAs($manager)
        ->test(MenuCatalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->call('startEditingMenu', $menu->id)
        ->set('editingMenuForm.menuName', 'Evening Menu')
        ->set('editingMenuForm.menuStatus', MenuStatus::Archived->value)
        ->set('editingMenuForm.menuSortOrder', 40)
        ->call('updateMenu')
        ->assertHasNoErrors()
        ->call('startEditingCategory', $category->id)
        ->set('editingCategoryForm.categoryName', 'Italian Pizza')
        ->set('editingCategoryForm.categoryDescription', 'Updated pizza selection')
        ->set('editingCategoryForm.categoryIcon', 'cake')
        ->set('editingCategoryForm.categorySortOrder', 50)
        ->set('editingCategoryForm.categoryIsActive', false)
        ->call('updateCategory')
        ->assertHasNoErrors();

    Livewire::actingAs($manager)->test(Dish::class, compact('organization', 'brand', 'branch', 'item'))
        ->call('removeItemImage', $item->id, hash('sha256', $imagePath), (string) Str::uuid())
        ->assertHasNoErrors();

    expect($menu->refresh()->name)->toBe('Evening Menu')
        ->and($menu->status)->toBe(MenuStatus::Archived)
        ->and($menu->sort_order)->toBe(40)
        ->and($category->refresh()->name)->toBe('Italian Pizza')
        ->and($category->description)->toBe('Updated pizza selection')
        ->and($category->sort_order)->toBe(50)
        ->and($category->is_active)->toBeFalse()
        ->and($item->refresh()->image)->toBeNull();

    Storage::disk('public')->assertMissing($imagePath);
});

test('menu item image gallery uploads several images only inside dish editing', function () {
    Storage::fake('public');
    Storage::fake('local');
    config(['livewire.temporary_file_upload.disk' => 'local']);
    [$organization, $brand, $branch, $manager] = createMenuCrudBranch();
    grantMenuCrudPermissions($manager, $organization, [SystemPermission::ManageMenu]);
    $menu = Menu::factory()->for($branch)->create();
    $category = MenuCategory::factory()->for($menu)->create();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['name' => 'Gallery pasta']);
    $parameters = [
        'organizationId' => $organization->id,
        'brandId' => $brand->id,
        'branchId' => $branch->id,
    ];

    Livewire::actingAs($manager)
        ->test(Dish::class, compact('organization', 'brand', 'branch', 'item'))
        ->assertDontSee('id="item-images-'.$item->id.'"', false)
        ->call('selectSection', 'photos')
        ->assertSee('id="item-images-'.$item->id.'"', false)
        ->assertSee('multiple', false)
        ->set('itemImageUploads.'.$item->id, [
            UploadedFile::fake()->image('pasta-front.jpg')->size(100),
            UploadedFile::fake()->image('pasta-side.png')->size(100),
            UploadedFile::fake()->image('pasta-detail.webp')->size(100),
        ])
        ->call('saveItemImages', $item->id)
        ->assertHasNoErrors()
        ->assertSet('itemImageUploads', [])
        ->assertSee('wire:key="menu-item-'.$item->id.'-image-primary-'.$item->id.'"', false)
        ->assertSeeText(__('uploads.labels.primary_image'))
        ->assertSeeText(__('uploads.actions.make_primary'));

    expect($item->refresh()->image)->not->toBeNull()
        ->and($item->galleryImages()->pluck('sort_order')->all())->toBe([0, 1])
        ->and(Storage::disk('public')->allFiles())->toHaveCount(3);
});

test('menu item image gallery enforces the aggregate limit on the exact livewire field', function () {
    Storage::fake('public');
    Storage::fake('local');
    config(['livewire.temporary_file_upload.disk' => 'local']);
    [$organization, $brand, $branch, $manager] = createMenuCrudBranch();
    grantMenuCrudPermissions($manager, $organization, [SystemPermission::ManageMenu]);
    $menu = Menu::factory()->for($branch)->create();
    $category = MenuCategory::factory()->for($menu)->create();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create([
        'image' => 'media/existing/primary.jpg',
    ]);
    Storage::disk('public')->put($item->image, 'primary');

    foreach (range(0, 6) as $sortOrder) {
        $path = 'media/existing/gallery-'.$sortOrder.'.jpg';
        MenuItemImage::factory()->for($item, 'item')->create(['path' => $path, 'sort_order' => $sortOrder]);
        Storage::disk('public')->put($path, 'gallery');
    }

    Livewire::actingAs($manager)
        ->test(Dish::class, compact('organization', 'brand', 'branch', 'item'))
        ->call('selectSection', 'photos')
        ->set('itemImageUploads.'.$item->id, [UploadedFile::fake()->image('ninth.jpg')->size(100)])
        ->call('saveItemImages', $item->id)
        ->assertHasErrors(['itemImageUploads.'.$item->id]);

    expect($item->galleryImages()->count())->toBe(7)
        ->and(Storage::disk('public')->allFiles())->toHaveCount(MenuItem::MAX_IMAGES);
});

test('menu item image gallery livewire actions promote and remove owned images', function () {
    Storage::fake('public');
    [$organization, $brand, $branch, $manager] = createMenuCrudBranch();
    grantMenuCrudPermissions($manager, $organization, [SystemPermission::ManageMenu]);
    $menu = Menu::factory()->for($branch)->create();
    $category = MenuCategory::factory()->for($menu)->create();
    $primaryPath = 'media/existing/primary.jpg';
    $promotedPath = 'media/existing/promoted.jpg';
    $removedPath = 'media/existing/removed.jpg';
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['image' => $primaryPath]);
    $promoted = MenuItemImage::factory()->for($item, 'item')->create(['path' => $promotedPath, 'sort_order' => 0]);
    $removed = MenuItemImage::factory()->for($item, 'item')->create(['path' => $removedPath, 'sort_order' => 1]);

    foreach ([$primaryPath, $promotedPath, $removedPath] as $path) {
        Storage::disk('public')->put($path, $path);
    }

    $component = Livewire::actingAs($manager)
        ->test(Dish::class, compact('organization', 'brand', 'branch', 'item'))
        ->call('selectSection', 'photos')
        ->call('promoteItemImage', $item->id, $promoted->id, hash('sha256', $promotedPath), (string) Str::uuid())
        ->assertHasNoErrors();

    expect($item->refresh()->image)->toBe($promotedPath)
        ->and($promoted->refresh()->path)->toBe($primaryPath);

    $component
        ->call('removeItemGalleryImage', $item->id, $removed->id, hash('sha256', $removedPath), (string) Str::uuid())
        ->assertHasNoErrors()
        ->assertDispatched('modal-close')
        ->call('removeItemImage', $item->id, hash('sha256', $promotedPath), (string) Str::uuid())
        ->assertHasNoErrors()
        ->assertDispatched('modal-close');

    expect($item->refresh()->image)->toBe($primaryPath)
        ->and($item->galleryImages()->exists())->toBeFalse();
    Storage::disk('public')->assertMissing([$promotedPath, $removedPath]);
    Storage::disk('public')->assertExists($primaryPath);
});

test('menu item image gallery rejects tampered branch records without storing files', function () {
    Storage::fake('public');
    Storage::fake('local');
    config(['livewire.temporary_file_upload.disk' => 'local']);
    [$organization, $brand, $branch, $manager] = createMenuCrudBranch();
    grantMenuCrudPermissions($manager, $organization, [SystemPermission::ManageMenu]);
    [, , $foreignBranch] = createMenuCrudBranch('Foreign Restaurant');
    $foreignMenu = Menu::factory()->for($foreignBranch)->create();
    $foreignCategory = MenuCategory::factory()->for($foreignMenu)->create();
    $foreignItem = MenuItem::factory()->for($foreignMenu)->for($foreignCategory, 'category')->create();
    $component = Livewire::actingAs($manager)->test(Dish::class, compact('organization', 'brand', 'branch'));

    $component
        ->set('itemImageUploads.'.$foreignItem->id, [UploadedFile::fake()->image('foreign.jpg')->size(100)])
        ->assertForbidden();

    Livewire::actingAs($manager)->test(Dish::class, compact('organization', 'brand', 'branch'))->call('saveItemImages', $foreignItem->id)->assertForbidden();

    expect($foreignItem->refresh()->image)->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('menu catalogue does not load galleries before a dish editor is opened', function () {
    [$organization, $brand, $branch, $manager] = createMenuCrudBranch();
    grantMenuCrudPermissions($manager, $organization, [SystemPermission::ManageMenu]);
    $menu = Menu::factory()->for($branch)->create();
    $category = MenuCategory::factory()->for($menu)->create();
    $items = MenuItem::factory()->count(3)->for($menu)->for($category, 'category')->create();

    foreach ($items as $item) {
        MenuItemImage::factory()->count(2)->for($item, 'item')->sequence(
            ['sort_order' => 0],
            ['sort_order' => 1],
        )->create();
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::actingAs($manager)->test(MenuCatalog::class, [
        'organizationId' => $organization->id,
        'brandId' => $brand->id,
        'branchId' => $branch->id,
    ])->assertOk();

    $galleryQueries = collect(DB::getQueryLog())->filter(
        fn (array $query): bool => str_contains($query['query'], 'menu_item_images'),
    );
    DB::disableQueryLog();

    expect($galleryQueries)->toHaveCount(0);
});

test('menu item image gallery translations keep en lt and ru placeholders aligned', function () {
    $keys = [
        'uploads.actions.make_primary',
        'uploads.errors.maximum_images',
        'uploads.labels.image_count',
        'uploads.labels.image_position',
        'uploads.labels.multiple_images',
        'uploads.labels.primary_image',
        'uploads.labels.up_to_images',
        'uploads.messages.images_uploaded',
        'uploads.messages.primary_changed',
    ];
    $translations = collect(['en', 'lt', 'ru'])->mapWithKeys(function (string $locale): array {
        $values = json_decode(File::get(lang_path($locale.'.json')), true, 512, JSON_THROW_ON_ERROR);

        return [$locale => $values];
    });

    foreach ($keys as $key) {
        $placeholderSets = $translations->map(function (array $values) use ($key): array {
            expect($values)->toHaveKey($key);
            preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', (string) $values[$key], $matches);

            return array_values(array_unique($matches[0]));
        })->values();

        expect($placeholderSets->unique()->count())->toBe(1, $key.' placeholders must match.');
    }
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
        ->test(Dish::class, compact('organization', 'brand', 'branch', 'item'))
        ->assertSeeText('Gluten-containing cereals')
        ->assertSeeText('Dietary labels')
        ->call('selectSection', 'photos')
        ->set('editingItemForm.itemTranslations.en.name', 'Dish')
        ->set('editingItemForm.itemTranslations.lt.name', 'Patiekalas')
        ->set('editingItemForm.itemTranslations.ru.name', 'Блюдо')
        ->assertSet('editingItemForm.itemAllergens', ['eggs'])
        ->assertSet('editingItemForm.itemDietaryLabels', ['vegetarian'])
        ->set('editingItemForm.itemAllergens', ['unknown-allergen'])
        ->set('editingItemForm.itemDietaryLabels', ['unknown-diet'])
        ->call('saveItem')
        ->assertHasErrors(['editingItemForm.itemAllergens.0', 'editingItemForm.itemDietaryLabels.0']);

    $component
        ->set('editingItemForm.itemAllergens', ['milk', 'gluten'])
        ->set('editingItemForm.itemDietaryLabels', ['vegan', 'vegetarian'])
        ->call('saveItem')
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
        ->set('group.modifierGroupName', 'Pizza size')
        ->set('group.modifierGroupTranslations.en', 'Pizza size')
        ->set('group.modifierGroupTranslations.lt', 'Picos dydis')
        ->set('group.modifierGroupTranslations.ru', 'Размер пиццы')
        ->set('group.modifierGroupIsRequired', true)
        ->set('group.modifierGroupMinSelect', 1)
        ->set('group.modifierGroupMaxSelect', 1)
        ->set('group.modifierGroupSortOrder', 10)
        ->call('createModifierGroup')
        ->assertHasNoErrors()
        ->assertSee('Pizza size');

    $group = ModifierGroup::query()
        ->where('branch_id', $branch->id)
        ->where('name', 'Pizza size')
        ->firstOrFail();

    expect(Cache::store(GetGuestMenuForBranchAction::cacheStore())->has($cacheKey))->toBeFalse();

    expect($group->translations()->orderBy('language_code')->pluck('name', 'language_code')->all())->toBe([
        'en' => 'Pizza size',
        'lt' => 'Picos dydis',
        'ru' => 'Размер пиццы',
    ]);

    app(GetGuestMenuForBranchAction::class)->handle($branch->id, 'en');

    Livewire::actingAs($manager)
        ->test(MenuModifiers::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->set('option.modifierOptionGroupId', (string) $group->id)
        ->set('option.modifierOptionName', 'Large')
        ->set('option.modifierOptionTranslations.en', 'Large')
        ->set('option.modifierOptionTranslations.lt', 'Didelė')
        ->set('option.modifierOptionTranslations.ru', 'Большая')
        ->set('option.modifierOptionPriceDelta', '3.50')
        ->set('option.modifierOptionIsAvailable', true)
        ->set('option.modifierOptionSortOrder', 20)
        ->call('createModifierOption')
        ->assertHasNoErrors()
        ->assertSee('Large');

    $option = ModifierOption::query()
        ->where('modifier_group_id', $group->id)
        ->where('name', 'Large')
        ->firstOrFail();

    expect($option->price_delta_cents)->toBe(350)
        ->and($option->translations()->orderBy('language_code')->pluck('name', 'language_code')->all())->toBe([
            'en' => 'Large',
            'lt' => 'Didelė',
            'ru' => 'Большая',
        ])
        ->and(Cache::store(GetGuestMenuForBranchAction::cacheStore())->has($cacheKey))->toBeFalse();

    app(GetGuestMenuForBranchAction::class)->handle($branch->id, 'en');

    Livewire::actingAs($manager)
        ->test(MenuModifiers::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->set('assignment.modifierItemMenuId', (string) $menu->id)
        ->set('assignment.modifierItemId', (string) $item->id)
        ->set('assignment.modifierItemGroupId', (string) $group->id)
        ->call('attachModifierGroupToItem')
        ->assertHasNoErrors()
        ->assertSee('Pizza size');

    expect($item->modifierGroups()->pluck('modifier_groups.id')->all())->toBe([$group->id])
        ->and(Cache::store(GetGuestMenuForBranchAction::cacheStore())->has($cacheKey))->toBeFalse();

    Livewire::actingAs($manager)
        ->test(MenuModifiers::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->call('refreshData')
        ->call('startEditingModifierGroup', $group->id)
        ->set('editingGroup.modifierGroupName', 'Choose size')
        ->set('editingGroup.modifierGroupMinSelect', 1)
        ->set('editingGroup.modifierGroupMaxSelect', 2)
        ->call('updateModifierGroup')
        ->assertHasNoErrors()
        ->call('startEditingModifierOption', $option->id)
        ->set('editingOption.modifierOptionName', 'Extra large')
        ->set('editingOption.modifierOptionPriceDelta', '5.00')
        ->set('editingOption.modifierOptionIsAvailable', false)
        ->call('updateModifierOption')
        ->assertHasNoErrors();

    Livewire::actingAs($manager)->test(MenuModifiers::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id, 'itemId' => $item->id])
        ->call('detachModifierGroupFromItem', $item->id, $group->id)
        ->assertHasNoErrors()->assertStatus(200);

    Livewire::actingAs($manager)->test(MenuModifiers::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->call('deleteModifierOption', $option->id)
        ->assertHasNoErrors()->assertStatus(200)
        ->call('deleteModifierGroup', $group->id)
        ->assertHasNoErrors()->assertStatus(200);

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
        ->set('variant.variantMenuId', (string) $menu->id)
        ->set('variant.variantItemId', (string) $item->id)
        ->call('refreshData')
        ->set('variant.variantType', MenuItemVariantType::Portion->value)
        ->set('variant.variantName', 'Large')
        ->set('variant.variantPrice', '18.90')
        ->set('variant.variantWeight', '650')
        ->set('variant.variantIsDefault', true)
        ->set('variant.variantTranslations.en', 'Large')
        ->set('variant.variantTranslations.lt', 'Didelė')
        ->set('variant.variantTranslations.ru', 'Большая')
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
        ->set('variant.variantMenuId', (string) $menu->id)
        ->set('variant.variantItemId', (string) $item->id)
        ->set('variant.variantType', MenuItemVariantType::Portion->value)
        ->set('variant.variantName', 'Large')
        ->set('variant.variantPrice', '19.50')
        ->call('createVariant')
        ->assertHasErrors(['variant.variantName']);

    expect(MenuItemVariant::query()->where('menu_item_id', $item->id)->count())->toBe(1);

    Livewire::actingAs($manager)
        ->test(MenuVariants::class, $parameters)
        ->call('startEditingVariant', $variant->id)
        ->set('editingVariant.variantName', 'Family')
        ->set('editingVariant.variantPrice', '24.50')
        ->set('editingVariant.variantTranslations.lt', 'Šeimos')
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
        ->test(Dish::class, compact('organization', 'brand', 'branch', 'item'))
        ->assertSet('canChangePrices', false)
        ->assertSet('canChangeAvailability', false)
        ->call('selectSection', 'photos')
        ->set('editingItemForm.itemTranslations.en.name', 'Soup')
        ->set('editingItemForm.itemTranslations.lt.name', 'Sriuba')
        ->set('editingItemForm.itemTranslations.ru.name', 'Суп')
        ->set('editingItemForm.itemPrice', '99.99')
        ->set('editingItemForm.itemIsAvailable', false)
        ->set('editingItemForm.itemHiddenUntil', now($branch->timezone)->addHours(2)->format('Y-m-d\TH:i'))
        ->call('saveItem')
        ->assertHasNoErrors();

    $item->refresh();

    expect($item->price_cents)->toBe(800)
        ->and($item->is_available)->toBeTrue()
        ->and($item->hidden_until)->toBeNull();

    Livewire::actingAs($manager)
        ->test(AvailabilityCenter::class, compact('organization', 'brand', 'branch'))
        ->call('openItem', $item->id)
        ->assertForbidden();

    grantMenuCrudPermissions($manager, $organization, [
        SystemPermission::ChangePrices,
        SystemPermission::ChangeAvailability,
    ]);
    $hiddenUntil = now($branch->timezone)->addHours(2)->seconds(0)->format('Y-m-d\TH:i');

    Livewire::actingAs($manager->fresh())
        ->test(Dish::class, compact('organization', 'brand', 'branch', 'item'))
        ->assertSet('canChangePrices', true)
        ->assertSet('canChangeAvailability', true)
        ->call('selectSection', 'photos')
        ->set('editingItemForm.itemTranslations.en.name', 'Soup')
        ->set('editingItemForm.itemTranslations.lt.name', 'Sriuba')
        ->set('editingItemForm.itemTranslations.ru.name', 'Суп')
        ->set('editingItemForm.itemPrice', '9.50')
        ->set('editingItemForm.itemIsAvailable', false)
        ->set('editingItemForm.itemHiddenUntil', $hiddenUntil)
        ->call('saveItem')
        ->assertHasNoErrors();

    $item->refresh();

    expect($item->price_cents)->toBe(950)
        ->and($item->is_available)->toBeTrue()->and($item->hidden_until)->toBeNull();

    $center = Livewire::actingAs($manager->fresh())->withQueryParams(['section' => 'stoplist'])
        ->test(AvailabilityCenter::class, compact('organization', 'brand', 'branch'));
    $center->call('openItem', $item->id)->set('restriction.operation', 'stop')->call('previewRestriction')->call('applyRestriction')->assertHasNoErrors();
    $center->call('openItem', $item->id)->set('restriction.operation', 'hide')
        ->set('restriction.untilDate', substr($hiddenUntil, 0, 10))->set('restriction.untilTime', substr($hiddenUntil, 11, 5))
        ->call('previewRestriction')->call('applyRestriction')->assertHasNoErrors();
    expect($item->fresh()->is_available)->toBeFalse()
        ->and($item->fresh()->hidden_until?->setTimezone($branch->timezone)->format('Y-m-d\TH:i'))->toBe($hiddenUntil);
});

test('menu price validation rejects incomplete decimals before saving and permits correction', function (string $price): void {
    [$organization, $brand, $branch, $manager] = createMenuCrudBranch();
    grantMenuCrudPermissions($manager, $organization, [SystemPermission::ManageMenu, SystemPermission::ChangePrices]);
    $menu = Menu::factory()->for($branch)->create();
    $category = MenuCategory::factory()->for($menu)->create();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['price_cents' => 800]);

    $component = Livewire::actingAs($manager)
        ->test(Dish::class, compact('organization', 'brand', 'branch', 'item'))
        ->call('selectSection', 'photos')
        ->set('editingItemForm.itemTranslations.en.name', 'Soup')
        ->set('editingItemForm.itemTranslations.lt.name', 'Sriuba')
        ->set('editingItemForm.itemTranslations.ru.name', 'Суп')
        ->set('editingItemForm.itemPrice', $price)
        ->call('saveItem')
        ->assertHasErrors('editingItemForm.itemPrice');

    expect($item->refresh()->price_cents)->toBe(800);

    $component->set('editingItemForm.itemPrice', '0.29')
        ->call('saveItem')
        ->assertHasNoErrors();

    expect($item->refresh()->price_cents)->toBe(29);
})->with(['.50', '1.']);

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
        data: MenuItemData::fromValidated([
            'name' => 'Server-authorized item',
            'description' => null,
            'price' => '99.99',
            'weight' => null,
            'volume' => null,
            'calories' => null,
            'is_available' => false,
            'sort_order' => 0,
        ]),
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
        ->assertRedirect(route('organizations.brands.branches.availability.index', [$organization, $brand, $branch, 'section' => 'stoplist']));
    Livewire::actingAs($headChef->fresh())->withQueryParams(['section' => 'stoplist'])
        ->test(AvailabilityCenter::class, compact('organization', 'brand', 'branch'))
        ->assertSee('Sold out steak')->assertSee('Grilled fish')->assertDontSee('New dish')
        ->call('openItem', $availableItem->id)->set('restriction.operation', 'stop')
        ->call('previewRestriction')->call('applyRestriction')->assertHasNoErrors();

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
        ->withQueryParams(['section' => 'stoplist'])->test(AvailabilityCenter::class, compact('organization', 'brand', 'branch'))
        ->call('openItem', $availableItem->id)->set('restriction.operation', 'resume')->call('previewRestriction')->call('applyRestriction')
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
    $firstGalleryPaths = createStoredMenuItemGallery($firstItem, 'first');
    $secondGalleryPaths = createStoredMenuItemGallery($secondItem, 'second');
    $nestedGalleryPaths = createStoredMenuItemGallery($nestedItem, 'nested');

    Livewire::actingAs($manager)
        ->test(MenuCatalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->call('deleteItem', $firstItem->id)
        ->assertHasNoErrors();

    expect(MenuItem::query()->whereKey($firstItem->id)->exists())->toBeFalse()
        ->and(MenuItem::withTrashed()->findOrFail($firstItem->id)->trashed())->toBeTrue()
        ->and(MenuItemImage::query()->where('menu_item_id', $firstItem->id)->exists())->toBeFalse();
    Storage::disk('public')->assertMissing([$firstItem->image, ...$firstGalleryPaths]);

    $categoryDeletion = Livewire::actingAs($manager)
        ->test(MenuCatalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->call('deleteCategory', $category->id)
        ->assertHasNoErrors();

    finishMenuCrudOperation($categoryDeletion);

    expect(MenuCategory::query()->whereKey($category->id)->exists())->toBeFalse()
        ->and(MenuItem::query()->whereKey($secondItem->id)->exists())->toBeFalse()
        ->and(MenuItem::query()->whereKey($nestedItem->id)->exists())->toBeFalse()
        ->and(MenuCategory::withTrashed()->findOrFail($category->id)->trashed())->toBeTrue()
        ->and(MenuItem::withTrashed()->findOrFail($secondItem->id)->trashed())->toBeTrue()
        ->and(MenuItemImage::query()->whereIn('menu_item_id', [$secondItem->id, $nestedItem->id])->exists())->toBeFalse();
    Storage::disk('public')->assertMissing([
        $secondItem->image,
        $nestedItem->image,
        ...$secondGalleryPaths,
        ...$nestedGalleryPaths,
    ]);

    $remainingCategory = MenuCategory::factory()->for($menu)->create(['name' => 'Remaining Category']);
    $remainingItem = MenuItem::factory()
        ->for($menu)
        ->for($remainingCategory, 'category')
        ->create([
            'name' => 'Remaining cleanup dish',
            'image' => 'media/test/remaining-cleanup.jpg',
        ]);
    Storage::disk('public')->put($remainingItem->image, 'remaining');
    $remainingGalleryPaths = createStoredMenuItemGallery($remainingItem, 'remaining');

    $menuDeletion = Livewire::actingAs($manager)
        ->test(MenuCatalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->call('deleteMenu', $menu->id)
        ->assertHasNoErrors();

    finishMenuCrudOperation($menuDeletion);

    expect(Menu::query()->whereKey($menu->id)->exists())->toBeFalse()
        ->and(MenuCategory::query()->whereKey($remainingCategory->id)->exists())->toBeFalse()
        ->and(MenuItem::query()->whereKey($remainingItem->id)->exists())->toBeFalse()
        ->and(Menu::withTrashed()->findOrFail($menu->id)->trashed())->toBeTrue()
        ->and(MenuCategory::withTrashed()->findOrFail($remainingCategory->id)->trashed())->toBeTrue()
        ->and(MenuItem::withTrashed()->findOrFail($remainingItem->id)->trashed())->toBeTrue()
        ->and(MenuItemImage::query()->where('menu_item_id', $remainingItem->id)->exists())->toBeFalse();
    Storage::disk('public')->assertMissing([$remainingItem->image, ...$remainingGalleryPaths]);
});

test('branch must belong to route brand and organization on menu page', function () {
    [$organization, $brand, , $manager] = createMenuCrudBranch();
    grantMenuCrudPermissions($manager, $organization, [SystemPermission::ManageMenu]);
    [, , $otherBranch] = createMenuCrudBranch('Other Menu Group', 'Other Menu Brand');

    Livewire::actingAs($manager)
        ->test(MenuCatalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $otherBranch->id])
        ->assertForbidden();
});

function finishMenuCrudOperation(Testable $component): void
{
    for ($step = 0; $step < 30 && $component->get('activeCatalogOperationId') !== ''; $step++) {
        $component->call('advanceCatalogOperation')->assertHasNoErrors();
    }

    $component->assertSet('activeCatalogOperationId', '');
}

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

/** @return list<string> */
function createStoredMenuItemGallery(MenuItem $item, string $prefix): array
{
    $paths = [];

    foreach (range(0, 1) as $sortOrder) {
        $path = 'media/test/'.$prefix.'-gallery-'.$sortOrder.'.jpg';
        MenuItemImage::factory()->for($item, 'item')->create([
            'path' => $path,
            'sort_order' => $sortOrder,
        ]);
        Storage::disk('public')->put($path, $path);
        $paths[] = $path;
    }

    return $paths;
}
