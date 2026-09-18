<?php

declare(strict_types=1);

use App\Enums\ServicePointType;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\RestaurantOnboarding;
use App\Models\ServicePoint;
use App\Models\StructureCreationReceipt;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('preserves legacy parents content permanent qr and history through the schema upgrade', function (string $state): void {
    $organization = Organization::factory()->create();
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $area = AreaNode::factory()->for($branch)->create();
    $point = ServicePoint::factory()->for($branch)->for($area, 'areaNode')->create(['type' => ServicePointType::Table, 'is_active' => false]);
    $qr = QrCode::factory()->for($point, 'servicePoint')->disabled()->create();
    $menu = Menu::factory()->for($branch)->draft()->create(['sort_order' => 9]);
    $category = MenuCategory::factory()->for($menu)->create(['sort_order' => 7]);
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->withDistinctTranslations()->unavailable()
        ->create(['description' => 'Preserve real content', 'image' => 'media/fixture/dish.webp', 'sort_order' => 11]);
    $setup = RestaurantOnboarding::factory()->for($organization)->for($brand)->for($branch)->create([
        'completed_at' => $state === 'partial' ? null : now()->subMonth(),
        'area_node_id' => $area->id, 'expected_service_point_count' => 1,
        'menu_id' => $state === 'partial' ? null : $menu->id,
        'menu_category_id' => $state === 'partial' ? null : $category->id,
        'menu_item_id' => $state === 'partial' ? null : $item->id,
    ]);
    $setup->servicePoints()->attach($point, ['position' => 1]);
    if ($state === 'archived') {
        $branch->delete();
        $brand->delete();
        $organization->delete();
    }
    $columns = ['user_id', 'organization_id', 'brand_id', 'branch_id', 'area_node_id', 'expected_service_point_count', 'menu_id', 'menu_category_id', 'menu_item_id', 'completed_at', 'created_at', 'updated_at'];
    $before = $setup->fresh()->only($columns);
    $records = collect([$organization, $brand, $branch, $area, $point, $qr, $menu, $category, $item]);
    $attributes = $records->map(fn ($model): array => $model->fresh()->getAttributes())->all();
    $translations = $item->translations()->orderBy('id')->get()->map->getAttributes()->all();
    $pivot = $setup->servicePoints()->firstOrFail()->pivot->getAttributes();
    $migration = require database_path('migrations/2026_09_17_100214_allow_multiple_restaurant_setup_attempts.php');
    $migration->down();
    $migration->up();
    expect($setup->fresh()->only(array_keys($before)))->toEqual($before)->and($setup->fresh()->setup_version)->toBe(0);
    expect($records->map(fn ($model): array => $model->fresh()->getAttributes())->all())->toBe($attributes)
        ->and($item->translations()->orderBy('id')->get()->map->getAttributes()->all())->toBe($translations)
        ->and($setup->servicePoints()->firstOrFail()->pivot->getAttributes())->toBe($pivot);
})->with(['completed', 'partial', 'archived']);

it('allows multiple private attempts under one brand but keeps one attempt per restaurant', function (): void {
    $actor = User::factory()->create();
    $brand = Brand::factory()->create();
    $first = Branch::factory()->for($brand)->create(['organization_id' => $brand->organization_id]);
    $second = Branch::factory()->for($brand)->create(['organization_id' => $brand->organization_id]);
    $data = ['user_id' => $actor->id, 'organization_id' => $brand->organization_id, 'brand_id' => $brand->id];
    RestaurantOnboarding::factory()->create([...$data, 'branch_id' => $first->id]);
    RestaurantOnboarding::factory()->create([...$data, 'branch_id' => $second->id]);
    expect($actor->restaurantOnboardings()->count())->toBe(2);
    expect(fn () => RestaurantOnboarding::factory()->create([...$data, 'branch_id' => $first->id]))->toThrow(QueryException::class);
});

it('retains creation receipts instead of silently dropping replay protection on rollback', function (): void {
    $key = (string) Str::uuid();
    $setup = RestaurantOnboarding::factory()->create(['creation_key' => $key, 'creation_hash' => hash('sha256', 'fixture')]);
    $migration = require database_path('migrations/2026_09_17_100214_allow_multiple_restaurant_setup_attempts.php');
    expect(fn () => $migration->down())->toThrow(RuntimeException::class);
    expect($setup->fresh()->creation_key)->toBe($key);
});

it('adds and rolls back the receipt organization index without changing replay records', function (): void {
    $receipt = StructureCreationReceipt::factory()->create();
    $before = $receipt->fresh()->getAttributes();
    $migration = require database_path('migrations/2026_09_17_222506_add_organization_index_to_structure_creation_receipts.php');
    $hasIndex = fn (): bool => collect(Schema::getIndexes('structure_creation_receipts'))
        ->contains(fn (array $index): bool => $index['columns'] === ['organization_id']);

    expect($hasIndex())->toBeTrue();
    $migration->down();
    expect($hasIndex())->toBeFalse()->and($receipt->fresh()->getAttributes())->toBe($before);
    $migration->up();
    expect($hasIndex())->toBeTrue()->and($receipt->fresh()->getAttributes())->toBe($before);
});
