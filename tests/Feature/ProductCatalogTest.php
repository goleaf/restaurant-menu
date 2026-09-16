<?php

declare(strict_types=1);

use App\Actions\Menus\SyncMenuItemTranslationsAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\MenuStatus;
use App\Livewire\Organizations\Brands\Branches\Menu\Catalog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemImage;
use App\Models\MenuItemTranslation;
use App\Models\MenuOperation;
use App\Models\Organization;
use App\Models\User;
use App\Services\Menus\CatalogData;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    ParallelTesting::resolveTokenUsing(fn (): string => 'product-catalog-'.getmypid());
    $this->seed(SystemPermissionsSeeder::class);
});

test('catalogue image mutations replay the same rendered request without touching another image', function (string $operation): void {
    Storage::fake('public');
    [$owner, $organization, $brand, $branch, $menu, $category] = productCatalogContext();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['image' => 'media/primary.jpg']);
    $gallery = MenuItemImage::factory()->for($item, 'item')->create(['path' => 'media/second.jpg', 'sort_order' => 0]);
    MenuItemImage::factory()->for($item, 'item')->create(['path' => 'media/third.jpg', 'sort_order' => 1]);
    foreach (['media/primary.jpg', 'media/second.jpg', 'media/third.jpg'] as $path) {
        Storage::disk('public')->put($path, 'owned test image');
    }

    $component = Livewire::actingAs($owner)->test(Catalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->call('startEditingItem', $item->id)
        ->set('editingItemForm.itemDescription', 'Unfinished image editor text');
    $action = match ($operation) {
        'primary' => 'removeItemImage',
        'gallery' => 'removeItemGalleryImage',
        'promote' => 'promoteItemImage',
    };
    $arguments = productCatalogImageArguments($component->html(), $action);
    $requestId = $arguments[count($arguments) - 1];
    expect($requestId)->toMatch('/^[0-9a-f-]{36}$/')
        ->and($arguments[count($arguments) - 2])->toBe(hash('sha256', $operation === 'primary' ? 'media/primary.jpg' : 'media/second.jpg'));
    $snapshot = $component->snapshot;

    $component->call($action, ...$arguments)->assertHasNoErrors();
    $primary = $item->fresh()->image;
    $paths = $item->galleryImages()->pluck('path', 'id')->all();
    $files = Storage::disk('public')->allFiles();
    expect($primary)->toBe($operation === 'gallery' ? 'media/primary.jpg' : 'media/second.jpg');
    $component->snapshot = $snapshot;
    $component->call($action, ...$arguments)->assertHasNoErrors()
        ->assertSet('editingItemForm.itemDescription', 'Unfinished image editor text');

    expect($item->fresh()->image)->toBe($primary)
        ->and($item->galleryImages()->pluck('path', 'id')->all())->toBe($paths)
        ->and(Storage::disk('public')->allFiles())->toBe($files);
})->with(['primary', 'gallery', 'promote']);

test('image cleanup failures preserve editor input and resume through the existing operation controls', function (): void {
    Storage::fake('public');
    [$owner, $organization, $brand, $branch, $menu, $category] = productCatalogContext();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['image' => 'media/old.jpg']);
    MenuItemImage::factory()->for($item, 'item')->create(['path' => 'media/keep.jpg']);
    Storage::disk('public')->put('media/old.jpg', 'old image');
    Storage::disk('public')->put('media/keep.jpg', 'keep image');
    $parameters = ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id];
    $component = Livewire::actingAs($owner)->test(Catalog::class, $parameters)
        ->call('startEditingItem', $item->id)
        ->set('editingItemForm.itemDescription', 'Keep this unsaved description');
    $arguments = productCatalogImageArguments($component->html(), 'removeItemImage');
    $requestId = $arguments[2];
    $disk = Storage::disk('public');
    $failingDisk = Mockery::mock($disk);
    $failingDisk->shouldReceive('delete')->andReturn(false);
    Storage::set('public', $failingDisk);

    $component->call('removeItemImage', ...$arguments)
        ->assertHasErrors('catalogOperation')
        ->assertSet('activeCatalogOperationId', $requestId)
        ->assertSet('catalogOperationPaused', true)
        ->assertSet('editingItemForm.itemDescription', 'Keep this unsaved description')
        ->assertSee(__('menu.operations.resume'));
    expect($item->fresh()->image)->toBe('media/keep.jpg');
    Storage::disk('public')->assertExists('media/old.jpg');

    Storage::set('public', $disk);
    Livewire::actingAs($owner)->test(Catalog::class, $parameters)
        ->assertSet('activeCatalogOperationId', $requestId)
        ->call('resumeCatalogOperation')->assertHasNoErrors()
        ->assertSet('activeCatalogOperationId', '');

    expect($item->fresh()->image)->toBe('media/keep.jpg')
        ->and($menu->fresh()->trashed())->toBeFalse();
    Storage::disk('public')->assertMissing('media/old.jpg');
    Storage::disk('public')->assertExists('media/keep.jpg');
});

test('stale photo confirmations display a conflict without replacing the dish editor input', function (): void {
    Storage::fake('public');
    [$owner, $organization, $brand, $branch, $menu, $category] = productCatalogContext();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['image' => 'media/current.jpg']);
    $component = Livewire::actingAs($owner)->test(Catalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->call('startEditingItem', $item->id)->set('editingItemForm.itemDescription', 'Keep my text');

    $component->call('removeItemImage', $item->id, hash('sha256', 'media/stale.jpg'), (string) Str::uuid())
        ->assertHasErrors('itemImageUploads.'.$item->id)
        ->assertSee(__('uploads.errors.image_changed'))
        ->assertSet('editingItemForm.itemDescription', 'Keep my text');

    expect($item->fresh()->image)->toBe('media/current.jpg');
});

/** @return list<int|string> */
function productCatalogImageArguments(string $html, string $action): array
{
    expect(preg_match('/wire:click="'.preg_quote($action, '/').'\((.*?)\)"/s', $html, $matches))->toBe(1);
    $arguments = array_map(fn (string $value): string => trim($value, " \t\n\r\0\x0B'"), explode(',', html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5)));
    $arguments[0] = (int) $arguments[0];
    if ($action !== 'removeItemImage') {
        $arguments[1] = (int) $arguments[1];
    }

    return $arguments;
}

test('catalogue reads a bounded page instead of hydrating the complete dish inventory', function (): void {
    [, , , $branch, $menu, $category] = productCatalogContext();
    MenuItem::factory()->count(45)->for($menu)->for($category, 'category')->sequence(fn ($sequence) => ['name' => 'Dish '.str_pad((string) $sequence->index, 3, '0', STR_PAD_LEFT)])->create();
    $hydrated = 0;
    MenuItem::retrieved(function () use (&$hydrated): void {
        $hydrated++;
    });

    $data = app(CatalogData::class)->for($branch, (string) $menu->id, (string) $menu->id, '');

    expect(array_sum(array_column($data['menuRows'], 'visible_item_count')))->toBe(24)
        ->and($hydrated)->toBeLessThanOrEqual(25)
        ->and($data['catalogHasMore'])->toBeTrue();
});

test('catalogue search finds translated descriptions and availability filters keep the branch boundary', function (): void {
    [$owner, $organization, $brand, $branch, $menu, $category] = productCatalogContext();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['name' => 'House soup', 'is_available' => false]);
    MenuItemTranslation::factory()->for($item, 'item')->create(['language_code' => 'lt', 'name' => 'Sriuba', 'description' => 'Su baravykais']);
    MenuItem::factory()->for($menu)->for($category, 'category')->create(['name' => 'Bread', 'is_available' => true]);
    [, , , , $foreignMenu, $foreignCategory] = productCatalogContext('Other tenant');
    MenuItem::factory()->for($foreignMenu)->for($foreignCategory, 'category')->create(['name' => 'Foreign baravykai']);

    Livewire::actingAs($owner)->test(Catalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->set('filters.search', 'baravykai')
        ->assertSee('House soup')->assertDontSee('Foreign baravykai')->assertDontSee('Bread')
        ->set('filters.availability', 'available')->assertDontSee('House soup')
        ->call('resetCatalogFilters')->assertSee('Bread')->assertSee('House soup');
});

/** @return array{User, Organization, Brand, Branch, Menu, MenuCategory} */
function productCatalogContext(string $name = 'Product restaurant'): array
{
    $owner = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($owner, ['name' => $name]);
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $menu = Menu::factory()->for($branch)->create(['name' => 'Dinner', 'status' => MenuStatus::Active]);
    $category = MenuCategory::factory()->for($menu)->create(['name' => 'Mains']);

    return [$owner->fresh(), $organization, $brand, $branch, $menu, $category];
}

test('saving one translated dish locale preserves the other persisted languages', function (): void {
    [, , , , $menu, $category] = productCatalogContext();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create();
    foreach (['en' => 'Soup', 'lt' => 'Sriuba', 'ru' => 'Суп'] as $locale => $name) {
        MenuItemTranslation::factory()->for($item, 'item')->create(['language_code' => $locale, 'name' => $name, 'description' => $locale.' text']);
    }

    app(SyncMenuItemTranslationsAction::class)->handle($item, ['lt' => ['name' => 'Nauja sriuba', 'description' => 'Su krapais']]);

    expect($item->translations()->orderBy('language_code')->pluck('name', 'language_code')->all())->toBe(['en' => 'Soup', 'lt' => 'Nauja sriuba', 'ru' => 'Суп']);
});

test('a stale dish editor reports a conflict without overwriting concurrent changes or losing input', function (): void {
    [$owner, $organization, $brand, $branch, $menu, $category] = productCatalogContext();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['name' => 'Soup', 'price_cents' => 1200]);
    foreach (['en' => 'Soup', 'lt' => 'Sriuba', 'ru' => 'Суп'] as $locale => $name) {
        MenuItemTranslation::factory()->for($item, 'item')->create(['language_code' => $locale, 'name' => $name]);
    }
    $component = Livewire::actingAs($owner)->test(Catalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->call('startEditingItem', $item->id)->set('editingItemForm.itemTranslations.lt.name', 'Mano sriuba');
    $item->update(['price_cents' => 1800]);

    $component->call('updateItem')->assertHasErrors('editingItemVersion')->assertSet('editingItemForm.itemTranslations.lt.name', 'Mano sriuba');
    expect($item->fresh()->price_cents)->toBe(1800)
        ->and($item->translations()->where('language_code', 'lt')->value('name'))->toBe('Sriuba');
});

test('catalogue deletion advances in bounded requests and resumes after leaving the page', function (): void {
    [$owner, $organization, $brand, $branch, $menu, $category] = productCatalogContext();
    MenuItem::factory()->count(101)->for($menu)->for($category, 'category')->create();
    $parameters = ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id];

    $component = Livewire::actingAs($owner)->test(Catalog::class, $parameters)
        ->call('deleteMenu', $menu->id)->assertHasNoErrors();

    expect(MenuItem::query()->where('menu_id', $menu->id)->count())->toBe(51)
        ->and($menu->fresh()->trashed())->toBeFalse();
    $requestId = $component->get('activeCatalogOperationId');
    $resumed = Livewire::actingAs($owner)->test(Catalog::class, $parameters)
        ->assertSet('activeCatalogOperationId', $requestId);
    for ($step = 0; $step < 12 && $resumed->get('activeCatalogOperationId') !== ''; $step++) {
        $resumed->call('advanceCatalogOperation')->assertHasNoErrors();
    }

    $resumed->assertSet('activeCatalogOperationId', '');
    expect(Menu::withTrashed()->findOrFail($menu->id)->trashed())->toBeTrue()
        ->and(MenuItem::query()->where('menu_id', $menu->id)->exists())->toBeFalse();
});

test('catalogue image save replays a lost response without adding the same batch twice', function (): void {
    Storage::fake('public');
    [$owner, $organization, $brand, $branch, $menu, $category] = productCatalogContext();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['image' => null]);
    $files = array_map(fn (int $index) => UploadedFile::fake()->image('dish-'.$index.'.jpg', 10, 10), range(1, 8));
    $component = Livewire::actingAs($owner)->test(Catalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->call('startEditingItem', $item->id)
        ->set('editingItemForm.itemDescription', 'Unfinished description')
        ->set('itemImageUploads.'.$item->id, $files);
    $snapshotBeforeSaving = $component->snapshot;

    $component->call('saveItemImages', $item->id)->assertHasNoErrors();
    $storedPaths = Storage::disk('public')->allFiles();
    $component->snapshot = $snapshotBeforeSaving;
    $component->call('saveItemImages', $item->id)->assertHasNoErrors()
        ->assertSet('editingItemForm.itemDescription', 'Unfinished description');

    expect($item->galleryImages()->count())->toBe(7)
        ->and(Storage::disk('public')->allFiles())->toBe($storedPaths);
});

test('permanent image write failure preserves pending uploads and retries the same batch without duplicates', function (): void {
    Storage::fake('public');
    [$owner, $organization, $brand, $branch, $menu, $category] = productCatalogContext();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['image' => 'media/original.jpg']);
    $gallery = MenuItemImage::factory()->for($item, 'item')->create(['path' => 'media/original-secondary.jpg']);
    $disk = Storage::disk('public');
    $disk->put($item->image, 'existing primary');
    $disk->put($gallery->path, 'existing secondary');
    $existingFiles = $disk->allFiles();
    $files = [
        UploadedFile::fake()->image('new-first.jpg', 800, 400),
        UploadedFile::fake()->image('new-second.jpg', 800, 400),
    ];
    $field = 'itemImageUploads.'.$item->id;
    $component = Livewire::actingAs($owner)->test(Catalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->call('startEditingItem', $item->id)
        ->set('editingItemForm.itemDescription', 'Preserve the unsaved dish description')
        ->set($field, $files);
    $requestId = $component->get('itemImageRequestIds.'.$item->id);
    $writes = 0;
    $failingDisk = Mockery::mock($disk);
    $failingDisk->shouldReceive('put')->andReturnUsing(function (string $path, mixed $contents, mixed $options = []) use ($disk, &$writes): bool {
        $writes++;

        return $writes === 4 ? false : $disk->put($path, $contents, $options);
    });
    Storage::set('public', $failingDisk);
    Exceptions::fake();

    $component->call('saveItemImages', $item->id)
        ->assertHasErrors($field)
        ->assertSee(__('uploads.errors.upload_failed'))
        ->assertSet('itemImageRequestIds.'.$item->id, $requestId)
        ->assertSet('editingItemForm.itemDescription', 'Preserve the unsaved dish description')
        ->assertNotDispatched('item-images-saved');

    expect($writes)->toBe(4)
        ->and(array_map(fn (UploadedFile $file): string => $file->getClientOriginalName(), $component->get($field)))->toBe(['new-first.jpg', 'new-second.jpg'])
        ->and($item->fresh()->image)->toBe('media/original.jpg')
        ->and($item->galleryImages()->pluck('path', 'id')->all())->toBe([$gallery->id => 'media/original-secondary.jpg'])
        ->and($disk->allFiles())->toBe($existingFiles)
        ->and(MenuOperation::query()->where('request_id', $requestId)->exists())->toBeFalse();
    Exceptions::assertReported(RuntimeException::class);

    Storage::set('public', $disk);
    $retrySnapshot = $component->snapshot;
    $component->call('saveItemImages', $item->id)->assertHasNoErrors()->assertDispatched('item-images-saved');
    $committedFiles = $disk->allFiles();
    $component->snapshot = $retrySnapshot;
    $component->call('saveItemImages', $item->id)->assertHasNoErrors();

    expect($item->fresh()->image)->toBe('media/original.jpg')
        ->and($item->galleryImages()->count())->toBe(3)
        ->and($gallery->fresh()->path)->toBe('media/original-secondary.jpg')
        ->and($committedFiles)->toHaveCount(6)
        ->and($disk->allFiles())->toBe($committedFiles)
        ->and(MenuOperation::query()->where('request_id', $requestId)->count())->toBe(1);
});

test('committed image uploads finish the picker after an observer callback fails without offering another upload', function (): void {
    Storage::fake('public');
    [$owner, $organization, $brand, $branch, $menu, $category] = productCatalogContext();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['image' => null]);
    $component = Livewire::actingAs($owner)->test(Catalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->call('startEditingItem', $item->id)
        ->set('editingItemForm.itemDescription', 'Still editing this dish')
        ->set('itemImageUploads.'.$item->id, [UploadedFile::fake()->image('committed.jpg', 800, 400)]);
    $requestId = $component->get('itemImageRequestIds.'.$item->id);
    $snapshot = $component->snapshot;
    MenuOperation::created(function (): void {
        DB::afterCommit(fn () => throw new RuntimeException('The observer failed after commit.'));
    });
    Exceptions::fake();

    $component->call('saveItemImages', $item->id)
        ->assertHasNoErrors()
        ->assertDispatched('item-images-saved')
        ->assertSet('itemImageUploads', [])
        ->assertSet('itemImageRequestIds', [])
        ->assertSet('editingItemForm.itemDescription', 'Still editing this dish');

    $committedPath = $item->fresh()->image;
    $committedFiles = Storage::disk('public')->allFiles();
    expect($committedPath)->not->toBeNull()
        ->and($committedFiles)->toHaveCount(2)
        ->and(MenuOperation::query()->where('request_id', $requestId)->firstOrFail()->completed_at)->not->toBeNull();
    Exceptions::assertReported(RuntimeException::class);

    $component->snapshot = $snapshot;
    $component->call('saveItemImages', $item->id)->assertHasNoErrors();

    expect($item->fresh()->image)->toBe($committedPath)
        ->and($item->galleryImages()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe($committedFiles)
        ->and(MenuOperation::query()->where('request_id', $requestId)->count())->toBe(1);
});
