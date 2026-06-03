<?php

use App\Enums\AreaNodeType;
use App\Models\AreaNode;
use App\Models\Branch;
use Illuminate\Support\Facades\Schema;

test('area nodes table stores nested branch structure fields', function () {
    expect(Schema::hasTable('area_nodes'))->toBeTrue();
    expect(Schema::hasColumns('area_nodes', [
        'branch_id',
        'parent_id',
        'type',
        'name',
        'icon',
        'sort_order',
        'is_active',
        'metadata',
        'deleted_at',
    ]))->toBeTrue();
});

test('area node types include the fixed branch area taxonomy', function () {
    expect(AreaNodeType::values())->toBe([
        'group',
        'floor',
        'hall',
        'terrace',
        'vip_room',
        'bar_area',
        'banquet_hall',
        'room',
        'hotel_area',
        'pickup_area',
        'delivery_area',
        'custom',
    ]);
});

test('area nodes belong to a branch and can be nested', function () {
    $branch = Branch::factory()->create();
    $floor = AreaNode::factory()
        ->for($branch)
        ->create([
            'type' => AreaNodeType::Floor,
            'name' => 'First floor',
            'sort_order' => 10,
            'metadata' => ['public_name' => '1F'],
        ]);
    $hall = AreaNode::factory()
        ->for($branch)
        ->for($floor, 'parent')
        ->create([
            'type' => AreaNodeType::Hall,
            'name' => 'Main hall',
            'sort_order' => 20,
        ]);

    expect($branch->areaNodes()->count())->toBe(2);
    expect($hall->parent->is($floor))->toBeTrue();
    expect($floor->children()->firstOrFail()->is($hall))->toBeTrue();
    expect($floor->fresh()->type)->toBe(AreaNodeType::Floor);
    expect($floor->fresh()->metadata)->toBe(['public_name' => '1F']);
});

test('area nodes support soft delete', function () {
    $areaNode = AreaNode::factory()->create(['name' => 'Temporary terrace']);

    $areaNode->delete();

    expect(AreaNode::query()->whereKey($areaNode->id)->exists())->toBeFalse();
    expect(AreaNode::withTrashed()->whereKey($areaNode->id)->firstOrFail()->trashed())->toBeTrue();
});
