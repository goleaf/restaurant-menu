<?php

declare(strict_types=1);

use App\Actions\Menus\ApplyCatalogBulkAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\MenuStatus;
use App\Enums\SystemPermission;
use App\Livewire\Organizations\Brands\Branches\Menu\Catalog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\KitchenDepartment;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemImage;
use App\Models\MenuItemTranslation;
use App\Models\MenuOperation;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\PermissionUserOverride;
use App\Models\User;
use App\Services\Menus\CatalogData;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    ParallelTesting::resolveTokenUsing(fn (): string => 'catalog-bulk-'.getmypid());
    $this->seed(SystemPermissionsSeeder::class);
});

/** @return array{User, Organization, Brand, Branch, Menu, MenuCategory} */
function catalogBulkContext(): array
{
    $owner = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'Catalog laboratory']);
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $menu = Menu::factory()->for($branch)->create(['status' => MenuStatus::Active]);
    $category = MenuCategory::factory()->for($menu)->create(['is_active' => true]);

    return [$owner->fresh(), $organization, $brand, $branch, $menu, $category];
}

/** @return list<array{id: int, version: string}> */
function catalogBulkSelection(MenuItem ...$items): array
{
    return array_map(fn (MenuItem $item): array => ['id' => $item->id, 'version' => $item->fresh()->load('translations')->contentFingerprint()], $items);
}

/** @return array{search: string, availability: string, menu: string, quality: string, page: int} */
function catalogBulkFilters(Menu $menu): array
{
    return ['search' => '', 'availability' => '', 'menu' => (string) $menu->id, 'quality' => '', 'page' => 1];
}

test('quality filters apply before pagination and remain tenant scoped', function (string $quality): void {
    [, , , $branch, $menu, $category] = catalogBulkContext();
    $ready = MenuItem::factory()->count(25)->for($menu)->for($category, 'category')->create(['image' => 'media/ready.jpg', 'description' => 'Prepared to order']);
    foreach ($ready as $item) {
        foreach (['en', 'lt', 'ru'] as $locale) {
            MenuItemTranslation::factory()->for($item, 'item')->create(['language_code' => $locale, 'name' => 'Ready '.$locale]);
        }
    }
    $missing = MenuItem::factory()->for($menu)->for($category, 'category')->create(['name' => 'Needs attention', 'sort_order' => 9999, 'image' => null, 'description' => null]);
    if ($quality === 'publication') {
        $category->update(['is_active' => false]);
    }
    $data = app(CatalogData::class)->for($branch, '', '', '', quality: $quality);
    $ids = array_merge(...array_column($data['menuRows'], 'items'));
    expect(array_column($ids, 'id'))->when($quality !== 'publication', fn ($expectation) => $expectation->toBe([$missing->id]))
        ->when($quality === 'publication', fn ($expectation) => $expectation->toHaveCount(24));
})->with(['photo', 'description', 'translations', 'publication']);

test('bulk availability is atomic and replays a lost response through an owned receipt', function (): void {
    [$owner, , , $branch, $menu, $category] = catalogBulkContext();
    $items = MenuItem::factory()->count(2)->for($menu)->for($category, 'category')->create(['is_available' => true]);
    $selection = catalogBulkSelection(...$items);
    $requestId = (string) Str::uuid();
    $action = app(ApplyCatalogBulkAction::class);
    expect($action->handle($owner, $branch, $selection, catalogBulkFilters($menu), 'unavailable', '', $requestId))->toBe(2);
    $items->first()->fresh()->update(['is_available' => true]);
    expect($action->handle($owner, $branch, $selection, catalogBulkFilters($menu), 'unavailable', '', $requestId))->toBe(2)
        ->and($items->first()->fresh()->is_available)->toBeTrue()
        ->and(MenuOperation::query()->where('request_id', $requestId)->count())->toBe(1);
});

test('bulk selection cannot include another page or a foreign tenant', function (bool $foreign): void {
    [$owner, , , $branch, $menu, $category] = catalogBulkContext();
    MenuItem::factory()->count(24)->for($menu)->for($category, 'category')->create(['sort_order' => 0]);
    $item = $foreign ? MenuItem::factory()->create(['is_available' => true]) : MenuItem::factory()->for($menu)->for($category, 'category')->create(['sort_order' => 9999, 'is_available' => true]);
    expect(fn () => app(ApplyCatalogBulkAction::class)->handle($owner, $branch, catalogBulkSelection($item), catalogBulkFilters($menu), 'unavailable', '', (string) Str::uuid()))
        ->toThrow($foreign ? AuthorizationException::class : ValidationException::class);
    expect($item->fresh()->is_available)->toBeTrue();
})->with([false, true]);

test('stale selections and moved dishes veto the complete batch', function (bool $moved): void {
    [$owner, , , $branch, $menu, $category] = catalogBulkContext();
    $items = MenuItem::factory()->count(2)->for($menu)->for($category, 'category')->create(['is_available' => true]);
    $selection = catalogBulkSelection(...$items);
    $items->last()->update($moved ? ['category_id' => MenuCategory::factory()->for($menu)->create()->id] : ['price_cents' => 3456]);
    expect(fn () => app(ApplyCatalogBulkAction::class)->handle($owner, $branch, $selection, catalogBulkFilters($menu), 'unavailable', '', (string) Str::uuid()))->toThrow(ValidationException::class);
    expect($items->first()->fresh()->is_available)->toBeTrue()->and(MenuOperation::query()->count())->toBe(0);
})->with([false, true]);

test('archive keeps source images gallery and order identities and can replay', function (): void {
    Storage::fake('public');
    [$owner, , , $branch, $menu, $category] = catalogBulkContext();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['image' => 'media/keep.jpg']);
    $image = MenuItemImage::factory()->for($item, 'item')->create(['path' => 'media/gallery.jpg']);
    Storage::disk('public')->put('media/keep.jpg', 'source');
    Storage::disk('public')->put('media/gallery.jpg', 'gallery');
    $selection = catalogBulkSelection($item);
    $requestId = (string) Str::uuid();
    $action = app(ApplyCatalogBulkAction::class);
    $action->handle($owner, $branch, $selection, catalogBulkFilters($menu), 'archive', '', $requestId);
    expect($action->handle($owner, $branch, $selection, catalogBulkFilters($menu), 'archive', '', $requestId))->toBe(1)
        ->and($item->fresh()->trashed())->toBeTrue()->and($image->fresh())->not->toBeNull();
    Storage::disk('public')->assertExists(['media/keep.jpg', 'media/gallery.jpg']);
});

test('bulk moves categories only inside the selected menu and rejects name collisions', function (): void {
    [$owner, , , $branch, $menu, $category] = catalogBulkContext();
    $target = MenuCategory::factory()->for($menu)->create();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['name' => 'Soup']);
    $action = app(ApplyCatalogBulkAction::class);
    $action->handle($owner, $branch, catalogBulkSelection($item), catalogBulkFilters($menu), 'move', (string) $target->id, (string) Str::uuid());
    expect($item->fresh()->category_id)->toBe($target->id);
    MenuItem::factory()->for($menu)->for($category, 'category')->create(['name' => 'Soup']);
    expect(fn () => $action->handle($owner, $branch, catalogBulkSelection($item), catalogBulkFilters($menu), 'move', (string) $category->id, (string) Str::uuid()))->toThrow(ValidationException::class);
    $foreignCategory = MenuCategory::factory()->create();
    expect(fn () => $action->handle($owner, $branch, catalogBulkSelection($item), catalogBulkFilters($menu), 'move', (string) $foreignCategory->id, (string) Str::uuid()))->toThrow(ValidationException::class);
});

test('catalogue page selection is exactly bounded and filter changes clear the selection without clearing drafts', function (): void {
    [$owner, $organization, $brand, $branch, $menu, $category] = catalogBulkContext();
    MenuItem::factory()->count(26)->for($menu)->for($category, 'category')->create();
    $component = Livewire::actingAs($owner)->test(Catalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->set('itemDescription', 'Unsaved new dish')->call('selectCatalogPage');
    expect($component->get('selectedCatalogVersions'))->toHaveCount(24);
    $component->set('filters.search', 'Narrow')->assertSet('selectedCatalogVersions', [])->assertSet('itemDescription', 'Unsaved new dish');
});

test('a rejected required save rolls back every selected item and the receipt', function (): void {
    [$owner, , , $branch, $menu, $category] = catalogBulkContext();
    $items = MenuItem::factory()->count(2)->for($menu)->for($category, 'category')->create(['is_available' => true]);
    $selection = catalogBulkSelection(...$items);
    MenuItem::updating(fn (MenuItem $item): ?bool => $item->id === $items->last()->id ? false : null);
    expect(fn () => app(ApplyCatalogBulkAction::class)->handle($owner, $branch, $selection, catalogBulkFilters($menu), 'unavailable', '', (string) Str::uuid()))->toThrow(RuntimeException::class);
    expect($items->first()->fresh()->is_available)->toBeTrue()->and($items->last()->fresh()->is_available)->toBeTrue()
        ->and(MenuOperation::query()->count())->toBe(0);
});

test('bulk direct callers cannot bypass raw input validation or batch bounds', function (string $invalid): void {
    [$owner, , , $branch, $menu, $category] = catalogBulkContext();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['is_available' => true]);
    $selection = catalogBulkSelection($item);
    $operation = 'unavailable';
    $filters = catalogBulkFilters($menu);
    match ($invalid) {
        'boolean' => $selection[0]['id'] = true,
        'array' => $selection[0]['id'] = [$item->id],
        'extra' => $selection[0]['unexpected'] = 'value',
        'size' => $selection = array_fill(0, 25, $selection[0]),
        'operation' => $operation = ['archive'],
        'page' => $filters['page'] = true,
        'quality' => $filters['quality'] = '../other-component',
    };
    expect(fn () => app(ApplyCatalogBulkAction::class)->handle($owner, $branch, $selection, $filters, $operation, '', (string) Str::uuid()))->toThrow(ValidationException::class);
    expect($item->fresh()->is_available)->toBeTrue();
})->with(['boolean', 'array', 'extra', 'size', 'operation', 'page', 'quality']);

test('bulk permissions are checked again after loading and on completed replay', function (bool $replay): void {
    [$owner, $organization, $brand, $branch, $menu, $category] = catalogBulkContext();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['is_available' => true]);
    $component = Livewire::actingAs($owner)->test(Catalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->call('selectCatalogPage')->set('bulk.operation', 'unavailable');
    $snapshot = $component->snapshot;
    if ($replay) {
        $component->call('applyCatalogBulk')->assertHasNoErrors();
        $item->fresh()->update(['is_available' => true]);
    }
    $organization->users()->updateExistingPivot($owner->id, ['status' => 'suspended']);
    $component->snapshot = $snapshot;
    $component->call('applyCatalogBulk')->assertForbidden();
    expect($item->fresh()->is_available)->toBeTrue();
})->with([false, true]);

test('archive requires explicit confirmation and lost response replay preserves other drafts', function (): void {
    [$owner, $organization, $brand, $branch, $menu, $category] = catalogBulkContext();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create();
    $component = Livewire::actingAs($owner)->test(Catalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->set('itemDescription', 'Keep my new dish')->call('selectCatalogPage')->set('bulk.operation', 'archive')
        ->call('applyCatalogBulk')->assertHasErrors('bulk.confirmArchive');
    expect($item->fresh()->trashed())->toBeFalse();
    $component->set('bulk.confirmArchive', true);
    $snapshot = $component->snapshot;
    $component->call('applyCatalogBulk')->assertHasNoErrors()->assertSet('itemDescription', 'Keep my new dish');
    $component->snapshot = $snapshot;
    $component->call('applyCatalogBulk')->assertHasNoErrors()->assertSet('itemDescription', 'Keep my new dish');
    expect($item->fresh()->trashed())->toBeTrue()->and(MenuOperation::query()->count())->toBe(1);
});

test('bulk fails safely when the selected dish has an unfinished editor', function (): void {
    [$owner, $organization, $brand, $branch, $menu, $category] = catalogBulkContext();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['is_available' => true]);
    Livewire::actingAs($owner)->test(Catalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->call('startEditingItem', $item->id)->set('editingItemDescription', 'Keep this editor')->call('selectCatalogPage')
        ->set('bulk.operation', 'unavailable')->call('applyCatalogBulk')->assertHasErrors('bulkSelection')
        ->assertSet('editingItemDescription', 'Keep this editor');
    expect($item->fresh()->is_available)->toBeTrue();
});

test('catalogue rows do not load galleries or modifier groups until the editor opens', function (): void {
    [$owner, , , $branch, $menu, $category] = catalogBulkContext();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create();
    MenuItemImage::factory()->count(3)->for($item, 'item')->sequence(fn ($sequence) => ['sort_order' => $sequence->index])->create();
    $imagesRetrieved = 0;
    MenuItemImage::retrieved(function () use (&$imagesRetrieved): void {
        $imagesRetrieved++;
    });
    app(CatalogData::class)->for($branch, '', '', '');
    expect($imagesRetrieved)->toBe(0);
    $data = app(CatalogData::class)->editingItem($branch, $item->id);
    expect($imagesRetrieved)->toBe(3)->and($data['images'])->toHaveCount(3);
});

test('invalid URL filter shapes render bounded safe defaults', function (): void {
    [$owner, $organization, $brand, $branch] = catalogBulkContext();
    Livewire::actingAs($owner)->test(Catalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->set('filters.page', ['bad'])->set('filters.search', ['bad'])->set('filters.menuId', true)->set('filters.quality', ['bad'])
        ->assertOk()->assertViewHas('catalogPageNumber', 1)->assertViewHas('catalogQualityValue', '');
});

test('revoking availability permission after page selection blocks the batch', function (): void {
    [$owner, $organization, $brand, $branch, $menu, $category] = catalogBulkContext();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['is_available' => true]);
    $component = Livewire::actingAs($owner)->test(Catalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->call('selectCatalogPage')->set('bulk.operation', 'unavailable');
    $permission = Permission::query()->where('code', SystemPermission::ChangeAvailability->value)->firstOrFail();
    PermissionUserOverride::factory()->forUser($owner)->forPermission($permission)->denied()->create();
    $component->call('applyCatalogBulk')->assertForbidden();
    expect($item->fresh()->is_available)->toBeTrue();
});

test('publication review finds draft menus and inactive preparation departments', function (): void {
    [, , , $branch, $menu, $category] = catalogBulkContext();
    $department = KitchenDepartment::factory()->for($branch)->create(['is_active' => false]);
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['kitchen_department_id' => $department->id]);
    $query = fn () => app(CatalogData::class)->filteredItemQuery($branch, quality: 'publication')->pluck('id')->all();
    expect($query())->toBe([$item->id]);
    $department->update(['is_active' => true]);
    expect($query())->toBe([]);
    $menu->forceFill(['status' => MenuStatus::Draft])->save();
    expect($query())->toBe([$item->id]);
});

test('list hydration removes two relationship queries on the same bounded fixture', function (): void {
    [, , , $branch, $menu, $category] = catalogBulkContext();
    $items = MenuItem::factory()->count(24)->for($menu)->for($category, 'category')->create();
    foreach ($items as $item) {
        MenuItemImage::factory()->for($item, 'item')->create();
    }
    $service = app(CatalogData::class);
    $fullQueries = countDatabaseQueries(fn () => $service->filteredItemQuery($branch)->with(['galleryImages', 'modifierGroups'])->limit(24)->get());
    $compactQueries = countDatabaseQueries(fn () => $service->filteredItemQuery($branch)->limit(24)->get());
    expect($fullQueries - $compactQueries)->toBe(2);
});

test('an identical bundled form POST replays the same bulk receipt after its response is lost', function (): void {
    [$owner, $organization, $brand, $branch, $menu, $category] = catalogBulkContext();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create();
    $component = Livewire::actingAs($owner)->test(Catalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->call('selectCatalogPage');
    $snapshot = $component->snapshot;
    $updates = ['bulk.operation' => 'archive', 'bulk.confirmArchive' => true];
    $calls = [['method' => 'applyCatalogBulk', 'params' => [], 'path' => '']];
    $component->update(calls: $calls, updates: $updates)->assertHasNoErrors();
    $component->snapshot = $snapshot;
    $component->update(calls: $calls, updates: $updates)->assertHasNoErrors();
    expect($item->fresh()->trashed())->toBeTrue()->and(MenuOperation::query()->count())->toBe(1);
});

test('initial catalogue snapshot holds one page fingerprint and rejects a changed page before selection', function (): void {
    [$owner, $organization, $brand, $branch, $menu, $category] = catalogBulkContext();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create();
    $component = Livewire::actingAs($owner)->test(Catalog::class, [
        'organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id,
    ]);
    expect($component->snapshot['data'])->not->toHaveKey('catalogPageVersions');
    $component->assertSet('selectedCatalogVersions', []);
    $item->update(['name' => 'Changed after render']);
    $component->call('selectCatalogPage')->assertHasErrors('bulkSelection')->assertSet('selectedCatalogVersions', []);
});
