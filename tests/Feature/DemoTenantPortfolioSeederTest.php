<?php

use App\Models\AreaNode;
use App\Models\BranchSetting;
use App\Models\MenuItem;
use App\Models\ServicePoint;
use Database\Seeders\DemoTenantPortfolioSeeder;
use Database\Seeders\SystemPermissionsSeeder;

test('tenant portfolio keeps canonical JSON on initial and repeated seeds', function (int $runs): void {
    $this->seed(SystemPermissionsSeeder::class);

    for ($run = 0; $run < $runs; $run++) {
        $this->seed(DemoTenantPortfolioSeeder::class);
    }

    $settings = BranchSetting::query()->select(['id', 'service_modes'])->get();
    $items = MenuItem::query()->select(['id', 'allergens', 'dietary_labels'])->orderBy('id')->get();
    $areas = AreaNode::query()->select(['id', 'metadata'])->get();
    $tables = ServicePoint::query()->select(['id', 'metadata'])->get();

    expect([
        'service_modes' => $settings->map(fn (BranchSetting $row): mixed => json_decode($row->getRawOriginal('service_modes'), true, flags: JSON_THROW_ON_ERROR))->all(),
        'allergens' => $items->map(fn (MenuItem $row): mixed => json_decode($row->getRawOriginal('allergens'), true, flags: JSON_THROW_ON_ERROR))->all(),
        'dietary_labels' => $items->map(fn (MenuItem $row): mixed => json_decode($row->getRawOriginal('dietary_labels'), true, flags: JSON_THROW_ON_ERROR))->all(),
        'area_metadata' => $areas->map(fn (AreaNode $row): mixed => json_decode($row->getRawOriginal('metadata'), true, flags: JSON_THROW_ON_ERROR))->all(),
        'table_metadata' => $tables->map(fn (ServicePoint $row): mixed => json_decode($row->getRawOriginal('metadata'), true, flags: JSON_THROW_ON_ERROR))->all(),
    ])->toBe([
        'service_modes' => [['dine_in'], ['dine_in']],
        'allergens' => [['gluten', 'milk'], ['milk']],
        'dietary_labels' => [[], []],
        'area_metadata' => array_fill(0, 2, ['demo_fixture' => 'tenant-portfolio']),
        'table_metadata' => array_fill(0, 4, ['demo_fixture' => 'tenant-portfolio']),
    ]);
})->with(['initial seed' => 1, 'repeated seed' => 2]);
