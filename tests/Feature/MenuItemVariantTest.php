<?php

declare(strict_types=1);

use App\Actions\Menus\CreateMenuItemVariantAction;
use App\Actions\Menus\DeleteMenuItemVariantAction;
use App\Actions\Menus\GetGuestMenuForBranchAction;
use App\Actions\Menus\UpdateMenuItemVariantAction;
use App\Enums\MenuItemVariantType;
use App\Enums\MenuStatus;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\MenuItemVariantTranslation;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
});

test('menu item variants expose typed portion data and localized names', function (): void {
    $item = MenuItem::factory()->create();
    $variant = MenuItemVariant::factory()
        ->for($item, 'item')
        ->portion()
        ->default()
        ->create([
            'name' => 'Large',
            'price_cents' => 1890,
            'weight' => '650.00',
            'volume' => null,
        ]);
    $translation = MenuItemVariantTranslation::factory()
        ->for($variant, 'variant')
        ->create([
            'language_code' => 'lt',
            'name' => 'Didelė',
        ]);

    expect($variant->type)->toBe(MenuItemVariantType::Portion)
        ->and($variant->price_cents)->toBe(1890)
        ->and($variant->weight)->toBe('650.00')
        ->and($variant->volume)->toBeNull()
        ->and($variant->is_default)->toBeTrue()
        ->and($variant->is_available)->toBeTrue()
        ->and($variant->localizedName('lt'))->toBe('Didelė')
        ->and($variant->localizedName('ru'))->toBe('Large')
        ->and($item->variants()->pluck('menu_item_variants.id')->all())->toBe([$variant->id])
        ->and($variant->translations()->pluck('menu_item_variant_translations.id')->all())->toBe([$translation->id])
        ->and($translation->variant->is($variant))->toBeTrue();
});

test('variant actions keep one default and synchronize supported translations', function (): void {
    [$actor, $branch, $item] = menuItemVariantContext();

    $regular = app(CreateMenuItemVariantAction::class)->handle($actor, $branch, $item, [
        'type' => MenuItemVariantType::Variant->value,
        'name' => 'Classic',
        'price' => '12.50',
        'weight' => null,
        'volume' => null,
        'is_default' => false,
        'is_available' => true,
        'sort_order' => 10,
        'translations' => [
            'en' => 'Classic',
            'lt' => 'Klasikinis',
            'ru' => 'Классический',
        ],
    ]);

    expect($regular->is_default)->toBeTrue()
        ->and($regular->price_cents)->toBe(1250)
        ->and($regular->translations()->orderBy('language_code')->pluck('name', 'language_code')->all())->toBe([
            'en' => 'Classic',
            'lt' => 'Klasikinis',
            'ru' => 'Классический',
        ]);

    $large = app(CreateMenuItemVariantAction::class)->handle($actor, $branch, $item, [
        'type' => MenuItemVariantType::Portion->value,
        'name' => 'Large',
        'price' => '18.90',
        'weight' => '650',
        'volume' => null,
        'is_default' => true,
        'is_available' => true,
        'sort_order' => 20,
        'translations' => ['en' => 'Large', 'lt' => 'Didelė', 'ru' => 'Большая'],
    ]);

    expect($regular->refresh()->is_default)->toBeFalse()
        ->and($large->is_default)->toBeTrue();

    $large = app(UpdateMenuItemVariantAction::class)->handle($actor, $branch, $large, [
        'type' => MenuItemVariantType::Portion->value,
        'name' => 'Family',
        'price' => '21.00',
        'weight' => '900',
        'volume' => null,
        'is_default' => true,
        'is_available' => true,
        'sort_order' => 30,
        'translations' => ['en' => 'Family', 'lt' => 'Šeimos', 'ru' => 'Семейная'],
    ]);

    expect($large->price_cents)->toBe(2100)
        ->and($large->localizedName('lt'))->toBe('Šeimos');

    app(DeleteMenuItemVariantAction::class)->handle($actor, $branch, $large);

    expect(MenuItemVariant::query()->whereKey($large->id)->exists())->toBeFalse()
        ->and($regular->refresh()->is_default)->toBeTrue();
});

test('variant actions enforce menu permission and branch ownership', function (): void {
    $unauthorizedUser = User::factory()->create();
    $branch = Branch::factory()->create();
    $menu = Menu::factory()->for($branch)->create();
    $item = MenuItem::factory()->for($menu)->create();
    $variantData = [
        'type' => MenuItemVariantType::Portion->value,
        'name' => 'Regular',
        'price' => '10.00',
        'weight' => null,
        'volume' => null,
        'is_default' => true,
        'is_available' => true,
        'sort_order' => 10,
    ];

    expect(fn () => app(CreateMenuItemVariantAction::class)->handle(
        $unauthorizedUser,
        $branch,
        $item,
        $variantData,
    ))->toThrow(AuthorizationException::class);

    $superadmin = User::factory()->create();
    $superadmin->roles()->attach(Role::query()->where('code', SystemRole::Superadmin->value)->firstOrFail());
    $otherItem = MenuItem::factory()->create();
    $otherVariant = MenuItemVariant::factory()->for($otherItem, 'item')->create();

    expect(fn () => app(CreateMenuItemVariantAction::class)->handle(
        $superadmin,
        $branch,
        $otherItem,
        $variantData,
    ))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(UpdateMenuItemVariantAction::class)->handle(
            $superadmin,
            $branch,
            $otherVariant,
            $variantData,
        ))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(DeleteMenuItemVariantAction::class)->handle(
            $superadmin,
            $branch,
            $otherVariant,
        ))->toThrow(InvalidArgumentException::class);
});

test('guest menu exposes only available variants with localized names and absolute prices', function (): void {
    $branch = Branch::factory()->create();
    $menu = Menu::factory()->for($branch)->create(['status' => MenuStatus::Active]);
    $category = MenuCategory::factory()->for($menu)->create(['is_active' => true]);
    $item = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->create(['name' => 'Pizza', 'price_cents' => 1000, 'is_available' => true]);
    $small = MenuItemVariant::factory()
        ->for($item, 'item')
        ->portion()
        ->default()
        ->create(['name' => 'Small', 'price_cents' => 1000, 'sort_order' => 10]);
    $large = MenuItemVariant::factory()
        ->for($item, 'item')
        ->portion()
        ->create(['name' => 'Large', 'price_cents' => 1500, 'sort_order' => 20]);
    $unavailable = MenuItemVariant::factory()
        ->for($item, 'item')
        ->unavailable()
        ->create(['name' => 'Family', 'price_cents' => 2200, 'sort_order' => 30]);
    $unorderableItem = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->create(['name' => 'Seasonal soup', 'price_cents' => 900, 'is_available' => true, 'sort_order' => 20]);
    MenuItemVariant::factory()
        ->for($unorderableItem, 'item')
        ->portion()
        ->unavailable()
        ->create(['name' => 'Regular', 'price_cents' => 900]);

    MenuItemVariantTranslation::factory()->for($small, 'variant')->create(['language_code' => 'lt', 'name' => 'Maža']);
    MenuItemVariantTranslation::factory()->for($large, 'variant')->create(['language_code' => 'lt', 'name' => 'Didelė']);
    MenuItemVariantTranslation::factory()->for($unavailable, 'variant')->create(['language_code' => 'lt', 'name' => 'Šeimos']);

    $action = app(GetGuestMenuForBranchAction::class);
    $payload = $action->handle($branch->id, 'lt');
    $itemPayload = $payload['categories'][0]['items'][0];
    $unorderableItemPayload = collect($payload['categories'][0]['items'])->firstWhere('id', $unorderableItem->id);

    expect($itemPayload['variants'])->toBe([
        [
            'id' => $small->id,
            'type' => MenuItemVariantType::Portion->value,
            'type_label' => 'Porcija',
            'name' => 'Maža',
            'price_cents' => 1000,
            'weight' => $small->weight,
            'volume' => $small->volume,
            'is_default' => true,
        ],
        [
            'id' => $large->id,
            'type' => MenuItemVariantType::Portion->value,
            'type_label' => 'Porcija',
            'name' => 'Didelė',
            'price_cents' => 1500,
            'weight' => $large->weight,
            'volume' => $large->volume,
            'is_default' => false,
        ],
    ]);

    expect($unorderableItemPayload)->toBeArray()
        ->and($unorderableItemPayload['is_available'])->toBeFalse()
        ->and($unorderableItemPayload['variants'])->toBe([]);

    expect(Cache::store(GetGuestMenuForBranchAction::cacheStore())->has(
        GetGuestMenuForBranchAction::cacheKey($branch->id, 'lt'),
    ))->toBeTrue();

    $large->updateOrFail(['price_cents' => 1700]);

    expect(Cache::store(GetGuestMenuForBranchAction::cacheStore())->has(
        GetGuestMenuForBranchAction::cacheKey($branch->id, 'lt'),
    ))->toBeFalse();
});

/**
 * @return array{User, Branch, MenuItem}
 */
function menuItemVariantContext(): array
{
    $actor = User::factory()->create();
    $actor->roles()->attach(Role::query()->where('code', SystemRole::Superadmin->value)->firstOrFail());
    $branch = Branch::factory()->create();
    $menu = Menu::factory()->for($branch)->create();
    $category = MenuCategory::factory()->for($menu)->create();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['price_cents' => 1000]);

    return [$actor, $branch, $item];
}
