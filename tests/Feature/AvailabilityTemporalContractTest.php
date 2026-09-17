<?php

declare(strict_types=1);

use App\Actions\Branches\GetBranchOpeningStatusAction;
use App\Actions\Menus\GetMenuAvailabilityStatusAction;
use App\Models\Branch;
use App\Models\BranchOpeningHour;
use App\Models\BranchScheduleException;
use App\Models\Menu;
use App\Models\MenuAvailabilitySchedule;
use App\Support\Validation\Availability\BranchLocalDateTime;
use App\Support\Validation\Availability\ScheduleRules;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

test('a pause ending outside opening hours exposes the boundary separately from the next orderable instant', function (): void {
    $branch = Branch::factory()->create(['timezone' => 'Europe/Vilnius', 'is_temporarily_closed' => true,
        'temporary_closed_reason' => 'Maintenance', 'temporary_closed_until' => '2026-06-01 17:00:00'])->refresh();
    BranchOpeningHour::factory()->for($branch)->create(['day_of_week' => 1, 'opens_at' => '09:00', 'closes_at' => '18:00']);
    $status = app(GetBranchOpeningStatusAction::class)->handle($branch, CarbonImmutable::parse('2026-06-01 19:00', 'Europe/Vilnius'));
    expect($status['can_accept_orders'])->toBeFalse()
        ->and($status['next_change_at'])->toBe('2026-06-01T20:00:00+03:00')
        ->and($status['next_orderable_at'])->toBe('2026-06-08T09:00:00+03:00')
        ->and($status['next_opens_at'])->toBe($status['next_orderable_at']);
});

test('menu availability shares branch DST occurrence rules and intersects restaurant opening', function (): void {
    $branch = Branch::factory()->create(['timezone' => 'Europe/Vilnius']);
    $menu = Menu::factory()->for($branch)->create();
    MenuAvailabilitySchedule::factory()->for($menu)->create(['day_of_week' => 7, 'starts_at' => '03:30', 'ends_at' => '04:00']);
    $status = app(GetMenuAvailabilityStatusAction::class)->handle($menu, CarbonImmutable::parse('2026-03-29 05:00', 'Europe/Vilnius'));
    expect($status['is_available'])->toBeFalse()->and($status['next_available_at'])->toBe('2026-04-05T03:30:00+03:00');
    BranchOpeningHour::factory()->for($branch)->create(['day_of_week' => 7, 'opens_at' => '05:00', 'closes_at' => '06:00']);
    $status = app(GetMenuAvailabilityStatusAction::class)->handle($menu->fresh(), CarbonImmutable::parse('2026-03-29 05:00', 'Europe/Vilnius'));
    expect($status['next_orderable_at'])->toBeNull()->and($status['is_available'])->toBeFalse();
});

test('date closure overrides an overnight tail while an empty menu schedule remains unrestricted', function (): void {
    $branch = Branch::factory()->create(['timezone' => 'Europe/Vilnius']);
    $menu = Menu::factory()->for($branch)->create();
    BranchOpeningHour::factory()->for($branch)->create(['day_of_week' => 6, 'opens_at' => '22:00', 'closes_at' => '06:00']);
    BranchScheduleException::factory()->for($branch)->create(['local_date' => '2026-10-25', 'is_closed' => true, 'intervals' => []]);
    $status = app(GetMenuAvailabilityStatusAction::class)->handle($menu, CarbonImmutable::parse('2026-10-25T01:30:00Z'));
    expect($status['is_available'])->toBeFalse()->and($status['reason_codes'])->toContain('branch_schedule_closed');
});

test('one off local deadlines reject nonexistent repeated and malformed times', function (string $value): void {
    $validator = Validator::make(['untilLocal' => $value], ['untilLocal' => [new BranchLocalDateTime('Europe/Vilnius')]]);
    expect($validator->fails())->toBeTrue();
})->with(['2026-03-29T03:30', '2026-10-25T03:30', '2026-02-30T12:00', '2026-06-01 12:00', '2026-06-01T12:00Z']);

test('one off local deadlines preserve a real unambiguous branch instant', function (): void {
    $validator = Validator::make(['untilLocal' => '2026-06-01T12:00'], ['untilLocal' => [new BranchLocalDateTime('Europe/Vilnius')]]);
    expect($validator->fails())->toBeFalse()
        ->and(BranchLocalDateTime::parse('2026-06-01T12:00', 'Europe/Vilnius')->utc()->toIso8601String())->toBe('2026-06-01T09:00:00+00:00');
});

test('legacy equal branch and menu intervals retain their next day closing meaning', function (): void {
    $branch = Branch::factory()->create(['timezone' => 'Europe/Vilnius']);
    $menu = Menu::factory()->for($branch)->create();
    BranchOpeningHour::factory()->for($branch)->create(['day_of_week' => 1, 'opens_at' => '09:00', 'closes_at' => '09:00']);
    MenuAvailabilitySchedule::factory()->for($menu)->create(['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '09:00']);
    $instant = CarbonImmutable::parse('2026-06-02 08:59', 'Europe/Vilnius');
    expect(app(GetBranchOpeningStatusAction::class)->handle($branch, $instant)['can_accept_orders'])->toBeTrue()
        ->and(app(GetMenuAvailabilityStatusAction::class)->handle($menu, $instant)['is_available'])->toBeTrue();
    $closing = $instant->setTime(9, 0);
    expect(app(GetBranchOpeningStatusAction::class)->handle($branch, $closing)['can_accept_orders'])->toBeFalse()
        ->and(app(GetMenuAvailabilityStatusAction::class)->handle($menu, $closing)['is_available'])->toBeFalse();
});

test('date exception DST errors keep the original interval index after overlap analysis', function (): void {
    $exceptions = [['local_date' => '2026-03-29', 'is_closed' => false, 'intervals' => [
        ['opens_at' => '12:00', 'closes_at' => '14:00'],
        ['opens_at' => '03:30', 'closes_at' => '05:00'],
    ]]];
    try {
        ScheduleRules::exceptions($exceptions, 'Europe/Vilnius');
        $this->fail('The nonexistent local boundary must be rejected.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('exceptions.0.intervals.1.opens_at')->not->toHaveKey('exceptions.0.intervals.0.opens_at');
    }
});
