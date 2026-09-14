<?php

declare(strict_types=1);

use App\Enums\MenuStatus;
use App\Livewire\PublicQr\GuestMenu;
use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Livewire\Livewire;

test('guest filter projections ignore malformed elements while retaining the original transport state', function (string $property, string $validFilter): void {
    $branch = Branch::factory()->create();
    $menu = Menu::factory()->for($branch)->create(['status' => MenuStatus::Active]);
    $category = MenuCategory::factory()->for($menu)->create(['is_active' => true]);
    MenuItem::factory()->for($menu)->for($category, 'category')->create(['name' => 'Plant plate', 'dietary_labels' => ['vegan'], 'allergens' => ['gluten']]);
    MenuItem::factory()->for($menu)->for($category, 'category')->create(['name' => 'Milk plate', 'dietary_labels' => [], 'allergens' => ['milk']]);
    $input = [[$validFilter], null, true, false, 1, 'unknown', $validFilter];

    Livewire::test(GuestMenu::class, ['branchId' => $branch->id, 'language' => 'en'])
        ->set($property, $input)
        ->assertSet($property, $input)
        ->assertSeeText('Plant plate')
        ->assertDontSeeText('Milk plate');
})->with([
    'dietary labels' => ['dietaryFilters', 'vegan'],
    'excluded allergens' => ['excludedAllergens', 'milk'],
]);
