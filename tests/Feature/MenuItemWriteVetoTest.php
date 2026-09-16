<?php

declare(strict_types=1);

use App\Actions\Menus\UpdateMenuItemAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Data\Menus\MenuItemData;
use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;

test('dish write veto rolls back translations and observer writes for both editor and action callers', function (string $event, bool $versioned): void {
    $this->seed(SystemPermissionsSeeder::class);
    $owner = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'Veto restaurant']);
    $branch = Branch::factory()->for($organization)->create();
    $menu = Menu::factory()->for($branch)->create();
    $category = MenuCategory::factory()->for($menu)->create();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['name' => 'Original dish', 'price_cents' => 1200]);
    $translation = MenuItemTranslation::factory()->for($item, 'item')->create(['language_code' => 'lt', 'name' => 'Original translation']);
    $version = $item->load('translations')->contentFingerprint();
    $original = $item->fresh()->getRawOriginal();
    $dispatcher = MenuItem::getEventDispatcher();
    MenuItem::setEventDispatcher(clone $dispatcher);
    MenuItem::{$event}(function (MenuItem $saving) use ($item, $translation): ?bool {
        if ($saving->id !== $item->id) {
            return null;
        }
        MenuItemTranslation::query()->whereKey($translation->id)->update(['name' => 'Observer side effect']);

        return false;
    });
    $failure = null;
    try {
        app(UpdateMenuItemAction::class)->handle($owner->fresh(), $branch, $item, $menu, $category, null, MenuItemData::fromValidated([
            'name' => 'Changed dish', 'description' => null, 'price' => '19.00',
            'weight' => null, 'volume' => null, 'calories' => null, 'sort_order' => 0,
            'translations' => ['lt' => ['name' => 'Changed translation', 'description' => null]],
        ]), $versioned ? $version : null);
    } catch (RuntimeException $exception) {
        $failure = $exception;
    } finally {
        MenuItem::setEventDispatcher($dispatcher);
    }

    expect($item->fresh()->getRawOriginal())->toBe($original)
        ->and($translation->fresh()->name)->toBe('Original translation')
        ->and($failure)->toBeInstanceOf(RuntimeException::class);
})->with(['saving', 'updating'])->with(['versioned editor' => true, 'direct action' => false]);
