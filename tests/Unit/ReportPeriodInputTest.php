<?php

declare(strict_types=1);

use App\Data\Reports\ReportPeriodInput;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

pest()->extend(TestCase::class);

test('explicit report boundaries do not resolve the current calendar day', function (?string $from, ?string $to): void {
    $now = new class('2026-06-01 22:30:00', 'UTC') extends CarbonImmutable
    {
        public int $timezoneConversions = 0;

        public function setTimezone(DateTimeZone|string|int $timeZone): static
        {
            $this->timezoneConversions++;

            return parent::setTimezone($timeZone);
        }
    };

    $range = (new ReportPeriodInput($from, $to))->resolve('Europe/Vilnius', $now);

    expect($range->dateFrom)->toBe('2026-05-01')
        ->and($range->dateTo)->toBe('2026-05-31')
        ->and($now->timezoneConversions)->toBe(0)
        ->and($now->toIso8601String())->toBe('2026-06-01T22:30:00+00:00');
})->with([
    'both boundaries' => ['2026-05-01', '2026-05-31'],
    'start only' => ['2026-05-01', null],
    'end only' => [null, '2026-05-31'],
]);

test('missing report boundaries preserve calendar arithmetic', function (?string $from, ?string $to, string $timezone, string $expectedFrom, string $expectedTo): void {
    $now = CarbonImmutable::parse('2026-06-01 22:30:00', 'UTC');
    $input = new ReportPeriodInput($from, $to);

    $range = $input->resolve($timezone, $now);

    expect($range->dateFrom)->toBe($expectedFrom)
        ->and($range->dateTo)->toBe($expectedTo)
        ->and($range->timezone)->toBe($timezone)
        ->and($input->dateFrom)->toBe($from)
        ->and($input->dateTo)->toBe($to)
        ->and($now->toIso8601String())->toBe('2026-06-01T22:30:00+00:00');
})->with([
    'branch next day' => [null, null, 'Europe/Vilnius', '2026-05-03', '2026-06-02'],
    'branch same day' => [null, null, 'America/Los_Angeles', '2026-05-02', '2026-06-01'],
    'spring DST forward' => ['2026-03-01', null, 'Europe/Vilnius', '2026-03-01', '2026-03-31'],
    'spring DST backward' => [null, '2026-03-31', 'Europe/Vilnius', '2026-03-01', '2026-03-31'],
    'leap month forward' => ['2024-02-01', null, 'UTC', '2024-02-01', '2024-03-02'],
    'leap month backward' => [null, '2024-03-01', 'UTC', '2024-01-31', '2024-03-01'],
    'year boundary' => ['2026-12-15', null, 'UTC', '2026-12-15', '2027-01-14'],
    'skipped local date' => [null, '2011-12-31', 'Pacific/Apia', '2011-12-01', '2011-12-31'],
]);

test('explicit report days retain their exact UTC boundaries across DST', function (string $day, string $start, string $end): void {
    $range = (new ReportPeriodInput($day, $day))->resolve('Europe/Vilnius', CarbonImmutable::parse('2026-06-01', 'UTC'));

    expect($range->startedAt->format('Y-m-d H:i:s.u'))->toBe($start)
        ->and($range->endedAt->format('Y-m-d H:i:s.u'))->toBe($end);
})->with([
    '23 hour day' => ['2026-03-29', '2026-03-28 22:00:00.000000', '2026-03-29 20:59:59.999999'],
    '25 hour day' => ['2026-10-25', '2026-10-24 21:00:00.000000', '2026-10-25 21:59:59.999999'],
]);

test('report period rejection retains the original field and message', function (?string $from, ?string $to, string $field, string $message): void {
    try {
        (new ReportPeriodInput($from, $to))->resolve('Europe/Vilnius', CarbonImmutable::parse('2026-06-01', 'UTC'));
        $this->fail('Invalid report period was accepted.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe([$field => [__($message, [
            'attribute' => __('validation.attributes.date_to'),
            'other' => __('validation.attributes.date_from'),
            'max' => 31,
        ])]]);
    }
})->with([
    'invalid start' => ['2026-02-30', '2026-03-01', 'date_from', 'reports.errors.date'],
    'invalid end' => ['2026-02-01', '2026-02-30', 'date_to', 'reports.errors.date'],
    'invalid start alone' => ['bad', null, 'date_from', 'reports.errors.date'],
    'invalid end alone' => [null, '', 'date_to', 'reports.errors.date'],
    'both invalid' => ['bad', 'bad', 'date_from', 'reports.errors.date'],
    'reversed' => ['2026-06-02', '2026-06-01', 'date_to', 'validation.rules.report_period_order'],
    '32 days' => ['2026-03-01', '2026-04-01', 'date_to', 'validation.rules.report_period_too_long'],
]);

test('report periods are recalculated for each supplied clock and branch timezone', function (): void {
    $input = new ReportPeriodInput(null, null);
    $now = CarbonImmutable::parse('2026-06-01 22:30:00', 'UTC');

    expect($input->resolve('Europe/Vilnius', $now)->dateTo)->toBe('2026-06-02')
        ->and($input->resolve('America/Los_Angeles', $now)->dateTo)->toBe('2026-06-01')
        ->and($input->resolve('Europe/Vilnius', $now->addDay())->dateTo)->toBe('2026-06-03');
});
