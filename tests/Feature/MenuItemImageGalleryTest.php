<?php

use App\Actions\Menus\AddMenuItemImagesAction;
use App\Actions\Menus\PromoteMenuItemImageAction;
use App\Actions\Menus\RemoveMenuItemGalleryImageAction;
use App\Actions\Menus\RemoveMenuItemImageAction;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemImage;
use App\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    ParallelTesting::resolveTokenUsing(fn (): string => 'menu-gallery-'.getmypid());
    Storage::fake('public');
});

test('multiple uploads assign the first primary and persist ordered secondary images', function () {
    [$branch, $item] = createMenuItemGalleryContext();

    $result = app(AddMenuItemImagesAction::class)->handle($branch, $item, [
        UploadedFile::fake()->image('first.jpg')->size(100),
        UploadedFile::fake()->image('second.png')->size(100),
        UploadedFile::fake()->image('third.webp')->size(100),
    ]);

    expect($result->image)->not->toBeNull()
        ->and($result->galleryImages->pluck('sort_order')->all())->toBe([0, 1])
        ->and($result->galleryImages)->toHaveCount(2)
        ->and(Storage::disk('public')->allFiles())->toHaveCount(3);

    Storage::disk('public')->assertExists($result->image);
    Storage::disk('public')->assertExists($result->galleryImages->pluck('path')->all());
});

test('uploads append after an existing primary and highest secondary order', function () {
    [$branch, $item] = createMenuItemGalleryContext();
    $primaryPath = 'media/existing/primary.jpg';
    $galleryPath = 'media/existing/gallery.jpg';
    $item->update(['image' => $primaryPath]);
    MenuItemImage::factory()->for($item, 'item')->create([
        'path' => $galleryPath,
        'sort_order' => 4,
    ]);
    Storage::disk('public')->put($primaryPath, 'primary');
    Storage::disk('public')->put($galleryPath, 'gallery');

    $result = app(AddMenuItemImagesAction::class)->handle($branch, $item, [
        UploadedFile::fake()->image('fourth.jpg')->size(100),
        UploadedFile::fake()->image('fifth.png')->size(100),
    ]);

    expect($result->image)->toBe($primaryPath)
        ->and($result->galleryImages->pluck('sort_order')->all())->toBe([4, 5, 6])
        ->and($result->galleryImages->pluck('path')->contains($galleryPath))->toBeTrue();
    Storage::disk('public')->assertExists($primaryPath);
});

test('the eight image aggregate limit rejects uploads before storing files', function () {
    [$branch, $item] = createMenuItemGalleryContext();
    $item->update(['image' => 'media/existing/primary.jpg']);
    Storage::disk('public')->put($item->image, 'primary');

    foreach (range(0, 6) as $sortOrder) {
        $path = 'media/existing/gallery-'.$sortOrder.'.jpg';
        MenuItemImage::factory()->for($item, 'item')->create([
            'path' => $path,
            'sort_order' => $sortOrder,
        ]);
        Storage::disk('public')->put($path, 'gallery');
    }

    expect(fn () => app(AddMenuItemImagesAction::class)->handle($branch, $item, [
        UploadedFile::fake()->image('ninth.jpg')->size(100),
    ]))->toThrow(ValidationException::class);

    expect($item->galleryImages()->count())->toBe(7)
        ->and(Storage::disk('public')->allFiles())->toHaveCount(MenuItem::MAX_IMAGES);
});

test('foreign branch items are rejected before any file is stored', function () {
    [$branch] = createMenuItemGalleryContext();
    [, $foreignItem] = createMenuItemGalleryContext();

    expect(fn () => app(AddMenuItemImagesAction::class)->handle($branch, $foreignItem, [
        UploadedFile::fake()->image('foreign.jpg')->size(100),
    ]))->toThrow(InvalidArgumentException::class);

    expect($foreignItem->fresh()->image)->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('direct gallery uploads reject dangerous extensions scriptable content and oversized images', function (UploadedFile $file) {
    [$branch, $item] = createMenuItemGalleryContext();

    expect(fn () => app(AddMenuItemImagesAction::class)->handle($branch, $item, [$file]))
        ->toThrow(ValidationException::class);

    expect($item->fresh()->image)->toBeNull()
        ->and($item->galleryImages()->exists())->toBeFalse()
        ->and(Storage::disk('public')->allFiles())->toBe([]);
})->with([
    'dangerous extension' => fn () => UploadedFile::fake()->image('dish.php')->size(100),
    'scriptable content' => fn () => UploadedFile::fake()->create('dish.svg', 10, 'image/svg+xml'),
    'oversized image' => fn () => UploadedFile::fake()->image('dish.png')->size(3000),
]);

test('persistence failure removes new files while preserving prior gallery state', function () {
    [$branch, $item] = createMenuItemGalleryContext();
    $primaryPath = 'media/existing/primary.jpg';
    $galleryPath = 'media/existing/gallery.jpg';
    $item->update(['image' => $primaryPath]);
    $gallery = MenuItemImage::factory()->for($item, 'item')->create([
        'path' => $galleryPath,
        'sort_order' => 0,
    ]);
    Storage::disk('public')->put($primaryPath, 'primary');
    Storage::disk('public')->put($galleryPath, 'gallery');
    MenuItemImage::creating(function (): never {
        throw new RuntimeException('Simulated gallery persistence failure.');
    });

    try {
        expect(fn () => app(AddMenuItemImagesAction::class)->handle($branch, $item, [
            UploadedFile::fake()->image('new-one.jpg')->size(100),
            UploadedFile::fake()->image('new-two.png')->size(100),
        ]))->toThrow(RuntimeException::class, 'Simulated gallery persistence failure.');
    } finally {
        MenuItemImage::flushEventListeners();
    }

    expect($item->refresh()->image)->toBe($primaryPath)
        ->and($item->galleryImages()->pluck('menu_item_images.id')->all())->toBe([$gallery->id])
        ->and(Storage::disk('public')->allFiles())->toBe([$galleryPath, $primaryPath]);
});

test('a secondary image can become primary without copying or deleting files', function () {
    [$branch, $item] = createMenuItemGalleryContext();
    $primaryPath = 'media/existing/primary.jpg';
    $firstPath = 'media/existing/first.jpg';
    $selectedPath = 'media/existing/selected.jpg';
    $item->update(['image' => $primaryPath]);
    $first = MenuItemImage::factory()->for($item, 'item')->create([
        'path' => $firstPath,
        'sort_order' => 0,
    ]);
    $selected = MenuItemImage::factory()->for($item, 'item')->create([
        'path' => $selectedPath,
        'sort_order' => 5,
    ]);

    foreach ([$primaryPath, $firstPath, $selectedPath] as $path) {
        Storage::disk('public')->put($path, $path);
    }

    $result = app(PromoteMenuItemImageAction::class)->handle($branch, $item, $selected);

    expect($result->image)->toBe($selectedPath)
        ->and($result->galleryImages->pluck('id')->all())->toBe([$first->id, $selected->id])
        ->and($selected->refresh()->path)->toBe($primaryPath)
        ->and($selected->sort_order)->toBe(5)
        ->and(Storage::disk('public')->allFiles())->toHaveCount(3);
});

test('promotion rejects an image belonging to another item or branch', function () {
    [$branch, $item] = createMenuItemGalleryContext();
    [, $otherItem] = createMenuItemGalleryContext();
    $otherImage = MenuItemImage::factory()->for($otherItem, 'item')->create([
        'path' => 'media/foreign/image.jpg',
    ]);
    Storage::disk('public')->put($otherImage->path, 'foreign');

    expect(fn () => app(PromoteMenuItemImageAction::class)->handle($branch, $item, $otherImage))
        ->toThrow(InvalidArgumentException::class);

    expect($item->refresh()->image)->toBeNull()
        ->and($otherImage->refresh()->path)->toBe('media/foreign/image.jpg')
        ->and(Storage::disk('public')->exists($otherImage->path))->toBeTrue();
});

test('removing a secondary image deletes its row before deleting exactly its file', function () {
    [$branch, $item] = createMenuItemGalleryContext();
    $primaryPath = 'media/existing/primary.jpg';
    $keptPath = 'media/existing/kept.jpg';
    $removedPath = 'media/existing/removed.jpg';
    $item->update(['image' => $primaryPath]);
    $kept = MenuItemImage::factory()->for($item, 'item')->create(['path' => $keptPath, 'sort_order' => 0]);
    $removed = MenuItemImage::factory()->for($item, 'item')->create(['path' => $removedPath, 'sort_order' => 1]);

    foreach ([$primaryPath, $keptPath, $removedPath] as $path) {
        Storage::disk('public')->put($path, $path);
    }

    $result = app(RemoveMenuItemGalleryImageAction::class)->handle($branch, $item, $removed);

    expect($result->image)->toBe($primaryPath)
        ->and($result->galleryImages->pluck('id')->all())->toBe([$kept->id])
        ->and(MenuItemImage::query()->whereKey($removed->id)->exists())->toBeFalse();
    Storage::disk('public')->assertMissing($removedPath);
    Storage::disk('public')->assertExists([$primaryPath, $keptPath]);
});

test('removing a primary promotes the lowest ordered secondary and preserves remaining files', function () {
    [$branch, $item] = createMenuItemGalleryContext();
    $primaryPath = 'media/existing/primary.jpg';
    $promotedPath = 'media/existing/promoted.jpg';
    $remainingPath = 'media/existing/remaining.jpg';
    $item->update(['image' => $primaryPath]);
    $remaining = MenuItemImage::factory()->for($item, 'item')->create(['path' => $remainingPath, 'sort_order' => 8]);
    $promoted = MenuItemImage::factory()->for($item, 'item')->create(['path' => $promotedPath, 'sort_order' => 2]);

    foreach ([$primaryPath, $promotedPath, $remainingPath] as $path) {
        Storage::disk('public')->put($path, $path);
    }

    $result = app(RemoveMenuItemImageAction::class)->handle($branch, $item);

    expect($result->image)->toBe($promotedPath)
        ->and($result->galleryImages->pluck('id')->all())->toBe([$remaining->id])
        ->and(MenuItemImage::query()->whereKey($promoted->id)->exists())->toBeFalse();
    Storage::disk('public')->assertMissing($primaryPath);
    Storage::disk('public')->assertExists([$promotedPath, $remainingPath]);
});

test('removing a primary without secondaries clears it and deletes the old file', function () {
    [$branch, $item] = createMenuItemGalleryContext();
    $primaryPath = 'media/existing/primary.jpg';
    $item->update(['image' => $primaryPath]);
    Storage::disk('public')->put($primaryPath, 'primary');

    $result = app(RemoveMenuItemImageAction::class)->handle($branch, $item);

    expect($result->image)->toBeNull()
        ->and($result->galleryImages)->toHaveCount(0);
    Storage::disk('public')->assertMissing($primaryPath);
});

test('secondary persistence failure preserves its database row and file', function () {
    [$branch, $item] = createMenuItemGalleryContext();
    $path = 'media/existing/secondary.jpg';
    $image = MenuItemImage::factory()->for($item, 'item')->create(['path' => $path]);
    Storage::disk('public')->put($path, 'secondary');
    MenuItemImage::deleting(function (): never {
        throw new RuntimeException('Simulated gallery deletion failure.');
    });

    try {
        expect(fn () => app(RemoveMenuItemGalleryImageAction::class)->handle($branch, $item, $image))
            ->toThrow(RuntimeException::class, 'Simulated gallery deletion failure.');
    } finally {
        MenuItemImage::flushEventListeners();
    }

    expect($image->refresh()->path)->toBe($path);
    Storage::disk('public')->assertExists($path);
});

test('primary persistence failure preserves every database path and file', function () {
    [$branch, $item] = createMenuItemGalleryContext();
    $primaryPath = 'media/existing/primary.jpg';
    $secondaryPath = 'media/existing/secondary.jpg';
    $item->update(['image' => $primaryPath]);
    $secondary = MenuItemImage::factory()->for($item, 'item')->create(['path' => $secondaryPath]);
    Storage::disk('public')->put($primaryPath, 'primary');
    Storage::disk('public')->put($secondaryPath, 'secondary');
    MenuItem::updating(function (): never {
        throw new RuntimeException('Simulated primary persistence failure.');
    });

    try {
        expect(fn () => app(RemoveMenuItemImageAction::class)->handle($branch, $item))
            ->toThrow(RuntimeException::class, 'Simulated primary persistence failure.');
    } finally {
        MenuItem::flushEventListeners();
    }

    expect($item->refresh()->image)->toBe($primaryPath)
        ->and($secondary->refresh()->path)->toBe($secondaryPath);
    Storage::disk('public')->assertExists([$primaryPath, $secondaryPath]);
});

/** @return array{Branch, MenuItem} */
function createMenuItemGalleryContext(): array
{
    $organization = Organization::factory()->create();
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $menu = Menu::factory()->for($branch)->create();
    $category = MenuCategory::factory()->for($menu)->create();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create();

    return [$branch, $item];
}
