<?php

declare(strict_types=1);

use App\Actions\Media\StoreLocalImageAction;
use App\Actions\Menus\AddMenuItemImagesAction;
use App\Actions\Menus\DeleteMenuAction;
use App\Actions\Menus\DeleteMenuCategoryAction;
use App\Actions\Menus\DeleteMenuItemAction;
use App\Actions\Menus\PromoteMenuItemImageAction;
use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemImage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    ParallelTesting::resolveTokenUsing(fn (): string => 'menu-media-transaction-'.getmypid());
    Storage::fake('public');
});

test('gallery additions remove new files when an enclosing transaction rolls back', function (): void {
    [$branch, , , $item] = menuMediaTransactionContext();

    expect(fn () => DB::transaction(function () use ($branch, $item): never {
        app(AddMenuItemImagesAction::class)->handle($branch, $item, [
            UploadedFile::fake()->image('first.png'),
            UploadedFile::fake()->image('second.png'),
        ]);

        throw new RuntimeException('Later operation failed.');
    }))->toThrow(RuntimeException::class, 'Later operation failed.');

    expect($item->fresh()->image)->toBeNull()
        ->and($item->galleryImages()->exists())->toBeFalse()
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('gallery additions retain committed files when an after commit callback throws', function (): void {
    [$branch, , , $item] = menuMediaTransactionContext();
    MenuItem::saved(function (): void {
        DB::afterCommit(function (): never {
            throw new RuntimeException('After commit failed.');
        });
    });

    expect(fn () => app(AddMenuItemImagesAction::class)->handle($branch, $item, [
        UploadedFile::fake()->image('first.png'),
        UploadedFile::fake()->image('second.png'),
    ]))->toThrow(RuntimeException::class, 'After commit failed.');

    expect($item->fresh()->image)->not->toBeNull()
        ->and($item->galleryImages()->exists())->toBeTrue();
    Storage::disk('public')->assertExists($item->fresh()->image);
    Storage::disk('public')->assertExists($item->galleryImages()->pluck('path')->all());
});

test('menu deletion retains referenced media on outer rollback', function (string $entity): void {
    [, $menu, $category, $item] = menuMediaTransactionContext();
    [$primary, $secondary] = menuMediaTransactionImages($item);

    expect(fn () => DB::transaction(function () use ($entity, $menu, $category, $item): never {
        match ($entity) {
            'item' => app(DeleteMenuItemAction::class)->handle($item),
            'category' => app(DeleteMenuCategoryAction::class)->handle($category),
            'menu' => app(DeleteMenuAction::class)->handle($menu),
        };

        throw new RuntimeException('Rollback deletion.');
    }))->toThrow(RuntimeException::class, 'Rollback deletion.');

    expect($item->fresh())->not->toBeNull()
        ->and($item->galleryImages()->exists())->toBeTrue();
    Storage::disk('public')->assertExists([$primary, $secondary]);
})->with(['item', 'category', 'menu']);

test('menu deletion waits for the outer commit before removing files', function (string $entity): void {
    [, $menu, $category, $item] = menuMediaTransactionContext();
    [$primary, $secondary] = menuMediaTransactionImages($item);

    DB::transaction(function () use ($entity, $menu, $category, $item, $primary, $secondary): void {
        match ($entity) {
            'item' => app(DeleteMenuItemAction::class)->handle($item),
            'category' => app(DeleteMenuCategoryAction::class)->handle($category),
            'menu' => app(DeleteMenuAction::class)->handle($menu),
        };

        expect($item->fresh()->trashed())->toBeTrue()
            ->and($item->galleryImages()->exists())->toBeFalse();
        Storage::disk('public')->assertExists([$primary, $secondary]);
    });

    Storage::disk('public')->assertMissing([$primary, $secondary]);
})->with(['item', 'category', 'menu']);

test('gallery upload cleans an earlier stored file when a later file cannot be stored', function (): void {
    [$branch, , , $item] = menuMediaTransactionContext();
    [$primary, $secondary] = menuMediaTransactionImages($item);
    $attemptedPath = 'media/audit/new.png';

    $this->mock(StoreLocalImageAction::class)->shouldReceive('handle')->twice()
        ->andReturnUsing(function () use ($attemptedPath): string {
            if (Storage::disk('public')->exists($attemptedPath)) {
                throw new RuntimeException('Second image storage failed.');
            }

            Storage::disk('public')->put($attemptedPath, 'new image');

            return $attemptedPath;
        });

    expect(fn () => app(AddMenuItemImagesAction::class)->handle($branch, $item, [
        UploadedFile::fake()->image('first.png'),
        UploadedFile::fake()->image('second.png'),
    ]))->toThrow(RuntimeException::class, 'Second image storage failed.');

    expect($item->fresh()->image)->toBe($primary)
        ->and($item->galleryImages()->sole()->path)->toBe($secondary);
    Storage::disk('public')->assertExists([$primary, $secondary]);
    Storage::disk('public')->assertMissing($attemptedPath);
});

test('menu deletion refuses stale entities that moved outside the supplied parent scope', function (string $entity): void {
    [, $menu, $category, $item] = menuMediaTransactionContext();
    [$otherBranch, $otherMenu, $otherCategory] = menuMediaTransactionContext();
    [$primary, $secondary] = menuMediaTransactionImages($item);

    match ($entity) {
        'item' => MenuItem::query()->whereKey($item->id)->update(['menu_id' => $otherMenu->id, 'category_id' => $otherCategory->id]),
        'category' => MenuCategory::query()->whereKey($category->id)->update(['menu_id' => $otherMenu->id]),
        'menu' => Menu::query()->whereKey($menu->id)->update(['branch_id' => $otherBranch->id]),
    };

    expect(fn () => match ($entity) {
        'item' => app(DeleteMenuItemAction::class)->handle($item),
        'category' => app(DeleteMenuCategoryAction::class)->handle($category),
        'menu' => app(DeleteMenuAction::class)->handle($menu),
    })->toThrow(ModelNotFoundException::class);

    expect($item->fresh()->trashed())->toBeFalse()
        ->and($item->galleryImages()->exists())->toBeTrue();
    Storage::disk('public')->assertExists([$primary, $secondary]);
})->with(['item', 'category', 'menu']);

test('deleting a stale menu item cleans the primary image selected by a later promotion', function (): void {
    [$branch, , , $item] = menuMediaTransactionContext();
    [$primary, $secondary] = menuMediaTransactionImages($item);
    $gallery = $item->galleryImages()->sole();

    app(PromoteMenuItemImageAction::class)->handle($branch, $item, $gallery);
    expect($item->image)->toBe($primary)
        ->and($item->fresh()->image)->toBe($secondary);

    app(DeleteMenuItemAction::class)->handle($item);

    expect($item->fresh()->trashed())->toBeTrue();
    Storage::disk('public')->assertMissing([$primary, $secondary]);
});

/** @return array{Branch, Menu, MenuCategory, MenuItem} */
function menuMediaTransactionContext(): array
{
    $branch = Branch::factory()->create();
    $menu = Menu::factory()->for($branch)->create();
    $category = MenuCategory::factory()->for($menu)->create();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['image' => null]);

    return [$branch, $menu, $category, $item];
}

/** @return array{string, string} */
function menuMediaTransactionImages(MenuItem $item): array
{
    $primary = 'media/audit/primary.png';
    $secondary = 'media/audit/secondary.png';
    $item->update(['image' => $primary]);
    MenuItemImage::factory()->for($item, 'item')->create(['path' => $secondary]);
    Storage::disk('public')->put($primary, 'primary');
    Storage::disk('public')->put($secondary, 'secondary');

    return [$primary, $secondary];
}
