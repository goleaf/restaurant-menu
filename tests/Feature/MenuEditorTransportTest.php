<?php

declare(strict_types=1);

use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\Menu\Catalog;
use App\Livewire\Organizations\Brands\Branches\Menu\Modifiers;
use App\Livewire\Organizations\Brands\Branches\Menu\Variants;
use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuAvailabilitySchedule;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\User;
use Livewire\Livewire;

/** @return array<string, mixed> */
function menuEditorTransportFixture(string $editor, bool $editing): array
{
    $user = User::factory()->create();
    $organization = Organization::factory()->for($user, 'owner')->create();
    $membership = OrganizationUser::factory()->forOrganization($organization)->forUser($user)
        ->forSystemRole(SystemRole::Owner)->active()->create();
    $membership->loadMissing('role');
    foreach ([SystemPermission::ManageMenu, SystemPermission::ChangePrices, SystemPermission::ChangeAvailability] as $permission) {
        $permission = Permission::factory()->forSystemPermission($permission)->create();
        $membership->role->permissions()->attach($permission, ['enabled' => true]);
    }
    $branch = Branch::factory()->for($organization)->create();
    $menu = Menu::factory()->for($branch)->withTranslations()->create(['name' => 'Original menu']);
    $category = MenuCategory::factory()->for($menu)->withTranslations()->create(['name' => 'Original category']);
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->withTranslations()->create(['name' => 'Original dish', 'price_cents' => 1250]);
    $names = ['en' => 'Transport', 'lt' => 'Transport', 'ru' => 'Transport'];
    $descriptions = array_fill_keys(['en', 'lt', 'ru'], ['name' => 'Transport', 'description' => '']);
    [$componentClass, $subject, $create, $edit, $start, $values] = match ($editor) {
        'menu' => [Catalog::class, $menu, 'createMenu', 'updateMenu', 'startEditingMenu', ['menuName' => 'Transport menu', 'menuStatus' => 'draft', 'menuSortOrder' => '12', 'menuTranslations' => $names]],
        'category' => [Catalog::class, $category, 'createCategory', 'updateCategory', 'startEditingCategory', ['categoryName' => 'Transport category', 'categoryDescription' => '', 'categoryIcon' => 'bookmark', 'categoryIsActive' => '1', 'categorySortOrder' => '12', 'categoryTranslations' => $descriptions]],
        'item' => [Catalog::class, $item, 'createItem', 'updateItem', 'startEditingItem', ['itemName' => 'Transport dish', 'itemPrice' => '10.25', 'itemIsAvailable' => '1', 'itemSortOrder' => '12', 'itemTranslations' => $descriptions]],
        'schedule' => [Catalog::class, MenuAvailabilitySchedule::factory()->for($menu)->create(['day_of_week' => 1, 'starts_at' => '08:00', 'ends_at' => '12:00']), 'createMenuSchedule', 'updateMenuSchedule', 'startEditingMenuSchedule', ['scheduleDayOfWeek' => '2', 'scheduleStartsAt' => '08:00', 'scheduleEndsAt' => '12:00']],
        'group' => [Modifiers::class, ModifierGroup::factory()->for($branch)->withTranslations()->create(['name' => 'Original group']), 'createModifierGroup', 'updateModifierGroup', 'startEditingModifierGroup', ['modifierGroupName' => 'Transport group', 'modifierGroupMinSelect' => '0', 'modifierGroupMaxSelect' => '2', 'modifierGroupSortOrder' => '12', 'modifierGroupIsRequired' => '0', 'modifierGroupTranslations' => $names]],
        'option' => [Modifiers::class, ModifierOption::factory()->for(ModifierGroup::factory()->for($branch)->create(), 'group')->withTranslations()->create(['name' => 'Original option']), 'createModifierOption', 'updateModifierOption', 'startEditingModifierOption', ['modifierOptionName' => 'Transport option', 'modifierOptionPriceDelta' => '-1.25', 'modifierOptionIsAvailable' => '1', 'modifierOptionSortOrder' => '12', 'modifierOptionTranslations' => $names]],
        'variant' => [Variants::class, MenuItemVariant::factory()->for($item, 'item')->withTranslations()->create(['name' => 'Original variant']), 'createVariant', 'updateVariant', 'startEditingVariant', ['variantName' => 'Transport variant', 'variantType' => 'portion', 'variantPrice' => '10.25', 'variantIsDefault' => '0', 'variantIsAvailable' => '1', 'variantSortOrder' => '12', 'variantTranslations' => $names]],
    };
    $component = Livewire::actingAs($user)->test($componentClass, [
        'organizationId' => $organization->id, 'brandId' => $branch->brand_id, 'branchId' => $branch->id,
    ])->assertOk();
    if ($editing) {
        $component->call($start, $subject->id)->assertHasNoErrors();
        $values = collect($values)->mapWithKeys(fn (mixed $value, string $key): array => ['editing'.ucfirst($key) => $value])->all();
    }
    $component->update(updates: $values)->assertOk();

    return [$component, $subject, $editing ? $edit : $create, $branch, $item];
}

test('menu editors reject malformed original transport values without persistence', function (string $editor, string $field, mixed $value, bool $editing): void {
    [$component, $subject, $action] = menuEditorTransportFixture($editor, $editing);
    $field = $editing ? 'editing'.ucfirst($field) : $field;
    $original = $subject->fresh()->getRawOriginal();
    $count = $subject::query()->count();
    try {
        $component->update(calls: [['method' => $action, 'params' => [], 'path' => '']], updates: [$field => $value])
            ->assertHasErrors([$field]);
    } finally {
        expect($subject::query()->count())->toBe($count)
            ->and($subject->fresh()->getRawOriginal())->toBe($original);
    }
})->with('malformed menu editor input')->with(['create' => false, 'edit' => true]);

test('menu editors preserve valid numeric strings and translated values', function (string $editor, bool $editing): void {
    [$component, $subject, $action] = menuEditorTransportFixture($editor, $editing);
    $count = $subject::query()->count();
    $component->call($action)->assertHasNoErrors();
    expect($subject::query()->count())->toBe($count + ($editing ? 0 : 1));
    $saved = $editing ? $subject->fresh() : $subject::query()->latest('id')->firstOrFail();
    $expected = match ($editor) {
        'menu' => ['name' => 'Transport menu', 'sort_order' => 12],
        'category' => ['name' => 'Transport category', 'sort_order' => 12, 'is_active' => true],
        'item' => ['name' => 'Transport dish', 'price_cents' => 1025, 'sort_order' => 12, 'is_available' => true],
        'schedule' => ['day_of_week' => 2, 'starts_at' => '08:00', 'ends_at' => '12:00'],
        'group' => ['name' => 'Transport group', 'min_select' => 0, 'max_select' => 2, 'sort_order' => 12, 'is_required' => false],
        'option' => ['name' => 'Transport option', 'price_delta_cents' => -125, 'sort_order' => 12, 'is_available' => true],
        'variant' => ['name' => 'Transport variant', 'price_cents' => 1025, 'sort_order' => 12, 'is_default' => false, 'is_available' => true],
    };
    expect($saved->only(array_keys($expected)))->toBe($expected);
    if ($editor !== 'schedule') {
        expect($saved->translations()->pluck('name', 'language_code')->all())
            ->toBe(['en' => 'Transport', 'lt' => 'Transport', 'ru' => 'Transport']);
    }
})->with(['menu', 'category', 'item', 'schedule', 'group', 'option', 'variant'])->with(['create' => false, 'edit' => true]);

dataset('malformed menu editor input', [
    'menu array name' => ['menu', 'menuName', ['invalid']],
    'menu null name' => ['menu', 'menuName', null],
    'menu boolean name' => ['menu', 'menuName', true],
    'menu boolean sort' => ['menu', 'menuSortOrder', true],
    'menu scalar translations' => ['menu', 'menuTranslations', 'invalid'],
    'category array name' => ['category', 'categoryName', ['invalid']],
    'category boolean description' => ['category', 'categoryDescription', true],
    'category invalid active' => ['category', 'categoryIsActive', 'false'],
    'category boolean sort' => ['category', 'categorySortOrder', true],
    'category null translations' => ['category', 'categoryTranslations', null],
    'item array name' => ['item', 'itemName', ['invalid']],
    'item boolean name' => ['item', 'itemName', true],
    'item boolean price' => ['item', 'itemPrice', true],
    'item float price' => ['item', 'itemPrice', 10.25],
    'item invalid active' => ['item', 'itemIsAvailable', 'false'],
    'item boolean calories' => ['item', 'itemCalories', true],
    'item scalar allergens' => ['item', 'itemAllergens', 'milk'],
    'item scalar translations' => ['item', 'itemTranslations', true],
    'schedule boolean day' => ['schedule', 'scheduleDayOfWeek', true],
    'schedule array time' => ['schedule', 'scheduleStartsAt', ['08:00']],
    'group array name' => ['group', 'modifierGroupName', ['invalid']],
    'group boolean name' => ['group', 'modifierGroupName', true],
    'group invalid required' => ['group', 'modifierGroupIsRequired', 'false'],
    'group boolean minimum' => ['group', 'modifierGroupMinSelect', true],
    'group boolean maximum' => ['group', 'modifierGroupMaxSelect', true],
    'group scalar translations' => ['group', 'modifierGroupTranslations', 'invalid'],
    'option array name' => ['option', 'modifierOptionName', ['invalid']],
    'option boolean price' => ['option', 'modifierOptionPriceDelta', true],
    'option float price' => ['option', 'modifierOptionPriceDelta', 1.25],
    'option invalid active' => ['option', 'modifierOptionIsAvailable', 'false'],
    'option boolean sort' => ['option', 'modifierOptionSortOrder', true],
    'variant array type' => ['variant', 'variantType', ['portion']],
    'variant boolean name' => ['variant', 'variantName', true],
    'variant boolean price' => ['variant', 'variantPrice', true],
    'variant float price' => ['variant', 'variantPrice', 10.25],
    'variant invalid default' => ['variant', 'variantIsDefault', 'false'],
    'variant invalid active' => ['variant', 'variantIsAvailable', 'false'],
    'variant boolean weight' => ['variant', 'variantWeight', true],
    'variant scalar translations' => ['variant', 'variantTranslations', 'invalid'],
]);

test('menu selections retain malformed input while dependent reads remain safe', function (string $editor, string $field, bool $editing, mixed $value): void {
    [$component, $subject, $action] = menuEditorTransportFixture($editor, $editing);
    $field = $editing ? 'editing'.ucfirst($field) : $field;
    $count = $subject::query()->count();
    $original = $subject->fresh()->getRawOriginal();
    $component->update(updates: [$field => $value])->assertOk()->assertSet($field, $value)
        ->call($action)->assertHasErrors([$field]);
    expect($subject::query()->count())->toBe($count)
        ->and($subject->fresh()->getRawOriginal())->toBe($original);
})->with([
    ['category', 'categoryMenuId', false],
    ['category', 'categoryParentId', false],
    ['schedule', 'scheduleMenuId', false],
    ['item', 'itemMenuId', false],
    ['item', 'itemMenuId', true],
    ['item', 'itemCategoryId', false],
    ['item', 'itemCategoryId', true],
    ['item', 'itemKitchenDepartmentId', false],
    ['item', 'itemKitchenDepartmentId', true],
    ['option', 'modifierOptionGroupId', false],
    ['variant', 'variantMenuId', false],
    ['variant', 'variantItemId', false],
])->with(['array' => [['invalid']], 'boolean' => true]);

test('modifier assignment rejects malformed selections without attaching a group', function (string $field, mixed $value): void {
    [$component, , , , $item] = menuEditorTransportFixture('group', false);
    $component->update(updates: [$field => $value])->assertOk()->assertSet($field, $value)
        ->call('attachModifierGroupToItem')->assertHasErrors([$field]);
    expect($item->modifierGroups()->exists())->toBeFalse();
})->with(['modifierItemMenuId', 'modifierItemId', 'modifierItemGroupId'])->with(['array' => [['invalid']], 'boolean' => true]);
