<?php

declare(strict_types=1);

use App\Actions\Branches\GetBranchOpeningStatusAction;
use App\Models\Branch;
use App\Models\BranchOpeningHour;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    app()->setLocale('lt');
});

test('a spring gap cannot turn a collapsed daytime interval into an overnight opening', function (string $closesAt): void {
    $branch = Branch::factory()->create(['timezone' => 'Europe/Vilnius'])->refresh();
    BranchOpeningHour::factory()->for($branch)->create([
        'day_of_week' => 7,
        'opens_at' => '03:30',
        'closes_at' => $closesAt,
    ]);

    $queries = countDatabaseQueries(function () use ($branch): void {
        $status = app(GetBranchOpeningStatusAction::class)->handle(
            $branch,
            CarbonImmutable::parse('2026-03-29 05:00:00', 'Europe/Vilnius'),
        );

        expect($status['is_open'])->toBeFalse()
            ->and($status['can_accept_orders'])->toBeFalse()
            ->and($status['closes_at'])->toBeNull()
            ->and($status['next_opens_at'])->toBe('2026-04-05T03:30:00+03:00');
    });

    expect($queries)->toBe(2);
})->with(['reversed duration' => '04:00', 'zero duration' => '04:30']);

test('next opening selects the earliest actual time when a spring gap reorders stored intervals', function (): void {
    $branch = Branch::factory()->create(['timezone' => 'Europe/Vilnius'])->refresh();
    BranchOpeningHour::factory()->for($branch)->create([
        'day_of_week' => 7,
        'opens_at' => '03:30',
        'closes_at' => '03:45',
        'sort_order' => 10,
    ]);
    BranchOpeningHour::factory()->for($branch)->create([
        'day_of_week' => 7,
        'opens_at' => '04:00',
        'closes_at' => '04:15',
        'sort_order' => 20,
    ]);

    $queries = countDatabaseQueries(function () use ($branch): void {
        $status = app(GetBranchOpeningStatusAction::class)->handle(
            $branch,
            CarbonImmutable::parse('2026-03-29 02:45:00', 'Europe/Vilnius'),
        );

        expect($status['is_open'])->toBeFalse()
            ->and($status['next_opens_at'])->toBe('2026-03-29T04:00:00+03:00');
    });

    expect($queries)->toBe(2);
});

test('next opening ignores a collapsed spring gap interval and finds the later valid interval', function (string $closesAt): void {
    $branch = Branch::factory()->create(['timezone' => 'Europe/Vilnius'])->refresh();
    BranchOpeningHour::factory()->for($branch)->create([
        'day_of_week' => 7,
        'opens_at' => '03:30',
        'closes_at' => $closesAt,
        'sort_order' => 10,
    ]);
    BranchOpeningHour::factory()->for($branch)->create([
        'day_of_week' => 7,
        'opens_at' => '05:00',
        'closes_at' => '06:00',
        'sort_order' => 20,
    ]);

    $status = app(GetBranchOpeningStatusAction::class)->handle(
        $branch,
        CarbonImmutable::parse('2026-03-29 02:45:00', 'Europe/Vilnius'),
    );

    expect($status['is_open'])->toBeFalse()
        ->and($status['next_opens_at'])->toBe('2026-03-29T05:00:00+03:00');
})->with(['reversed duration' => '04:00', 'zero duration' => '04:30']);

test('a spring gap opening retains normalization when its interval still has a positive duration', function (): void {
    $branch = Branch::factory()->create(['timezone' => 'Europe/Vilnius'])->refresh();
    BranchOpeningHour::factory()->for($branch)->create([
        'day_of_week' => 7,
        'opens_at' => '03:30',
        'closes_at' => '05:00',
    ]);
    $now = CarbonImmutable::parse('2026-03-29 01:45:00', 'UTC');

    $status = app(GetBranchOpeningStatusAction::class)->handle($branch, $now);

    expect($status['is_open'])->toBeTrue()
        ->and($status['can_accept_orders'])->toBeTrue()
        ->and($status['closes_at'])->toBe('05:00')
        ->and($status['timezone'])->toBe('Europe/Vilnius')
        ->and($now->toIso8601String())->toBe('2026-03-29T01:45:00+00:00');
});

test('an overnight interval constructs its closing wall time on the next date before normalization', function (): void {
    $branch = Branch::factory()->create(['timezone' => 'Europe/Vilnius'])->refresh();
    BranchOpeningHour::factory()->for($branch)->create([
        'day_of_week' => 7,
        'opens_at' => '03:30',
        'closes_at' => '03:00',
    ]);

    $action = app(GetBranchOpeningStatusAction::class);
    $open = $action->handle($branch, CarbonImmutable::parse('2026-03-30 02:45:00', 'Europe/Vilnius'));
    $closed = $action->handle($branch, CarbonImmutable::parse('2026-03-30 03:15:00', 'Europe/Vilnius'));

    expect($open['is_open'])->toBeTrue()
        ->and($open['closes_at'])->toBe('03:00')
        ->and($closed['is_open'])->toBeFalse()
        ->and($closed['next_opens_at'])->toBe('2026-04-05T03:30:00+03:00');
});

test('an overnight interval remains open across the spring transition until its exclusive closing time', function (string $utcTime, bool $isOpen): void {
    $branch = Branch::factory()->create(['timezone' => 'Europe/Vilnius'])->refresh();
    BranchOpeningHour::factory()->for($branch)->create([
        'day_of_week' => 6,
        'opens_at' => '22:00',
        'closes_at' => '04:00',
    ]);

    $status = app(GetBranchOpeningStatusAction::class)->handle($branch, CarbonImmutable::parse($utcTime, 'UTC'));

    expect($status['is_open'])->toBe($isOpen)
        ->and($status['can_accept_orders'])->toBe($isOpen)
        ->and($status['closes_at'])->toBe($isOpen ? '04:00' : null);
})->with([
    'before skipped hour' => ['2026-03-29 00:30:00', true],
    'at closing time after skipped hour' => ['2026-03-29 01:00:00', false],
]);

test('an overnight interval includes both occurrences of the repeated autumn hour', function (string $utcTime): void {
    $branch = Branch::factory()->create(['timezone' => 'Europe/Vilnius'])->refresh();
    BranchOpeningHour::factory()->for($branch)->create([
        'day_of_week' => 6,
        'opens_at' => '22:00',
        'closes_at' => '04:00',
    ]);

    $status = app(GetBranchOpeningStatusAction::class)->handle($branch, CarbonImmutable::parse($utcTime, 'UTC'));

    expect($status['is_open'])->toBeTrue()
        ->and($status['closes_at'])->toBe('04:00');
})->with(['first 03:30' => '2026-10-25 00:30:00', 'second 03:30' => '2026-10-25 01:30:00']);
