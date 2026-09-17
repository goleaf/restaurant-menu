<?php

declare(strict_types=1);

use App\Services\Availability\OpeningIntervalEvaluator;
use Carbon\CarbonImmutable;

test('temporal layers intersect and separate pause expiry from actual reopening', function (): void {
    $at = CarbonImmutable::parse('2026-06-01 08:00', 'Europe/Vilnius');
    $layers = [
        ['weekly' => [['day_of_week' => 1, 'opens_at' => '09:00', 'closes_at' => '18:00']], 'empty_allows' => true, 'exceptions' => []],
        ['weekly' => [['day_of_week' => 1, 'opens_at' => '12:00', 'closes_at' => '14:00']], 'empty_allows' => true, 'exceptions' => []],
    ];
    $result = (new OpeningIntervalEvaluator)->evaluate($layers, 'Europe/Vilnius', $at, $at->setTime(10, 0));
    expect($result['allows_now'])->toBeFalse()
        ->and($result['next_change_at']?->format('H:i'))->toBe('09:00')
        ->and($result['next_orderable_at']?->format('H:i'))->toBe('12:00')
        ->and($at->format('H:i'))->toBe('08:00');
});

test('spring gap never expands collapsed intervals and chooses chronological normalized opening', function (): void {
    $evaluator = new OpeningIntervalEvaluator;
    $layer = ['weekly' => [['day_of_week' => 7, 'opens_at' => '03:30', 'closes_at' => '04:00']], 'empty_allows' => false, 'exceptions' => []];
    $result = $evaluator->evaluate([$layer], 'Europe/Vilnius', CarbonImmutable::parse('2026-03-29 05:00', 'Europe/Vilnius'));
    expect($result['allows_now'])->toBeFalse()->and($result['next_orderable_at']?->toIso8601String())->toBe('2026-04-05T03:30:00+03:00');
    $layer['weekly'] = [
        ['day_of_week' => 7, 'opens_at' => '03:30', 'closes_at' => '03:45'],
        ['day_of_week' => 7, 'opens_at' => '04:00', 'closes_at' => '04:15'],
    ];
    $result = $evaluator->evaluate([$layer], 'Europe/Vilnius', CarbonImmutable::parse('2026-03-29 02:45', 'Europe/Vilnius'));
    expect($result['next_orderable_at']?->toIso8601String())->toBe('2026-03-29T04:00:00+03:00');
});

test('a date exception replaces the entire local date including the previous overnight tail', function (): void {
    $layer = ['weekly' => [['day_of_week' => 6, 'opens_at' => '22:00', 'closes_at' => '06:00']], 'empty_allows' => false,
        'exceptions' => [['local_date' => '2026-10-25', 'is_closed' => true, 'intervals' => []]]];
    $evaluator = new OpeningIntervalEvaluator;
    foreach (['2026-10-25T00:30:00Z', '2026-10-25T01:30:00Z'] as $instant) {
        $result = $evaluator->evaluate([$layer], 'Europe/Vilnius', CarbonImmutable::parse($instant));
        expect($result['allows_now'])->toBeFalse();
    }
    $layer['exceptions'][0] = ['local_date' => '2026-10-25', 'is_closed' => false, 'intervals' => [['opens_at' => '09:00', 'closes_at' => '10:00']]];
    $result = $evaluator->evaluate([$layer], 'Europe/Vilnius', CarbonImmutable::parse('2026-10-25T01:30:00Z'));
    expect($result['next_orderable_at']?->toIso8601String())->toBe('2026-10-25T09:00:00+02:00');
});

test('temporal evaluator preserves exclusive endpoints and both occurrences of an autumn repeated hour', function (): void {
    $layer = ['weekly' => [['day_of_week' => 6, 'opens_at' => '22:00', 'closes_at' => '04:00']], 'empty_allows' => false, 'exceptions' => []];
    foreach (['2026-10-25T00:30:00Z', '2026-10-25T01:30:00Z'] as $instant) {
        expect((new OpeningIntervalEvaluator)->evaluate([$layer], 'Europe/Vilnius', CarbonImmutable::parse($instant))['allows_now'])->toBeTrue();
    }
    expect((new OpeningIntervalEvaluator)->evaluate([$layer], 'Europe/Vilnius', CarbonImmutable::parse('2026-10-25T02:00:00Z'))['allows_now'])->toBeFalse();
});

test('empty unrestricted schedules and permanently closed schedules do not invent horizon boundaries', function (): void {
    $at = CarbonImmutable::parse('2026-06-01T09:00:00Z');
    $evaluator = new OpeningIntervalEvaluator;
    $open = $evaluator->evaluate([['weekly' => [], 'empty_allows' => true, 'exceptions' => []]], 'UTC', $at);
    $closed = $evaluator->evaluate([['weekly' => [], 'empty_allows' => false, 'exceptions' => []]], 'UTC', $at);
    $paused = $evaluator->evaluate([['weekly' => [], 'empty_allows' => true, 'exceptions' => []]], 'UTC', $at, indefinitelyPaused: true);
    expect($open['allows_now'])->toBeTrue()->and($open['current_closes_at'])->toBeNull()->and($open['next_change_at'])->toBeNull()
        ->and($closed['next_orderable_at'])->toBeNull()->and($paused['next_orderable_at'])->toBeNull();
});

test('a distant explicit reopening and a long timed pause are evaluated without a fake weekly horizon', function (): void {
    $at = CarbonImmutable::parse('2026-06-01T09:00:00Z');
    $evaluator = new OpeningIntervalEvaluator;
    $layer = ['weekly' => [], 'empty_allows' => false, 'exceptions' => [['local_date' => '2028-12-24', 'is_closed' => false, 'intervals' => [['opens_at' => '10:00', 'closes_at' => '12:00']]]]];
    $result = $evaluator->evaluate([$layer], 'UTC', $at);
    expect($result['next_orderable_at']?->toIso8601String())->toBe('2028-12-24T10:00:00+00:00');
    $layer = ['weekly' => [['day_of_week' => 1, 'opens_at' => '09:00', 'closes_at' => '18:00']], 'empty_allows' => false, 'exceptions' => []];
    $result = $evaluator->evaluate([$layer], 'UTC', $at, CarbonImmutable::parse('2026-07-07T10:00:00Z'));
    expect($result['next_orderable_at']?->toIso8601String())->toBe('2026-07-13T09:00:00+00:00');
});

test('recurring fold boundaries keep the first occurrence used by existing local schedules', function (): void {
    $layer = ['weekly' => [['day_of_week' => 7, 'opens_at' => '03:30', 'closes_at' => '04:00']], 'empty_allows' => false, 'exceptions' => []];
    $evaluator = new OpeningIntervalEvaluator;
    $before = $evaluator->evaluate([$layer], 'Europe/Vilnius', CarbonImmutable::parse('2026-10-25T00:29:00Z'));
    expect($before['next_orderable_at']?->toIso8601String())->toBe('2026-10-25T03:30:00+03:00');
    foreach (['2026-10-25T00:30:00Z', '2026-10-25T01:30:00Z'] as $instant) {
        expect($evaluator->evaluate([$layer], 'Europe/Vilnius', CarbonImmutable::parse($instant))['allows_now'])->toBeTrue();
    }
    expect($evaluator->evaluate([$layer], 'Europe/Vilnius', CarbonImmutable::parse('2026-10-25T02:00:00Z'))['allows_now'])->toBeFalse();
});
