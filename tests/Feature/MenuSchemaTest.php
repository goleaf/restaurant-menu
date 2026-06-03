<?php

use App\Enums\MenuStatus;
use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuCategoryTranslation;
use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use Illuminate\Support\Facades\Schema;

test('menu tables expose the required columns', function () {
    expect(Schema::hasTable('menus'))->toBeTrue()
        ->and(Schema::hasColumns('menus', [
            'id',
            'branch_id',
            'name',
            'status',
            'sort_order',
            'created_at',
            'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasTable('menu_categories'))->toBeTrue()
        ->and(Schema::hasColumns('menu_categories', [
            'id',
            'menu_id',
            'parent_id',
            'name',
            'description',
            'image',
            'icon',
            'sort_order',
            'is_active',
            'created_at',
            'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasTable('menu_items'))->toBeTrue()
        ->and(Schema::hasColumns('menu_items', [
            'id',
            'menu_id',
            'category_id',
            'name',
            'description',
            'price',
            'image',
            'weight',
            'volume',
            'calories',
            'is_available',
            'sort_order',
            'created_at',
            'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasTable('menu_category_translations'))->toBeTrue()
        ->and(Schema::hasColumns('menu_category_translations', [
            'id',
            'menu_category_id',
            'language_code',
            'name',
            'description',
            'created_at',
            'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasTable('menu_item_translations'))->toBeTrue()
        ->and(Schema::hasColumns('menu_item_translations', [
            'id',
            'menu_item_id',
            'language_code',
            'name',
            'description',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
});

test('menu models keep branch category and item relationships', function () {
    $branch = Branch::factory()->create();
    $menu = Menu::factory()
        ->for($branch)
        ->create([
            'name' => 'Dinner Menu',
            'status' => MenuStatus::Active,
        ]);
    $parentCategory = MenuCategory::factory()
        ->for($menu)
        ->create(['name' => 'Pizza']);
    $childCategory = MenuCategory::factory()
        ->for($menu)
        ->for($parentCategory, 'parent')
        ->create(['name' => 'Classic Pizza']);
    $item = MenuItem::factory()
        ->for($menu)
        ->for($childCategory, 'category')
        ->create([
            'name' => 'Margherita',
            'price' => '12.50',
            'weight' => '450.00',
            'volume' => null,
            'calories' => 720,
        ]);

    expect($branch->menus()->pluck('menus.id')->all())->toBe([$menu->id])
        ->and($menu->status)->toBe(MenuStatus::Active)
        ->and($menu->categories()->pluck('menu_categories.id')->all())->toBe([$childCategory->id, $parentCategory->id])
        ->and($parentCategory->children()->pluck('menu_categories.id')->all())->toBe([$childCategory->id])
        ->and($childCategory->parent->id)->toBe($parentCategory->id)
        ->and($childCategory->is_active)->toBeTrue()
        ->and($childCategory->items()->pluck('menu_items.id')->all())->toBe([$item->id])
        ->and($item->menu->id)->toBe($menu->id)
        ->and($item->category->id)->toBe($childCategory->id)
        ->and($item->price)->toBe('12.50')
        ->and($item->weight)->toBe('450.00')
        ->and($item->volume)->toBeNull()
        ->and($item->calories)->toBe(720)
        ->and($item->is_available)->toBeTrue();
});

test('menu category and item translations belong to their base records', function () {
    $category = MenuCategory::factory()->create();
    $item = MenuItem::factory()
        ->for($category->menu)
        ->for($category, 'category')
        ->create();

    $categoryTranslation = MenuCategoryTranslation::factory()
        ->for($category, 'category')
        ->create([
            'language_code' => 'lt',
            'name' => 'Picos',
            'description' => 'Karsti patiekalai',
        ]);
    $itemTranslation = MenuItemTranslation::factory()
        ->for($item, 'item')
        ->create([
            'language_code' => 'lt',
            'name' => 'Margarita LT',
            'description' => 'Pomidorai ir mozzarella',
        ]);

    expect($category->translations()->pluck('menu_category_translations.id')->all())->toBe([$categoryTranslation->id])
        ->and($categoryTranslation->category->id)->toBe($category->id)
        ->and($item->translations()->pluck('menu_item_translations.id')->all())->toBe([$itemTranslation->id])
        ->and($itemTranslation->item->id)->toBe($item->id);
});

test('deleting a menu removes its categories and items', function () {
    $menu = Menu::factory()->create();
    $category = MenuCategory::factory()->for($menu)->create();
    MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->has(MenuItemTranslation::factory()->state(['language_code' => 'lt']), 'translations')
        ->create();
    MenuCategoryTranslation::factory()
        ->for($category, 'category')
        ->create(['language_code' => 'lt']);
    MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->create();

    $menu->delete();

    expect(Menu::query()->exists())->toBeFalse()
        ->and(MenuCategory::query()->exists())->toBeFalse()
        ->and(MenuItem::query()->exists())->toBeFalse()
        ->and(MenuCategoryTranslation::query()->exists())->toBeFalse()
        ->and(MenuItemTranslation::query()->exists())->toBeFalse();
});
