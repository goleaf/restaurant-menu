<?php

declare(strict_types=1);

use App\Actions\Menus\RemoveMenuItemImageAction;
use App\Actions\Menus\UpdateMenuAction;
use App\Actions\Menus\UpdateMenuCategoryAction;
use App\Enums\MenuStatus;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Support\Facades\Storage;

test('menu action updates mutable menu fields with string and enum statuses', function (): void {
    $menu = Menu::factory()->create([
        'name' => 'Lunch',
        'status' => MenuStatus::Draft,
        'sort_order' => 1,
    ]);
    $action = app(UpdateMenuAction::class);

    expect($action->handle($menu, [
        'name' => 'Dinner',
        'status' => MenuStatus::Active->value,
        'sort_order' => 10,
    ]))->toBe($menu);

    $menu->refresh();

    expect($menu->name)->toBe('Dinner')
        ->and($menu->status)->toBe(MenuStatus::Active)
        ->and($menu->sort_order)->toBe(10);

    $action->handle($menu, [
        'name' => 'Archived Dinner',
        'status' => MenuStatus::Archived,
        'sort_order' => 20,
    ]);

    expect($menu->refresh()->status)->toBe(MenuStatus::Archived);
});

test('menu category action normalizes plain text and updates presentation fields', function (): void {
    $category = MenuCategory::factory()->create([
        'name' => 'Original',
        'description' => null,
    ]);

    expect(app(UpdateMenuCategoryAction::class)->handle($category, [
        'name' => "  Hot\n  drinks  ",
        'description' => "  Freshly\n brewed  ",
        'icon' => 'mug-hot',
        'sort_order' => 30,
        'is_active' => false,
    ]))->toBe($category);

    $category->refresh();

    expect($category->name)->toBe('Hot drinks')
        ->and($category->description)->toBe("Freshly\n brewed")
        ->and($category->icon)->toBe('mug-hot')
        ->and($category->sort_order)->toBe(30)
        ->and($category->is_active)->toBeFalse();
});

test('menu item image action clears the persisted path and deletes the local file', function (): void {
    Storage::fake('public');
    $path = 'media/menu-items/dish.jpg';
    Storage::disk('public')->put($path, 'image');
    $item = MenuItem::factory()->create(['image' => $path]);

    expect(app(RemoveMenuItemImageAction::class)->handle($item))->toBe($item);

    expect($item->refresh()->image)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});
