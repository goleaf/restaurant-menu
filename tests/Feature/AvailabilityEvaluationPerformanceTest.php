<?php

declare(strict_types=1);

use App\Enums\MenuStatus;
use App\Models\Branch;
use App\Models\KitchenDepartment;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Services\Availability\AvailabilityEvaluator;
use Carbon\CarbonImmutable;

/** @return array{Branch,Menu,MenuCategory} */
function boundedAvailabilityCatalog(int $items, array $branchState = [], array $itemState = []): array
{
    $branch = Branch::factory()->create($branchState);
    $menu = Menu::factory()->for($branch)->active()->create();
    $category = MenuCategory::factory()->for($menu)->create(['is_active' => true]);
    MenuItem::factory()->for($menu)->for($category, 'category')->count($items)->create($itemState);

    return [$branch, $menu, $category];
}

test('indefinite pauses and wholly stopped catalogs keep a fixed branch read budget', function (bool $paused): void {
    [$branch] = boundedAvailabilityCatalog(201, ['is_temporarily_closed' => $paused, 'temporary_closed_until' => null], ['is_available' => $paused]);
    $instant = CarbonImmutable::parse('2026-09-17T12:00:00Z');
    $queries = countDatabaseQueries(function () use ($branch, $instant, $paused): void {
        $result = app(AvailabilityEvaluator::class)->branch($branch->fresh(), $instant);
        expect($result->acceptsNewOrders)->toBeFalse()->and($result->configurable)->toBeFalse()
            ->and($result->nextOrderableAt)->toBeNull()->and($result->primaryCode())->toBe($paused ? 'branch_paused' : 'no_orderable_items');
    });
    expect($queries)->toBeLessThanOrEqual(8);
})->with(['indefinite pause' => true, 'all items stopped' => false]);

test('one branch scan retains configuration and routing readiness while order admission is paused', function (): void {
    [$branch, $menu, $category] = boundedAvailabilityCatalog(1, ['is_temporarily_closed' => true, 'temporary_closed_until' => null]);
    $department = KitchenDepartment::factory()->for($branch)->create(['is_active' => true]);
    $item = $menu->items()->sole();
    $item->forceFill(['kitchen_department_id' => $department->id])->save();
    $evaluator = app(AvailabilityEvaluator::class);
    $at = CarbonImmutable::parse('2026-09-17T12:00:00Z');
    $result = $evaluator->branchSummary($branch->fresh(), $at);
    expect($result['availability']->primaryCode())->toBe('branch_paused')->and($result['availability']->nextOrderableAt)->toBeNull()
        ->and($result['menu_available'])->toBeTrue()->and($result['routing_ready'])->toBeTrue();
    MenuItem::factory()->for($menu)->for($category, 'category')->create(['is_available' => false]);
    expect($evaluator->branchSummary($branch->fresh(), $at)['routing_ready'])->toBeTrue();
    MenuItem::factory()->for($menu)->for($category, 'category')->create(['is_available' => true]);
    expect($evaluator->branchSummary($branch->fresh(), $at)['routing_ready'])->toBeFalse();
});

test('branch evaluation memoization is confined to one operation and preserves required options and future hiding', function (): void {
    [$branch, $menu] = boundedAvailabilityCatalog(1);
    $item = $menu->items()->sole();
    $group = ModifierGroup::factory()->for($branch)->create(['is_required' => true, 'min_select' => 2, 'max_select' => 2]);
    $option = ModifierOption::factory()->for($group, 'group')->create(['is_available' => true]);
    $item->modifierGroups()->attach($group);
    $at = CarbonImmutable::parse('2026-09-17T12:00:00Z');
    $evaluator = app(AvailabilityEvaluator::class);
    expect($evaluator->branch($branch->fresh(), $at)->acceptsNewOrders)->toBeFalse();
    ModifierOption::factory()->for($group, 'group')->create(['is_available' => true]);
    expect($evaluator->branch($branch->fresh(), $at)->acceptsNewOrders)->toBeTrue();
    $item->forceFill(['hidden_until' => $at->addHour()])->save();
    expect($evaluator->branch($branch->fresh(), $at)->nextOrderableAt?->equalTo($at->addHour()))->toBeTrue();
    $option->forceFill(['is_available' => false])->save();
    expect($evaluator->branch($branch->fresh(), $at)->nextOrderableAt)->toBeNull();
});

test('bounded item batches reuse loaded graphs and keep projected changes out of persistence', function (): void {
    [, $menu] = boundedAvailabilityCatalog(20);
    $items = MenuItem::query()->where('menu_id', $menu->id)->with(AvailabilityEvaluator::itemRelations())->orderBy('id')->limit(20)->get();
    $at = CarbonImmutable::parse('2026-09-17T12:00:00Z');
    $evaluator = app(AvailabilityEvaluator::class);
    $queries = countDatabaseQueries(function () use ($evaluator, $items, $at): void {
        $current = $evaluator->items($items, $at);
        $projected = $evaluator->projectItems($items, $at, 'stop');
        expect($current)->toHaveCount(20)->and($projected)->toHaveCount(20);
        foreach ($items as $item) {
            expect($current[$item->id]->acceptsNewOrders)->toBeTrue()->and($projected[$item->id]->primaryCode())->toBe('item_stopped')
                ->and($item->is_available)->toBeTrue();
        }
    });
    expect($queries)->toBe(0)->and($menu->items()->where('is_available', false)->exists())->toBeFalse();
    $menu->forceFill(['status' => MenuStatus::Draft])->save();
    $fresh = MenuItem::query()->where('menu_id', $menu->id)->with(AvailabilityEvaluator::itemRelations())->orderBy('id')->limit(20)->get();
    expect($evaluator->items($fresh, $at)[$fresh->first()->id]->primaryCode())->toBe('menu_unpublished');
    expect(fn () => $evaluator->items(array_fill(0, 101, $fresh->first()), $at))->toThrow(InvalidArgumentException::class);
});
