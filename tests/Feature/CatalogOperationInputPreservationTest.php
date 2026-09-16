<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Livewire\Organizations\Brands\Branches\Menu\Catalog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuOperation;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    ParallelTesting::resolveTokenUsing(fn (): string => 'catalog-preservation-'.getmypid());
    Storage::fake('public');
});

/** @return array{User, Branch, Menu, MenuCategory, MenuItem} */
function catalogInputPreservationContext(): array
{
    $owner = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'Input preservation restaurant']);
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $menuA = Menu::factory()->for($branch)->create();
    $categoryA = MenuCategory::factory()->for($menuA)->create();
    $menuB = Menu::factory()->for($branch)->create();
    $categoryB = MenuCategory::factory()->for($menuB)->create();
    $itemB = MenuItem::factory()->for($menuB)->for($categoryB, 'category')->withTranslations()->create();

    return [$owner->fresh(), $branch, $menuA, $categoryA, $itemB];
}

/** @return array<string, mixed> */
function catalogUnfinishedInput(MenuItem $item): array
{
    return [
        'editingItemForm.itemMenuId' => (string) $item->menu_id,
        'editingItemForm.itemCategoryId' => (string) $item->category_id,
        'editingItemForm.itemKitchenDepartmentId' => '',
        'editingItemForm.itemName' => 'Unfinished name',
        'editingItemForm.itemDescription' => 'Unfinished description',
        'editingItemForm.itemPrice' => '17.25',
        'editingItemForm.itemWeight' => '230',
        'editingItemForm.itemVolume' => '0.35',
        'editingItemForm.itemCalories' => '321',
        'editingItemForm.itemSortOrder' => '12',
        'editingItemForm.itemIsAvailable' => false,
        'editingItemForm.itemHiddenUntil' => '2027-01-02T10:30',
        'editingItemForm.itemAllergens' => ['milk'],
        'editingItemForm.itemDietaryLabels' => ['vegetarian'],
        'editingItemForm.itemTranslations' => [
            'en' => ['name' => 'English draft', 'description' => 'English description'],
            'lt' => ['name' => 'Lietuviškas juodraštis', 'description' => 'Aprašymas'],
            'ru' => ['name' => 'Русский черновик', 'description' => 'Описание'],
        ],
        'itemForm.itemMenuId' => (string) $item->menu_id,
        'itemForm.itemCategoryId' => (string) $item->category_id,
        'categoryForm.categoryMenuId' => (string) $item->menu_id,
        'scheduleForm.scheduleMenuId' => (string) $item->menu_id,
    ];
}

test('finishing a large menu deletion preserves another menu editor and its selections', function (): void {
    [$owner, $branch, $menuA, $categoryA, $itemB] = catalogInputPreservationContext();
    MenuItem::factory()->count(101)->for($menuA)->for($categoryA, 'category')->create();
    $component = Livewire::actingAs($owner)->test(Catalog::class, [
        'organizationId' => $branch->organization_id, 'brandId' => $branch->brand_id, 'branchId' => $branch->id,
    ])->call('deleteMenu', $menuA->id)->call('startEditingItem', $itemB->id);
    expect($menuA->items()->count())->toBe(51);
    $input = catalogUnfinishedInput($itemB);
    $component->update(updates: $input, calls: []);
    $component->set('itemImageUploads.'.$itemB->id, [UploadedFile::fake()->image('unfinished.png', 10, 10)]);
    $version = $component->get('editingItemVersion');
    $uploadRequestId = $component->get('itemImageRequestIds')[$itemB->id];

    for ($step = 0; $step < 15 && $component->get('activeCatalogOperationId') !== ''; $step++) {
        $component->call('advanceCatalogOperation')->assertHasNoErrors();
    }

    expect($menuA->fresh()->trashed())->toBeTrue();
    $component->assertSet('activeCatalogOperationId', '')
        ->assertSet('editingItemId', $itemB->id)
        ->assertSet('editingItemVersion', $version);
    expect($component->get('itemImageUploads')[$itemB->id])->toHaveCount(1)
        ->and($component->get('itemImageRequestIds')[$itemB->id])->toBe($uploadRequestId);
    foreach ($input as $property => $value) {
        $component->assertSet($property, $value);
    }
});

test('finishing a duplicate preserves another draft and offers an explicit action to open the copy', function (): void {
    [$owner, $branch, $menuA, $categoryA, $itemB] = catalogInputPreservationContext();
    $source = MenuItem::factory()->for($menuA)->for($categoryA, 'category')->withTranslations()->create();
    $component = Livewire::actingAs($owner)->test(Catalog::class, [
        'organizationId' => $branch->organization_id, 'brandId' => $branch->brand_id, 'branchId' => $branch->id,
    ])->call('duplicateItem', $source->id)->call('startEditingItem', $itemB->id);
    $requestId = $component->get('activeCatalogOperationId');
    $input = catalogUnfinishedInput($itemB);
    $component->update(updates: $input, calls: []);
    $component->set('itemImageUploads.'.$itemB->id, [UploadedFile::fake()->image('unfinished.png', 10, 10)]);
    $version = $component->get('editingItemVersion');
    $uploadRequestId = $component->get('itemImageRequestIds')[$itemB->id];

    for ($step = 0; $step < 15 && $component->get('activeCatalogOperationId') !== ''; $step++) {
        $component->call('advanceCatalogOperation')->assertHasNoErrors();
    }

    $operation = MenuOperation::query()->where('request_id', $requestId)->sole();
    expect($operation->phase->value)->toBe('completed');
    $component->assertSet('editingItemId', $itemB->id)->assertSet('editingItemVersion', $version);
    expect($component->get('itemImageUploads')[$itemB->id])->toHaveCount(1)
        ->and($component->get('itemImageRequestIds')[$itemB->id])->toBe($uploadRequestId);
    foreach ($input as $property => $value) {
        $component->assertSet($property, $value);
    }
    $component->assertSee('wire:click="openCompletedCatalogCopy"', false)
        ->call('openCompletedCatalogCopy')->assertHasNoErrors()
        ->assertSet('editingItemId', $operation->result_id);
});

test('malformed pending upload state renders safely and removal preserves it until save validation', function (mixed $value): void {
    [$owner, $branch, , , $item] = catalogInputPreservationContext();
    $field = 'itemImageUploads.'.$item->id;
    $originalImage = $item->image;
    $component = Livewire::actingAs($owner)->test(Catalog::class, [
        'organizationId' => $branch->organization_id, 'brandId' => $branch->brand_id, 'branchId' => $branch->id,
    ])->call('startEditingItem', $item->id)
        ->update(updates: [$field => $value], calls: [])->assertOk()
        ->call('removePendingItemImage', $item->id, 0)->assertOk()
        ->assertSet($field, $value)
        ->call('saveItemImages', $item->id)->assertHasErrors();

    expect($item->fresh()->image)->toBe($originalImage)
        ->and($item->galleryImages()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
})->with(['scalar string' => 'malformed upload', 'nested array' => [[['malformed upload']]]]);

test('category deletion reconciles vanished or out of scope selections while preserving surviving choices and draft text', function (string $selection): void {
    [$owner, $branch, $menu, $deletedCategory, $otherMenuItem] = catalogInputPreservationContext();
    $survivingCategory = MenuCategory::factory()->for($menu)->create(['name' => 'Surviving category']);
    $selectedId = match ($selection) {
        'deleted' => $deletedCategory->id,
        'surviving' => $survivingCategory->id,
        'other menu' => $otherMenuItem->category_id,
        'other branch' => MenuCategory::factory()->create()->id,
    };
    $component = Livewire::actingAs($owner)->test(Catalog::class, [
        'organizationId' => $branch->organization_id, 'brandId' => $branch->brand_id, 'branchId' => $branch->id,
    ])->set('itemForm.itemMenuId', (string) $menu->id)
        ->set('itemForm.itemCategoryId', (string) $selectedId)
        ->set('categoryForm.categoryMenuId', (string) $menu->id)
        ->set('categoryForm.categoryParentId', (string) $selectedId)
        ->set('itemForm.itemName', 'Unfinished new dish')
        ->set('categoryForm.categoryName', 'Unfinished new category')
        ->call('deleteCategory', $deletedCategory->id)->assertHasNoErrors();

    for ($step = 0; $step < 15 && $component->get('activeCatalogOperationId') !== ''; $step++) {
        $component->call('advanceCatalogOperation')->assertHasNoErrors();
    }

    expect($deletedCategory->fresh()->trashed())->toBeTrue();
    $component->assertSet('activeCatalogOperationId', '')
        ->assertSet('itemForm.itemMenuId', (string) $menu->id)
        ->assertSet('categoryForm.categoryMenuId', (string) $menu->id)
        ->assertSet('itemForm.itemCategoryId', (string) $survivingCategory->id)
        ->assertSet('categoryForm.categoryParentId', $selection === 'surviving' ? (string) $survivingCategory->id : '')
        ->assertSet('itemForm.itemName', 'Unfinished new dish')
        ->assertSet('categoryForm.categoryName', 'Unfinished new category');
})->with(['deleted', 'surviving', 'other menu', 'other branch']);
