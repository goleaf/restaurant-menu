<?php

declare(strict_types=1);

use App\Actions\Menus\GetGuestMenuForBranchAction;
use App\Actions\Menus\GetMenuAvailabilityStatusAction;
use App\Enums\MenuStatus;
use App\Models\Branch;
use App\Models\BranchScheduleException;
use App\Models\BranchSetting;
use App\Models\Menu;
use App\Models\MenuAvailabilitySchedule;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-14 10:00:00 UTC'));
    $this->branch = Branch::factory()->create(['timezone' => 'UTC']);
    BranchSetting::factory()->for($this->branch)->create();
    $this->menu = Menu::factory()->for($this->branch)->create(['status' => MenuStatus::Active]);
    $this->category = MenuCategory::factory()->for($this->menu)->create(['is_active' => true]);
    $this->item = MenuItem::factory()->for($this->menu)->for($this->category, 'category')->create(['is_available' => true]);
});

test('a warm guest cache expires exactly when a temporary hiding deadline ends', function (): void {
    $this->item->update(['hidden_until' => now()->addSeconds(30)]);
    $action = app(GetGuestMenuForBranchAction::class);
    expect(collect($action->handle($this->branch->id)['categories'][0]['items'])->pluck('id'))->not->toContain($this->item->id);
    $this->travel(30)->seconds();
    expect(collect($action->handle($this->branch->id)['categories'][0]['items'])->pluck('id'))->toContain($this->item->id);
});

test('a warm guest cache cannot cross a menu schedule boundary', function (): void {
    MenuAvailabilitySchedule::factory()->for($this->menu)->create(['day_of_week' => 1, 'starts_at' => '09:00:00', 'ends_at' => '10:01:00']);
    $this->travelTo(CarbonImmutable::parse('2026-09-14 10:00:45 UTC'));
    $action = app(GetGuestMenuForBranchAction::class);
    expect($action->handle($this->branch->id)['availability']['is_available'])->toBeTrue();
    $this->travel(15)->seconds();
    expect($action->handle($this->branch->id)['availability']['is_available'])->toBeFalse();
});

test('impossible required modifier selection makes a visible dish unavailable for new orders', function (): void {
    $group = ModifierGroup::factory()->for($this->branch)->required(2, 2)->create();
    ModifierOption::factory()->for($group, 'modifierGroup')->available()->create();
    ModifierOption::factory()->for($group, 'modifierGroup')->unavailable()->create();
    $this->item->modifierGroups()->attach($group);
    $payload = app(GetGuestMenuForBranchAction::class)->handle($this->branch->id);
    $row = collect($payload['categories'][0]['items'])->firstWhere('id', $this->item->id);
    expect($row)->not->toBeNull()->and($row['is_available'])->toBeFalse();
});

test('an obsolete in-flight menu build cannot republish the current generation after a stop', function (): void {
    $changed = false;
    $id = $this->item->id;
    MenuItem::retrieved(function (MenuItem $item) use (&$changed, $id): void {
        if ($changed || $item->id !== $id) {
            return;
        }
        $changed = true;
        MenuItem::query()->findOrFail($id)->update(['is_available' => false]);
    });
    $action = app(GetGuestMenuForBranchAction::class);
    $action->handle($this->branch->id);
    $payload = $action->handle($this->branch->id);
    $row = collect($payload['categories'][0]['items'])->firstWhere('id', $id);
    expect($changed)->toBeTrue()->and($row['is_available'])->toBeFalse();
});

test('a restaurant pause preserves the visible catalog while disabling new obligations', function (): void {
    $action = app(GetGuestMenuForBranchAction::class);
    $action->handle($this->branch->id);
    $this->branch->update(['is_temporarily_closed' => true]);
    $payload = $action->handle($this->branch->id);
    $row = collect($payload['categories'][0]['items'] ?? [])->firstWhere('id', $this->item->id);
    expect($row)->not->toBeNull()
        ->and($row['is_available'])->toBeFalse()
        ->and($row['availability_reason'])->not->toBeNull()
        ->and($payload['availability']['is_available'])->toBeFalse();
});

test('a cached timed restaurant pause ends exactly at its deadline without a database write', function (): void {
    $until = now()->addSeconds(30);
    $this->branch->update(['is_temporarily_closed' => true, 'temporary_closed_until' => $until]);
    $action = app(GetGuestMenuForBranchAction::class);
    $payload = $action->handle($this->branch->id);
    expect($payload['categories'][0]['items'][0]['is_available'])->toBeFalse();
    $this->travel(30)->seconds();
    $payload = $action->handle($this->branch->id);
    expect($payload['categories'][0]['items'][0]['is_available'])->toBeTrue()
        ->and($this->branch->fresh()->is_temporarily_closed)->toBeTrue()
        ->and($this->branch->fresh()->temporary_closed_until->equalTo($until))->toBeTrue();
});

test('a large guest catalog reuses its temporal decisions with a full year of date exceptions', function (int $count): void {
    BranchScheduleException::factory()->for($this->branch)->count(366)->sequence(...array_map(
        fn (int $offset): array => ['local_date' => now()->addDays($offset)->toDateString(), 'is_closed' => false, 'intervals' => [['opens_at' => '08:00', 'closes_at' => '23:00']]],
        range(1, 366),
    ))->create();
    MenuItem::factory()->for($this->menu)->for($this->category, 'category')->count($count - 1)->create(['is_available' => true]);
    $this->item->update(['is_available' => false]);
    $temporal = app(GetMenuAvailabilityStatusAction::class);
    $evaluations = 0;
    $counter = Mockery::mock(GetMenuAvailabilityStatusAction::class);
    $counter->shouldReceive('handle')->andReturnUsing(function (...$arguments) use ($temporal, &$evaluations): array {
        $evaluations++;

        return $temporal->handle(...$arguments);
    });
    app()->instance(GetMenuAvailabilityStatusAction::class, $counter);
    $payload = app(GetGuestMenuForBranchAction::class)->handle($this->branch->id);
    $rows = collect($payload['categories'][0]['items']);
    expect($rows)->toHaveCount($count)
        ->and($rows->firstWhere('id', $this->item->id)['is_available'])->toBeFalse()
        ->and($rows->where('is_available', true))->toHaveCount($count - 1)
        ->and($evaluations)->toBeLessThanOrEqual(2 + (int) ceil($count / 100));
})->with([40, 101]);
