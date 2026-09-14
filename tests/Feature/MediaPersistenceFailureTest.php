<?php

declare(strict_types=1);

use App\Actions\Branches\UpdateBranchCoverImageAction;
use App\Actions\Branches\UpdateBranchLogoAction;
use App\Actions\Brands\UpdateBrandLogoAction;
use App\Actions\Menus\AddMenuItemImagesAction;
use App\Actions\Menus\PromoteMenuItemImageAction;
use App\Actions\Menus\RemoveMenuItemGalleryImageAction;
use App\Actions\Menus\RemoveMenuItemImageAction;
use App\Actions\Organizations\UpdateOrganizationLogoAction;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemImage;
use App\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    ParallelTesting::resolveTokenUsing(fn (): string => 'media-persistence-'.getmypid());
    Storage::fake('public');
});

dataset('entity image operations', [
    'organization replacement' => [Organization::class, UpdateOrganizationLogoAction::class, 'logo_path', true],
    'organization removal' => [Organization::class, UpdateOrganizationLogoAction::class, 'logo_path', false],
    'brand replacement' => [Brand::class, UpdateBrandLogoAction::class, 'logo_path', true],
    'brand removal' => [Brand::class, UpdateBrandLogoAction::class, 'logo_path', false],
    'branch replacement' => [Branch::class, UpdateBranchLogoAction::class, 'logo_path', true],
    'branch removal' => [Branch::class, UpdateBranchLogoAction::class, 'logo_path', false],
    'cover replacement' => [Branch::class, UpdateBranchCoverImageAction::class, 'cover_image_path', true],
]);

test('rejected entity image writes preserve the persisted reference and original file', function (
    string $modelClass, string $actionClass, string $attribute, bool $replace, string $event,
): void {
    $original = 'media/persistence/original.png';
    $model = $modelClass::factory()->create([$attribute => $original]);
    Storage::disk('public')->put($original, 'original');
    $modelClass::$event(fn (): bool => false);

    expect(fn () => app($actionClass)->handle(
        $model, $replace ? UploadedFile::fake()->image('replacement.png') : null,
    ))->toThrow(RuntimeException::class);

    expect($model->fresh()->getAttribute($attribute))->toBe($original)
        ->and(Storage::disk('public')->allFiles())->toBe([$original]);
})->with('entity image operations')->with(['saving', 'updating']);

test('committed entity replacements survive a persistence after commit exception', function (
    string $modelClass, string $actionClass, string $attribute,
): void {
    $original = 'media/persistence/original.png';
    $model = $modelClass::factory()->create([$attribute => $original]);
    Storage::disk('public')->put($original, 'original');
    $modelClass::saved(function (): void {
        DB::afterCommit(function (): never {
            throw new RuntimeException('Committed observer failed.');
        });
    });

    expect(fn () => app($actionClass)->handle($model, UploadedFile::fake()->image('replacement.png')))
        ->toThrow(RuntimeException::class, 'Committed observer failed.');

    $committed = $model->fresh()->getAttribute($attribute);
    expect($committed)->not->toBe($original)->not->toBeNull();
    Storage::disk('public')->assertExists($committed);
})->with([
    [Organization::class, UpdateOrganizationLogoAction::class, 'logo_path'],
    [Brand::class, UpdateBrandLogoAction::class, 'logo_path'],
    [Branch::class, UpdateBranchLogoAction::class, 'logo_path'],
    [Branch::class, UpdateBranchCoverImageAction::class, 'cover_image_path'],
]);

test('image removal rolls back when a required model write is vetoed', function (string $operation): void {
    [$branch, $item, $image] = mediaPersistenceGallery();
    $primary = $item->image;
    $secondary = $image->path;

    if ($operation === 'primary save') {
        MenuItem::saving(fn (): bool => false);
    } else {
        MenuItemImage::deleting(fn (): bool => false);
    }

    expect(fn () => $operation === 'gallery delete'
        ? app(RemoveMenuItemGalleryImageAction::class)->handle($branch, $item, $image)
        : app(RemoveMenuItemImageAction::class)->handle($branch, $item))
        ->toThrow(RuntimeException::class);

    expect($item->fresh()->image)->toBe($primary)
        ->and($image->fresh()?->path)->toBe($secondary);
    Storage::disk('public')->assertExists([$primary, $secondary]);
})->with(['primary save', 'promoted delete', 'gallery delete']);

test('gallery promotion rolls back both references when a required model write is vetoed', function (string $operation): void {
    [$branch, $item, $image] = mediaPersistenceGallery();

    if ($operation === 'gallery delete') {
        $item->update(['image' => null]);
        MenuItemImage::deleting(fn (): bool => false);
    } elseif ($operation === 'gallery save') {
        MenuItemImage::saving(fn (): bool => false);
    } else {
        MenuItem::saving(fn (): bool => false);
    }

    $primary = $item->image;
    $secondary = $image->path;
    expect(fn () => app(PromoteMenuItemImageAction::class)->handle($branch, $item, $image))
        ->toThrow(RuntimeException::class);

    expect($item->fresh()->image)->toBe($primary)
        ->and($image->fresh()?->path)->toBe($secondary);
    Storage::disk('public')->assertExists(['media/persistence/primary.png', $secondary]);
})->with(['primary save', 'gallery save', 'gallery delete']);

test('gallery additions roll back all newly stored paths when a model rejects persistence', function (string $operation): void {
    [$branch, $item, $image] = mediaPersistenceGallery();

    if ($operation === 'primary save') {
        $item->update(['image' => null]);
        MenuItem::saving(fn (): bool => false);
    } else {
        $attempts = 0;
        MenuItemImage::creating(function () use (&$attempts): bool {
            return ++$attempts < 2;
        });
    }

    $primary = $item->image;
    $originalFiles = Storage::disk('public')->allFiles();

    expect(fn () => app(AddMenuItemImagesAction::class)->handle($branch, $item, [
        UploadedFile::fake()->image('first.png'), UploadedFile::fake()->image('second.png'),
    ]))->toThrow(RuntimeException::class);

    expect($item->fresh()->image)->toBe($primary)
        ->and($item->galleryImages()->pluck('path')->all())->toBe([$image->path])
        ->and(Storage::disk('public')->allFiles())->toBe($originalFiles);
})->with(['primary save', 'second gallery create']);

test('gallery promotion rejects a stale image that now belongs to another item', function (bool $foreignBranch): void {
    [$branch, $item, $image] = mediaPersistenceGallery();
    $otherItem = $foreignBranch
        ? MenuItem::factory()->create()
        : MenuItem::factory()->create(['menu_id' => $item->menu_id, 'category_id' => $item->category_id]);
    MenuItemImage::query()->whereKey($image->id)->update(['menu_item_id' => $otherItem->id]);
    $originalFiles = Storage::disk('public')->allFiles();

    expect(fn () => app(PromoteMenuItemImageAction::class)->handle($branch, $item, $image))
        ->toThrow(InvalidArgumentException::class);

    expect($item->fresh()->image)->toBe($item->image)
        ->and($otherItem->fresh()->image)->toBe($otherItem->image)
        ->and($image->fresh()->menu_item_id)->toBe($otherItem->id)
        ->and($image->fresh()->path)->toBe($image->path)
        ->and(Storage::disk('public')->allFiles())->toBe($originalFiles);
})->with(['same branch' => false, 'foreign branch' => true]);

/** @return array{Branch, MenuItem, MenuItemImage} */
function mediaPersistenceGallery(): array
{
    $branch = Branch::factory()->create();
    $menu = Menu::factory()->for($branch)->create();
    $category = MenuCategory::factory()->for($menu)->create();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create([
        'image' => 'media/persistence/primary.png',
    ]);
    $image = MenuItemImage::factory()->for($item, 'item')->create([
        'path' => 'media/persistence/secondary.png',
    ]);
    Storage::disk('public')->put($item->image, 'primary');
    Storage::disk('public')->put($image->path, 'secondary');

    return [$branch, $item, $image];
}
