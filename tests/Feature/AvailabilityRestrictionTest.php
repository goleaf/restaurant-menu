<?php

declare(strict_types=1);

use App\Enums\MenuStatus;
use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Services\Availability\AvailabilityEvaluator;
use Carbon\CarbonImmutable;

/** @return array{Branch, Menu, MenuItem} */
function availabilityRestrictionGraph(): array
{
    $branch = Branch::factory()->create(['timezone' => 'Europe/Vilnius', 'is_active' => true]);
    $menu = Menu::factory()->for($branch)->create(['status' => MenuStatus::Active]);
    $category = MenuCategory::factory()->for($menu)->create(['is_active' => true]);
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['is_available' => true]);

    return [$branch, $menu, $item];
}

test('availability keeps manual stop temporary hiding and restaurant pause independent', function (): void {
    [$branch, , $item] = availabilityRestrictionGraph();
    $instant = CarbonImmutable::parse('2026-09-17T12:00:00Z');
    $branch->update(['is_temporarily_closed' => true, 'temporary_closed_until' => $instant->addHour()]);
    $item->update(['is_available' => false, 'hidden_until' => $instant->addMinutes(30)]);
    $evaluator = app(AvailabilityEvaluator::class);
    $result = $evaluator->item($item->fresh(), $instant);

    expect($result->visible)->toBeFalse()->and($result->acceptsNewOrders)->toBeFalse()
        ->and(array_column($result->toArray()['reasons'], 'code'))->toContain('branch_paused', 'item_stopped', 'item_hidden')
        ->and($result->evaluatedAt->equalTo($instant))->toBeTrue();

    $after = $evaluator->item($item->fresh(), $instant->addHours(2));
    expect($after->visible)->toBeTrue()->and($after->acceptsNewOrders)->toBeFalse()
        ->and(array_column($after->toArray()['reasons'], 'code'))->toBe(['item_stopped'])
        ->and($item->fresh()->is_available)->toBeFalse()->and($branch->fresh()->is_temporarily_closed)->toBeTrue();
});

test('configuration feasibility distinguishes variants required groups and optional unavailable options', function (): void {
    [$branch, , $item] = availabilityRestrictionGraph();
    $instant = CarbonImmutable::parse('2026-09-17T12:00:00Z');
    $evaluator = app(AvailabilityEvaluator::class);
    $optional = ModifierGroup::factory()->for($branch)->create(['is_required' => false, 'min_select' => 0]);
    ModifierOption::factory()->for($optional, 'group')->create(['is_available' => false]);
    $item->modifierGroups()->attach($optional);
    expect($evaluator->item($item->fresh(), $instant)->configurable)->toBeTrue();

    $required = ModifierGroup::factory()->for($branch)->create(['is_required' => true, 'min_select' => 2, 'max_select' => 3]);
    ModifierOption::factory()->for($required, 'group')->create(['is_available' => true]);
    $item->modifierGroups()->attach($required);
    expect($evaluator->item($item->fresh(), $instant)->primaryCode())->toBe('required_options_unavailable');

    $required->update(['is_required' => false, 'min_select' => 0]);
    MenuItemVariant::factory()->for($item, 'item')->create(['is_available' => false]);
    expect($evaluator->item($item->fresh(), $instant)->primaryCode())->toBe('variants_unavailable');
});

test('availability preview changes only the selected in-memory restriction without persistence', function (): void {
    [, , $item] = availabilityRestrictionGraph();
    $instant = CarbonImmutable::parse('2026-09-17T12:00:00Z');
    $item->update(['is_available' => false, 'hidden_until' => $instant->addHour()]);
    $result = app(AvailabilityEvaluator::class)->projectItem($item, $instant, 'resume');
    expect($result->acceptsNewOrders)->toBeFalse()->and($result->primaryCode())->toBe('item_hidden')
        ->and($item->fresh()->is_available)->toBeFalse();
});
