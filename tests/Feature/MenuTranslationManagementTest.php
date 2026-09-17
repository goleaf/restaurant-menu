<?php

use App\Actions\Menus\GetGuestMenuForBranchAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\MenuStatus;
use App\Livewire\Organizations\Brands\Branches\Menu\Catalog as MenuCatalog;
use App\Livewire\Organizations\Brands\Branches\Menu\Dish;
use App\Livewire\Organizations\Brands\Branches\Menu\Modifiers as MenuModifiers;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuCategoryTranslation;
use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use App\Models\MenuItemVariant;
use App\Models\MenuTranslation;
use App\Models\ModifierGroup;
use App\Models\ModifierGroupTranslation;
use App\Models\ModifierOption;
use App\Models\ModifierOptionTranslation;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('translation editor presents a source reference and safe copy controls for secondary languages', function (): void {
    [$owner, $organization, $brand, $branch] = createMenuTranslationContext();

    Livewire::actingAs($owner)
        ->test(MenuCatalog::class, menuTranslationComponentParameters($organization->id, $brand->id, $branch->id))
        ->assertSeeHtml('data-original-reference')
        ->assertSeeHtml('data-copy-original="lt"')
        ->assertSeeHtml('data-copy-original="ru"')
        ->assertDontSeeHtml('data-copy-original="en"')
        ->assertSeeHtml('data-copy-notice="lt"');
});

test('menu base name conflicts are visible in the primary translation panel without losing localized input', function (bool $editing): void {
    [$owner, $organization, $brand, $branch, $existingMenu] = createMenuTranslationContext();
    $component = Livewire::actingAs($owner)->test(MenuCatalog::class,
        menuTranslationComponentParameters($organization->id, $brand->id, $branch->id));
    $nameField = $editing ? 'editingMenuForm.menuName' : 'menuForm.menuName';
    $translationsField = $editing ? 'editingMenuForm.menuTranslations' : 'menuForm.menuTranslations';
    $translations = ['en' => $existingMenu->name, 'lt' => 'Neišsaugotas meniu', 'ru' => 'Несохранённое меню'];
    $editedMenu = null;

    if ($editing) {
        $editedMenu = Menu::factory()->for($branch)->create(['name' => 'Separate menu']);
        $component->call('startEditingMenu', $editedMenu->id);
    }

    $component->set($nameField, $existingMenu->name)
        ->set($translationsField, $translations)
        ->call($editing ? 'updateMenu' : 'createMenu')
        ->assertHasErrors([$nameField => 'unique'])
        ->assertSet($translationsField, $translations);

    $error = $component->instance()->getErrorBag()->first($nameField);
    expect($error)->not->toBe('');
    $component->assertSee($error);
    expect(preg_match('/data-locale-panel="en" data-invalid="true"/', $component->html()))->toBe(1);
    expect(Menu::query()->where('branch_id', $branch->id)->count())->toBe($editing ? 2 : 1);

    if ($editedMenu !== null) {
        expect($editedMenu->fresh()->name)->toBe('Separate menu')
            ->and($editedMenu->translations()->count())->toBe(0);
    }
})->with(['create' => false, 'rename' => true]);

test('manager creates category and dish translations for every supported locale', function () {
    [$owner, $organization, $brand, $branch, $menu] = createMenuTranslationContext();
    $parameters = menuTranslationComponentParameters($organization->id, $brand->id, $branch->id);

    Livewire::actingAs($owner)
        ->test(MenuCatalog::class, $parameters)
        ->set('categoryForm.categoryMenuId', (string) $menu->id)
        ->set('categoryForm.categoryName', 'Starters')
        ->set('categoryForm.categoryTranslations.en.name', 'Starters')
        ->set('categoryForm.categoryTranslations.en.description', 'Small plates')
        ->set('categoryForm.categoryTranslations.lt.name', 'Užkandžiai')
        ->set('categoryForm.categoryTranslations.lt.description', 'Maži patiekalai')
        ->set('categoryForm.categoryTranslations.ru.name', 'Закуски')
        ->set('categoryForm.categoryTranslations.ru.description', 'Небольшие блюда')
        ->call('createCategory')
        ->assertHasNoErrors();

    $category = MenuCategory::query()->where('menu_id', $menu->id)->where('name', 'Starters')->firstOrFail();

    Livewire::actingAs($owner)
        ->test(Dish::class, compact('organization', 'brand', 'branch'))
        ->set('editingItemForm.itemMenuId', (string) $menu->id)
        ->set('editingItemForm.itemCategoryId', (string) $category->id)
        ->set('editingItemForm.itemName', 'Cold beet soup')
        ->set('editingItemForm.itemTranslations.en.name', 'Cold beet soup')
        ->set('editingItemForm.itemTranslations.en.description', 'With herbs')
        ->set('editingItemForm.itemTranslations.lt.name', 'Šaltibarščiai')
        ->set('editingItemForm.itemTranslations.lt.description', 'Su žalumynais')
        ->set('editingItemForm.itemTranslations.ru.name', 'Холодный свекольный суп')
        ->set('editingItemForm.itemTranslations.ru.description', 'С зеленью')
        ->call('saveItem')
        ->assertHasNoErrors();

    $item = MenuItem::query()->where('menu_id', $menu->id)->where('name', 'Cold beet soup')->firstOrFail();

    expect($category->translations()->orderBy('language_code')->pluck('name', 'language_code')->all())->toBe([
        'en' => 'Starters',
        'lt' => 'Užkandžiai',
        'ru' => 'Закуски',
    ])->and($item->translations()->orderBy('language_code')->pluck('name', 'language_code')->all())->toBe([
        'en' => 'Cold beet soup',
        'lt' => 'Šaltibarščiai',
        'ru' => 'Холодный свекольный суп',
    ]);
});

test('manager reads and updates required translations and invalidates the guest cache', function () {
    [$owner, $organization, $brand, $branch, $menu] = createMenuTranslationContext();
    $category = MenuCategory::factory()->for($menu)->create(['name' => 'Base category']);
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create([
        'name' => 'Base dish',
        'description' => 'Base description',
    ]);

    foreach (['en' => 'English dish', 'lt' => 'Lietuviškas patiekalas', 'ru' => 'Русское блюдо'] as $locale => $name) {
        MenuCategoryTranslation::factory()->for($category, 'category')->create([
            'language_code' => $locale,
            'name' => $locale.' category',
        ]);
        MenuItemTranslation::factory()->for($item, 'item')->create([
            'language_code' => $locale,
            'name' => $name,
            'description' => $locale.' description',
        ]);
    }

    $cacheKey = GetGuestMenuForBranchAction::cacheKey($branch->id, 'lt');
    app(GetGuestMenuForBranchAction::class)->handle($branch->id, 'lt');
    expect(Cache::store(GetGuestMenuForBranchAction::cacheStore())->has($cacheKey))->toBeTrue();

    Livewire::actingAs($owner)
        ->test(MenuCatalog::class, menuTranslationComponentParameters($organization->id, $brand->id, $branch->id))
        ->call('startEditingCategory', $category->id)
        ->assertSet('editingCategoryForm.categoryTranslations.lt.name', 'lt category')
        ->set('editingCategoryForm.categoryTranslations.lt.name', 'Atnaujinta kategorija')
        ->call('updateCategory')
        ->assertHasNoErrors();

    Livewire::actingAs($owner)->test(Dish::class, compact('organization', 'brand', 'branch', 'item'))
        ->assertSet('editingItemForm.itemTranslations.lt.name', 'Lietuviškas patiekalas')
        ->set('editingItemForm.itemTranslations.lt.name', 'Atnaujintas patiekalas')
        ->set('editingItemForm.itemTranslations.lt.description', "Pirma pastraipa.\n\nAntra pastraipa.")
        ->set('editingItemForm.itemTranslations.ru.name', 'Обновлённое блюдо')
        ->set('editingItemForm.itemTranslations.ru.description', '')
        ->call('saveItem')
        ->assertHasNoErrors()
        ->assertSee($category->name)
        ->assertSet('editingItemForm.itemTranslations.lt.name', 'Atnaujintas patiekalas');

    expect($category->translations()->where('language_code', 'lt')->value('name'))->toBe('Atnaujinta kategorija')
        ->and($item->translations()->where('language_code', 'lt')->value('name'))->toBe('Atnaujintas patiekalas')
        ->and($item->translations()->where('language_code', 'ru')->value('name'))->toBe('Обновлённое блюдо')
        ->and($item->translations()->where('language_code', 'lt')->value('description'))->toBe("Pirma pastraipa.\n\nAntra pastraipa.")
        ->and($item->translations()->where('language_code', 'ru')->value('description'))->toBeNull()
        ->and(Cache::store(GetGuestMenuForBranchAction::cacheStore())->has($cacheKey))->toBeFalse()
        ->and(app(GetGuestMenuForBranchAction::class)->handle($branch->id, 'ru')['categories'][0]['items'][0]['name'])->toBe('Обновлённое блюдо')
        ->and(app(GetGuestMenuForBranchAction::class)->handle($branch->id, 'ru')['categories'][0]['items'][0]['description'])->toBeNull();
});

test('translation description requires a locale name and errors stay associated with the field', function () {
    [$owner, $organization, $brand, $branch, $menu] = createMenuTranslationContext();

    Livewire::actingAs($owner)
        ->test(MenuCatalog::class, menuTranslationComponentParameters($organization->id, $brand->id, $branch->id))
        ->set('categoryForm.categoryMenuId', (string) $menu->id)
        ->set('categoryForm.categoryName', 'Desserts')
        ->set('categoryForm.categoryTranslations.lt.name', '')
        ->set('categoryForm.categoryTranslations.lt.description', 'Saldūs patiekalai')
        ->call('createCategory')
        ->assertHasErrors(['categoryForm.categoryTranslations.lt.name' => 'required']);

    expect(MenuCategory::query()->where('menu_id', $menu->id)->where('name', 'Desserts')->exists())->toBeFalse();
});

test('menu editor requires a name in every supported guest locale', function () {
    [$owner, $organization, $brand, $branch] = createMenuTranslationContext();

    Livewire::actingAs($owner)
        ->test(MenuCatalog::class, menuTranslationComponentParameters($organization->id, $brand->id, $branch->id))
        ->set('menuForm.menuName', 'Lunch')
        ->set('menuForm.menuTranslations.en', 'Lunch')
        ->set('menuForm.menuTranslations.lt', 'Pietūs')
        ->set('menuForm.menuTranslations.ru', '')
        ->call('createMenu')
        ->assertHasErrors(['menuForm.menuTranslations.ru' => 'required']);

    expect(Menu::query()->where('branch_id', $branch->id)->where('name', 'Lunch')->exists())->toBeFalse();
});

test('menu administration rejects duplicate names inside their owning scope', function () {
    [$owner, $organization, $brand, $branch, $menu] = createMenuTranslationContext();
    $category = MenuCategory::factory()->for($menu)->create(['name' => 'Starters']);
    MenuItem::factory()->for($menu)->for($category, 'category')->create(['name' => 'Soup']);
    $group = ModifierGroup::factory()->for($branch)->create(['name' => 'Extras']);
    ModifierOption::factory()->for($group, 'modifierGroup')->create(['name' => 'Cheese']);
    $parameters = menuTranslationComponentParameters($organization->id, $brand->id, $branch->id);

    Livewire::actingAs($owner)
        ->test(MenuCatalog::class, $parameters)
        ->set('menuForm.menuName', $menu->name)
        ->set('menuForm.menuTranslations.en', 'Menu')
        ->set('menuForm.menuTranslations.lt', 'Meniu')
        ->set('menuForm.menuTranslations.ru', 'Меню')
        ->call('createMenu')
        ->assertHasErrors(['menuForm.menuName' => 'unique'])
        ->set('categoryForm.categoryMenuId', (string) $menu->id)
        ->set('categoryForm.categoryName', 'Starters')
        ->set('categoryForm.categoryTranslations.en.name', 'Starters')
        ->set('categoryForm.categoryTranslations.lt.name', 'Užkandžiai')
        ->set('categoryForm.categoryTranslations.ru.name', 'Закуски')
        ->call('createCategory')
        ->assertHasErrors(['categoryForm.categoryName' => 'unique']);

    Livewire::actingAs($owner)->test(Dish::class, compact('organization', 'brand', 'branch'))
        ->set('editingItemForm.itemMenuId', (string) $menu->id)
        ->set('editingItemForm.itemCategoryId', (string) $category->id)
        ->set('editingItemForm.itemName', 'Soup')
        ->set('editingItemForm.itemTranslations.en.name', 'Soup')
        ->set('editingItemForm.itemTranslations.lt.name', 'Sriuba')
        ->set('editingItemForm.itemTranslations.ru.name', 'Суп')
        ->call('saveItem')
        ->assertHasErrors(['editingItemForm.itemTranslations.en.name' => 'unique']);

    Livewire::actingAs($owner)
        ->test(MenuModifiers::class, $parameters)
        ->set('group.modifierGroupName', 'Extras')
        ->set('group.modifierGroupTranslations.en', 'Extras')
        ->set('group.modifierGroupTranslations.lt', 'Priedai')
        ->set('group.modifierGroupTranslations.ru', 'Добавки')
        ->call('createModifierGroup')
        ->assertHasErrors(['group.modifierGroupName' => 'unique'])
        ->set('option.modifierOptionGroupId', (string) $group->id)
        ->set('option.modifierOptionName', 'Cheese')
        ->set('option.modifierOptionTranslations.en', 'Cheese')
        ->set('option.modifierOptionTranslations.lt', 'Sūris')
        ->set('option.modifierOptionTranslations.ru', 'Сыр')
        ->call('createModifierOption')
        ->assertHasErrors(['option.modifierOptionName' => 'unique']);

    expect(Menu::query()->where('branch_id', $branch->id)->where('name', $menu->name)->count())->toBe(1)
        ->and(MenuCategory::query()->where('menu_id', $menu->id)->where('name', 'Starters')->count())->toBe(1)
        ->and(MenuItem::query()->where('category_id', $category->id)->where('name', 'Soup')->count())->toBe(1)
        ->and(ModifierGroup::query()->where('branch_id', $branch->id)->where('name', 'Extras')->count())->toBe(1)
        ->and(ModifierOption::query()->where('modifier_group_id', $group->id)->where('name', 'Cheese')->count())->toBe(1);
});

test('guest payload localizes menu variants and modifiers with one translation table strategy', function () {
    [, , , $branch, $menu] = createMenuTranslationContext();
    $category = MenuCategory::factory()->for($menu)->create(['name' => 'Base category']);
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['name' => 'Base dish']);
    $variant = MenuItemVariant::factory()->for($item, 'item')->default()->create(['name' => 'Base portion']);
    $group = ModifierGroup::factory()->for($branch)->create(['name' => 'Base extras']);
    $option = ModifierOption::factory()->for($group, 'modifierGroup')->create(['name' => 'Base cheese']);
    $item->modifierGroups()->attach($group);

    MenuTranslation::factory()->for($menu)->create(['language_code' => 'lt', 'name' => 'Lietuviškas meniu']);
    $variant->translations()->create(['language_code' => 'lt', 'name' => 'Lietuviška porcija']);
    ModifierGroupTranslation::factory()->for($group, 'group')->create(['language_code' => 'lt', 'name' => 'Priedai']);
    ModifierOptionTranslation::factory()->for($option, 'option')->create(['language_code' => 'lt', 'name' => 'Sūris']);

    $payload = app(GetGuestMenuForBranchAction::class)->handle($branch->id, 'lt');
    $itemPayload = $payload['menus'][0]['categories'][0]['items'][0];

    expect($payload['menus'][0]['name'])->toBe('Lietuviškas meniu')
        ->and($itemPayload['variants'][0]['name'])->toBe('Lietuviška porcija')
        ->and($itemPayload['modifier_groups'][0]['name'])->toBe('Priedai')
        ->and($itemPayload['modifier_groups'][0]['options'][0]['name'])->toBe('Sūris');
});

test('translation editing cannot select category or dish identifiers from another branch', function () {
    [$owner, $organization, $brand, $branch] = createMenuTranslationContext();
    [, , , , $foreignMenu] = createMenuTranslationContext('Foreign localized restaurant');
    $foreignCategory = MenuCategory::factory()->for($foreignMenu)->create();
    $foreignItem = MenuItem::factory()->for($foreignMenu)->for($foreignCategory, 'category')->create();
    $component = Livewire::actingAs($owner)
        ->test(MenuCatalog::class, menuTranslationComponentParameters($organization->id, $brand->id, $branch->id));

    expect(fn () => $component->call('startEditingCategory', $foreignCategory->id))
        ->toThrow(ModelNotFoundException::class)
        ->and(fn () => $component->call('startEditingItem', $foreignItem->id))
        ->toThrow(ModelNotFoundException::class);

    expect($foreignCategory->fresh())->not->toBeNull()
        ->and($foreignItem->fresh())->not->toBeNull();
});

test('guest menu renders synchronized locale content without placeholder leakage', function () {
    [, , , $branch, $menu] = createMenuTranslationContext();
    $category = MenuCategory::factory()->for($menu)->create(['name' => 'Base mains']);
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['name' => 'Base pasta']);
    MenuCategoryTranslation::factory()->for($category, 'category')->create([
        'language_code' => 'lt',
        'name' => 'Pagrindiniai',
    ]);
    MenuItemTranslation::factory()->for($item, 'item')->create([
        'language_code' => 'lt',
        'name' => 'Makaronai',
    ]);

    $payload = app(GetGuestMenuForBranchAction::class)->handle($branch->id, 'lt');
    $fallbackPayload = app(GetGuestMenuForBranchAction::class)->handle($branch->id, 'ru');

    expect($payload['categories'][0]['name'])->toBe('Pagrindiniai')
        ->and($payload['categories'][0]['items'][0]['name'])->toBe('Makaronai')
        ->and($fallbackPayload['categories'][0]['name'])->toBe('Base mains')
        ->and($fallbackPayload['categories'][0]['items'][0]['name'])->toBe('Base pasta')
        ->and(json_encode($payload))->not->toContain('menu.translations.');
});

function createMenuTranslationContext(string $organizationName = 'Localized Restaurant'): array
{
    $owner = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($owner, ['name' => $organizationName]);
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $menu = Menu::factory()->for($branch)->create([
        'name' => 'Localized menu',
        'status' => MenuStatus::Active,
    ]);

    return [$owner->fresh(), $organization, $brand, $branch, $menu];
}

/** @return array{organizationId: int, brandId: int, branchId: int} */
function menuTranslationComponentParameters(int $organizationId, int $brandId, int $branchId): array
{
    return compact('organizationId', 'brandId', 'branchId');
}
