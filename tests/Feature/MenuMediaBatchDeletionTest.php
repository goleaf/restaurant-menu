<?php

declare(strict_types=1);

use App\Actions\Menus\DeleteMenuAction;
use App\Actions\Menus\DeleteMenuCategoryAction;
use App\Actions\Menus\DeleteMenuItemAction;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemImage;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    ParallelTesting::resolveTokenUsing(fn (): string => 'menu-media-batch-'.getmypid());
    Storage::fake('public');
});

test('parent deletion streams every media path with bounded model hydration across batches', function (string $entity): void {
    $menu = Menu::factory()->create();
    $category = MenuCategory::factory()->for($menu)->create();
    $expectedPaths = [];
    for ($index = 0; $index < 605; $index++) {
        $primary = 'media/batch/primary-'.$index.'.png';
        $gallery = 'media/batch/gallery-'.$index.'.png';
        $item = MenuItem::factory()->for($menu)->for($category, 'category')->createQuietly([
            'image' => $primary,
            'sort_order' => 605 - $index,
        ]);
        MenuItemImage::factory()->for($item, 'item')->createQuietly(['path' => $gallery]);
        $expectedPaths[] = $primary;
        $expectedPaths[] = $gallery;
    }
    unset($item);
    $liveModels = new WeakMap;
    $maximumLiveModels = 0;
    $trackHydration = function (MenuItem|MenuItemImage $model) use ($liveModels, &$maximumLiveModels): void {
        $liveModels[$model] = true;
        $maximumLiveModels = max($maximumLiveModels, count($liveModels));
    };
    MenuItem::retrieved($trackHydration);
    MenuItemImage::retrieved($trackHydration);
    $deletedItemEvents = 0;
    MenuItem::deleted(function () use (&$deletedItemEvents): void {
        $deletedItemEvents++;
    });
    $deletedPaths = [];
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('delete')
        ->andReturnUsing(function (string $path) use (&$deletedPaths): bool {
            $deletedPaths[] = $path;

            return true;
        });
    Storage::shouldReceive('disk')->with('public')->andReturn($disk);

    match ($entity) {
        'menu' => app(DeleteMenuAction::class)->handle($menu),
        'category' => app(DeleteMenuCategoryAction::class)->handle($category),
    };

    expect($maximumLiveModels)->toBeLessThanOrEqual(400)
        ->and($deletedItemEvents)->toBe(605)
        ->and($deletedPaths)->toHaveCount(1210)
        ->and($deletedPaths)->toEqualCanonicalizing($expectedPaths)
        ->and(MenuItem::query()->where('menu_id', $menu->id)->exists())->toBeFalse()
        ->and(MenuItem::onlyTrashed()->where('menu_id', $menu->id)->count())->toBe(605)
        ->and(MenuItemImage::query()->exists())->toBeFalse();
})->with(['menu', 'category']);

test('wide category descendants keep query filters bounded and include media from the last category batch', function (): void {
    $menu = Menu::factory()->create();
    $root = MenuCategory::factory()->for($menu)->create();
    $categoryIds = MenuCategory::factory()->for($menu)->count(405)
        ->createQuietly(['parent_id' => $root->id])->modelKeys();
    $paths = [];

    foreach ([0, 199, 404] as $index) {
        $path = 'media/batch/wide-'.$index.'.png';
        $item = MenuItem::factory()->for($menu)->createQuietly([
            'category_id' => $categoryIds[$index],
            'image' => $path,
        ]);
        $gallery = MenuItemImage::factory()->for($item, 'item')->createQuietly();
        Storage::disk('public')->put($path, 'primary');
        Storage::disk('public')->put($gallery->path, 'gallery');
        $paths[] = $path;
        $paths[] = $gallery->path;
    }

    $maximumBindings = 0;
    DB::listen(function (QueryExecuted $query) use (&$maximumBindings): void {
        if (str_contains($query->sql, ' in ')) {
            $maximumBindings = max($maximumBindings, count($query->bindings));
        }
    });

    app(DeleteMenuCategoryAction::class)->handle($root);

    expect($maximumBindings)->toBeGreaterThanOrEqual(200)
        ->toBeLessThanOrEqual(202)
        ->and(MenuCategory::onlyTrashed()->where('menu_id', $menu->id)->count())->toBe(406)
        ->and(MenuItem::onlyTrashed()->where('menu_id', $menu->id)->count())->toBe(3)
        ->and(MenuItemImage::query()->exists())->toBeFalse();
    Storage::disk('public')->assertMissing($paths);
});

test('parent deletion preserves malformed foreign menu children and items and their files', function (string $entity): void {
    $menu = Menu::factory()->create();
    $foreignMenu = Menu::factory()->create();
    $root = MenuCategory::factory()->for($menu)->create();
    $ownedItem = MenuItem::factory()->for($menu)->for($root, 'category')->create();
    $foreignChild = MenuCategory::factory()->for($foreignMenu)->create(['parent_id' => $root->id]);
    $foreignChildItem = MenuItem::factory()->for($foreignMenu)->for($foreignChild, 'category')->create([
        'image' => 'media/batch/foreign-child.png',
    ]);
    $foreignRootItem = MenuItem::factory()->for($foreignMenu)->for($root, 'category')->create([
        'image' => 'media/batch/foreign-item.png',
    ]);
    $misplacedOwnedItem = MenuItem::factory()->for($menu)->for($foreignChild, 'category')->create([
        'image' => 'media/batch/misplaced-owned-item.png',
    ]);
    $foreignImage = MenuItemImage::factory()->for($foreignRootItem, 'item')->create();
    Storage::disk('public')->put($foreignChildItem->image, 'foreign child');
    Storage::disk('public')->put($foreignRootItem->image, 'foreign item');
    Storage::disk('public')->put($foreignImage->path, 'foreign gallery');
    Storage::disk('public')->put($misplacedOwnedItem->image, 'owned misplaced item');

    match ($entity) {
        'menu' => app(DeleteMenuAction::class)->handle($menu),
        'category' => app(DeleteMenuCategoryAction::class)->handle($root),
    };

    expect($ownedItem->fresh()->trashed())->toBeTrue()
        ->and($foreignChild->fresh()->trashed())->toBeFalse()
        ->and($foreignChildItem->fresh()->trashed())->toBeFalse()
        ->and($foreignRootItem->fresh()->trashed())->toBeFalse()
        ->and($foreignImage->fresh())->not->toBeNull()
        ->and($misplacedOwnedItem->fresh()->trashed())->toBe($entity === 'menu');
    Storage::disk('public')->assertExists([$foreignChildItem->image, $foreignRootItem->image, $foreignImage->path]);
    expect(Storage::disk('public')->exists($misplacedOwnedItem->image))->toBe($entity !== 'menu');
})->with(['menu', 'category']);

test('category media traversal terminates on a malformed cycle and deletes each owned node once', function (string $entity): void {
    $menu = Menu::factory()->create();
    $root = MenuCategory::factory()->for($menu)->create();
    $child = MenuCategory::factory()->for($menu)->create(['parent_id' => $root->id]);
    $root->updateQuietly(['parent_id' => $child->id]);
    $item = MenuItem::factory()->for($menu)->for($child, 'category')->create(['image' => 'media/batch/cycle.png']);
    Storage::disk('public')->put($item->image, 'owned');
    $traversalReads = 0;
    DB::listen(function (QueryExecuted $query) use (&$traversalReads): void {
        if (str_contains($query->sql, 'menu_categories') && str_contains($query->sql, 'parent_id') && str_contains($query->sql, ' in ')) {
            if (++$traversalReads > 20) {
                throw new RuntimeException('Category traversal did not terminate.');
            }
        }
    });
    $deletedCategories = [];
    MenuCategory::deleted(function (MenuCategory $category) use (&$deletedCategories): void {
        $deletedCategories[] = $category->id;
    });

    match ($entity) {
        'menu' => app(DeleteMenuAction::class)->handle($menu),
        'category' => app(DeleteMenuCategoryAction::class)->handle($root),
    };

    expect($deletedCategories)->toEqualCanonicalizing([$root->id, $child->id])
        ->and($item->fresh()->trashed())->toBeTrue();
    Storage::disk('public')->assertMissing($item->image);
})->with(['menu', 'category']);

test('a deletion veto rolls back the entire parent media operation', function (string $entity, string $veto): void {
    $menu = Menu::factory()->create();
    $root = MenuCategory::factory()->for($menu)->create();
    $child = MenuCategory::factory()->for($menu)->create(['parent_id' => $root->id]);
    $item = MenuItem::factory()->for($menu)->for($child, 'category')->create(['image' => 'media/batch/veto.png']);
    $gallery = MenuItemImage::factory()->for($item, 'item')->create();
    Storage::disk('public')->put($item->image, 'original');
    Storage::disk('public')->put($gallery->path, 'gallery');
    $vetoedModel = match ($veto) {
        'root' => $entity === 'menu' ? $menu : $root,
        'child' => $child,
        'item' => $item,
    };
    $vetoedId = $vetoedModel->id;
    $vetoedModel::deleting(fn ($model): ?bool => $model->id === $vetoedId ? false : null);

    expect(fn () => match ($entity) {
        'menu' => app(DeleteMenuAction::class)->handle($menu),
        'category' => app(DeleteMenuCategoryAction::class)->handle($root),
    })->toThrow(RuntimeException::class);

    expect($menu->fresh()->trashed())->toBeFalse()
        ->and($root->fresh()->trashed())->toBeFalse()
        ->and($child->fresh()->trashed())->toBeFalse()
        ->and($item->fresh()->trashed())->toBeFalse()
        ->and($gallery->fresh())->not->toBeNull();
    Storage::disk('public')->assertExists([$item->image, $gallery->path]);
})->with([
    ['menu', 'root'], ['menu', 'child'], ['menu', 'item'],
    ['category', 'root'], ['category', 'child'], ['category', 'item'],
]);

test('a menu item deletion veto preserves its row gallery and files', function (): void {
    $item = MenuItem::factory()->create(['image' => 'media/batch/item-veto.png']);
    $gallery = MenuItemImage::factory()->for($item, 'item')->create();
    Storage::disk('public')->put($item->image, 'primary');
    Storage::disk('public')->put($gallery->path, 'gallery');
    MenuItem::deleting(fn (): bool => false);

    expect(fn () => app(DeleteMenuItemAction::class)->handle($item))->toThrow(RuntimeException::class);

    expect($item->fresh()->trashed())->toBeFalse()
        ->and($gallery->fresh())->not->toBeNull();
    Storage::disk('public')->assertExists([$item->image, $gallery->path]);
});
