<?php

use App\Actions\Menus\GetGuestMenuForBranchAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\MenuStatus;
use App\Livewire\Organizations\Brands\Branches\Menu\Catalog as MenuCatalog;
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

test('manager creates category and dish translations for every supported locale', function () {
    [$owner, $organization, $brand, $branch, $menu] = createMenuTranslationContext();
    $parameters = menuTranslationComponentParameters($organization->id, $brand->id, $branch->id);

    Livewire::actingAs($owner)
        ->test(MenuCatalog::class, $parameters)
        ->set('categoryMenuId', (string) $menu->id)
        ->set('categoryName', 'Starters')
        ->set('categoryTranslations.en.name', 'Starters')
        ->set('categoryTranslations.en.description', 'Small plates')
        ->set('categoryTranslations.lt.name', 'Užkandžiai')
        ->set('categoryTranslations.lt.description', 'Maži patiekalai')
        ->set('categoryTranslations.ru.name', 'Закуски')
        ->set('categoryTranslations.ru.description', 'Небольшие блюда')
        ->call('createCategory')
        ->assertHasNoErrors();

    $category = MenuCategory::query()->where('menu_id', $menu->id)->where('name', 'Starters')->firstOrFail();

    Livewire::actingAs($owner)
        ->test(MenuCatalog::class, $parameters)
        ->set('itemMenuId', (string) $menu->id)
        ->set('itemCategoryId', (string) $category->id)
        ->set('itemName', 'Cold beet soup')
        ->set('itemTranslations.en.name', 'Cold beet soup')
        ->set('itemTranslations.en.description', 'With herbs')
        ->set('itemTranslations.lt.name', 'Šaltibarščiai')
        ->set('itemTranslations.lt.description', 'Su žalumynais')
        ->set('itemTranslations.ru.name', 'Холодный свекольный суп')
        ->set('itemTranslations.ru.description', 'С зеленью')
        ->call('createItem')
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
        ->assertSet('editingCategoryTranslations.lt.name', 'lt category')
        ->set('editingCategoryTranslations.lt.name', 'Atnaujinta kategorija')
        ->call('updateCategory')
        ->assertHasNoErrors()
        ->call('startEditingItem', $item->id)
        ->assertSet('editingItemTranslations.lt.name', 'Lietuviškas patiekalas')
        ->set('editingItemTranslations.lt.name', 'Atnaujintas patiekalas')
        ->set('editingItemTranslations.ru.name', 'Обновлённое блюдо')
        ->call('updateItem')
        ->assertHasNoErrors()
        ->assertSee('Atnaujinta kategorija')
        ->assertSee('Atnaujintas patiekalas');

    expect($category->translations()->where('language_code', 'lt')->value('name'))->toBe('Atnaujinta kategorija')
        ->and($item->translations()->where('language_code', 'lt')->value('name'))->toBe('Atnaujintas patiekalas')
        ->and($item->translations()->where('language_code', 'ru')->value('name'))->toBe('Обновлённое блюдо')
        ->and(Cache::store(GetGuestMenuForBranchAction::cacheStore())->has($cacheKey))->toBeFalse()
        ->and(app(GetGuestMenuForBranchAction::class)->handle($branch->id, 'ru')['categories'][0]['items'][0]['name'])->toBe('Обновлённое блюдо');
});

test('translation description requires a locale name and errors stay associated with the field', function () {
    [$owner, $organization, $brand, $branch, $menu] = createMenuTranslationContext();

    Livewire::actingAs($owner)
        ->test(MenuCatalog::class, menuTranslationComponentParameters($organization->id, $brand->id, $branch->id))
        ->set('categoryMenuId', (string) $menu->id)
        ->set('categoryName', 'Desserts')
        ->set('categoryTranslations.lt.name', '')
        ->set('categoryTranslations.lt.description', 'Saldūs patiekalai')
        ->call('createCategory')
        ->assertHasErrors(['categoryTranslations.lt.name' => 'required']);

    expect(MenuCategory::query()->where('menu_id', $menu->id)->where('name', 'Desserts')->exists())->toBeFalse();
});

test('menu editor requires a name in every supported guest locale', function () {
    [$owner, $organization, $brand, $branch] = createMenuTranslationContext();

    Livewire::actingAs($owner)
        ->test(MenuCatalog::class, menuTranslationComponentParameters($organization->id, $brand->id, $branch->id))
        ->set('menuName', 'Lunch')
        ->set('menuTranslations.en', 'Lunch')
        ->set('menuTranslations.lt', 'Pietūs')
        ->set('menuTranslations.ru', '')
        ->call('createMenu')
        ->assertHasErrors(['menuTranslations.ru' => 'required']);

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
        ->set('menuName', $menu->name)
        ->set('menuTranslations.en', 'Menu')
        ->set('menuTranslations.lt', 'Meniu')
        ->set('menuTranslations.ru', 'Меню')
        ->call('createMenu')
        ->assertHasErrors(['menuName' => 'unique'])
        ->set('categoryMenuId', (string) $menu->id)
        ->set('categoryName', 'Starters')
        ->set('categoryTranslations.en.name', 'Starters')
        ->set('categoryTranslations.lt.name', 'Užkandžiai')
        ->set('categoryTranslations.ru.name', 'Закуски')
        ->call('createCategory')
        ->assertHasErrors(['categoryName' => 'unique'])
        ->set('itemMenuId', (string) $menu->id)
        ->set('itemCategoryId', (string) $category->id)
        ->set('itemName', 'Soup')
        ->set('itemTranslations.en.name', 'Soup')
        ->set('itemTranslations.lt.name', 'Sriuba')
        ->set('itemTranslations.ru.name', 'Суп')
        ->call('createItem')
        ->assertHasErrors(['itemName' => 'unique']);

    Livewire::actingAs($owner)
        ->test(MenuModifiers::class, $parameters)
        ->set('modifierGroupName', 'Extras')
        ->set('modifierGroupTranslations.en', 'Extras')
        ->set('modifierGroupTranslations.lt', 'Priedai')
        ->set('modifierGroupTranslations.ru', 'Добавки')
        ->call('createModifierGroup')
        ->assertHasErrors(['modifierGroupName' => 'unique'])
        ->set('modifierOptionGroupId', (string) $group->id)
        ->set('modifierOptionName', 'Cheese')
        ->set('modifierOptionTranslations.en', 'Cheese')
        ->set('modifierOptionTranslations.lt', 'Sūris')
        ->set('modifierOptionTranslations.ru', 'Сыр')
        ->call('createModifierOption')
        ->assertHasErrors(['modifierOptionName' => 'unique']);

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
