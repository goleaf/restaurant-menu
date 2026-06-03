<?php

use App\Enums\ServicePointStatus;
use App\Enums\ServicePointType;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\ServicePoint;
use Illuminate\Support\Facades\Schema;

test('service points table stores physical service location fields', function () {
    expect(Schema::hasTable('service_points'))->toBeTrue();
    expect(Schema::hasColumns('service_points', [
        'branch_id',
        'area_node_id',
        'type',
        'name',
        'display_number',
        'internal_code',
        'capacity',
        'icon',
        'status',
        'position_x',
        'position_y',
        'is_active',
        'metadata',
        'deleted_at',
    ]))->toBeTrue();
});

test('service point types include the fixed physical point taxonomy', function () {
    expect(ServicePointType::values())->toBe([
        'table',
        'bar_seat',
        'vip_table',
        'room',
        'booth',
        'sunbed',
        'hotel_room',
        'pickup_window',
        'delivery_point',
        'other',
    ]);
});

test('service point statuses include safe operational states', function () {
    expect(ServicePointStatus::values())->toBe([
        'available',
        'occupied',
        'reserved',
        'unavailable',
        'maintenance',
    ]);
});

test('service points belong to branch and can belong to area node', function () {
    $branch = Branch::factory()->create();
    $areaNode = AreaNode::factory()
        ->for($branch)
        ->create(['name' => 'Main hall']);

    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->for($areaNode)
        ->create([
            'type' => ServicePointType::VipTable,
            'name' => 'VIP table near window',
            'display_number' => 'VIP-1',
            'internal_code' => 'MAIN-VIP-001',
            'capacity' => 6,
            'icon' => 'sparkles',
            'status' => ServicePointStatus::Available,
            'position_x' => 12.50,
            'position_y' => 24.75,
            'metadata' => ['note' => 'Window side'],
        ]);

    expect($branch->servicePoints()->count())->toBe(1);
    expect($areaNode->servicePoints()->firstOrFail()->is($servicePoint))->toBeTrue();
    expect($servicePoint->branch->is($branch))->toBeTrue();
    expect($servicePoint->areaNode->is($areaNode))->toBeTrue();
    expect($servicePoint->fresh()->type)->toBe(ServicePointType::VipTable);
    expect($servicePoint->fresh()->status)->toBe(ServicePointStatus::Available);
    expect($servicePoint->fresh()->capacity)->toBe(6);
    expect($servicePoint->fresh()->position_x)->toBe(12.5);
    expect($servicePoint->fresh()->position_y)->toBe(24.75);
    expect($servicePoint->fresh()->metadata)->toBe(['note' => 'Window side']);
});

test('service points can move between area nodes without changing identity fields', function () {
    $branch = Branch::factory()->create();
    $firstAreaNode = AreaNode::factory()->for($branch)->create(['name' => 'First hall']);
    $secondAreaNode = AreaNode::factory()->for($branch)->create(['name' => 'Terrace']);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->for($firstAreaNode)
        ->create([
            'name' => 'Table 12',
            'display_number' => '12',
            'internal_code' => 'TABLE-12',
        ]);

    $originalId = $servicePoint->id;
    $originalInternalCode = $servicePoint->internal_code;

    $servicePoint->update([
        'area_node_id' => $secondAreaNode->id,
        'name' => 'Terrace table 12',
        'display_number' => 'T-12',
    ]);

    $servicePoint->refresh();

    expect($servicePoint->id)->toBe($originalId);
    expect($servicePoint->internal_code)->toBe($originalInternalCode);
    expect($servicePoint->area_node_id)->toBe($secondAreaNode->id);
    expect($servicePoint->name)->toBe('Terrace table 12');
    expect($servicePoint->display_number)->toBe('T-12');
});

test('service points can exist without an area node', function () {
    $servicePoint = ServicePoint::factory()->create([
        'area_node_id' => null,
        'type' => ServicePointType::PickupWindow,
        'name' => 'Pickup window',
    ]);

    expect($servicePoint->areaNode)->toBeNull();
    expect($servicePoint->fresh()->type)->toBe(ServicePointType::PickupWindow);
});

test('service points support soft delete', function () {
    $servicePoint = ServicePoint::factory()->create(['name' => 'Temporary table']);

    $servicePoint->delete();

    expect(ServicePoint::query()->whereKey($servicePoint->id)->exists())->toBeFalse();
    expect(ServicePoint::withTrashed()->whereKey($servicePoint->id)->firstOrFail()->trashed())->toBeTrue();
});
