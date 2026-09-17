<?php

declare(strict_types=1);

use App\Actions\Branches\UpdateBranchTemporaryClosureAction;
use App\Enums\SystemRole;
use App\Models\AvailabilityCommand;
use App\Models\Branch;
use App\Models\OrganizationUser;
use App\Support\Validation\Availability\BranchLocalDateTime;
use App\Support\Validation\Availability\ScheduleRules;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

function temporalLocalizedError(Closure $operation, string $field, string $key, string $locale, array $parameters = []): void
{
    try {
        $operation();
        test()->fail('The invalid temporal input must be rejected.');
    } catch (ValidationException $exception) {
        $expected = __($key, $parameters, $locale);
        expect($exception->errors())->toHaveKey($field)
            ->and($exception->errors()[$field][0])->toBe($expected)
            ->and($expected)->not->toContain('availability.', 'validation.', ':attribute', ':min', ':max', ':format');
        if ($locale !== 'en') {
            expect($expected)->not->toBe(__($key, $parameters, 'en'));
        }
    }
}

test('local deadlines emit complete translated gap fold format and future errors', function (string $locale): void {
    app()->setLocale($locale);
    foreach ([
        ['2026-03-29T03:30', 'local_datetime'],
        ['2026-10-25T03:30', 'ambiguous_datetime'],
        ['2026-02-30T12:00', 'local_datetime'],
        ['2026-06-01 12:00', 'local_datetime'],
        ['2025-12-31T23:59', 'future_deadline'],
    ] as [$value, $error]) {
        temporalLocalizedError(fn () => Validator::make(['untilLocal' => $value], ['untilLocal' => [new BranchLocalDateTime('Europe/Vilnius', CarbonImmutable::parse('2026-01-01T00:00:00Z'))]])->validate(),
            'untilLocal', 'availability.errors.'.$error, $locale);
    }
})->with(['en', 'lt', 'ru']);

test('weekly and date schedule validation translates actual fields and error parameters', function (string $locale): void {
    app()->setLocale($locale);
    $interval = ['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '18:00'];
    temporalLocalizedError(fn () => ScheduleRules::menu([array_replace($interval, ['ends_at' => '09:00'])]), 'weeklyIntervals.0.ends_at', 'availability.errors.equal_interval', $locale);
    temporalLocalizedError(fn () => ScheduleRules::menu([$interval, $interval]), 'weeklyIntervals', 'branches.opening_hours.errors.overlap', $locale);
    temporalLocalizedError(fn () => ScheduleRules::menu([array_replace($interval, ['day_of_week' => 8])]), 'weeklyIntervals.0.day_of_week', 'validation.between.numeric', $locale,
        ['attribute' => __('availability.fields.day'), 'min' => 1, 'max' => 7]);
    temporalLocalizedError(fn () => ScheduleRules::menu([array_replace($interval, ['starts_at' => '25:00'])]), 'weeklyIntervals.0.starts_at', 'validation.date_format', $locale,
        ['attribute' => __('availability.fields.start'), 'format' => 'H:i']);
    $day = ['day_of_week' => 1, 'is_closed' => false, 'intervals' => [['opens_at' => '09:00', 'closes_at' => '18:00']]];
    temporalLocalizedError(fn () => ScheduleRules::branch([$day, $day], true), 'openingHours.0.day_of_week', 'validation.distinct', $locale, ['attribute' => __('availability.fields.day')]);
    temporalLocalizedError(fn () => ScheduleRules::branch([array_replace($day, ['intervals' => []])], true), 'openingHours.0.intervals', 'availability.errors.interval_required', $locale);
    $exception = ['local_date' => '2026-12-25', 'is_closed' => true, 'intervals' => []];
    temporalLocalizedError(fn () => ScheduleRules::exceptions([$exception, $exception]), 'exceptions.0.local_date', 'validation.distinct', $locale, ['attribute' => __('availability.fields.date')]);
    temporalLocalizedError(fn () => ScheduleRules::exceptions([array_replace($exception, ['local_date' => '25/12/2026'])]), 'exceptions.0.local_date', 'validation.date_format', $locale,
        ['attribute' => __('availability.fields.date'), 'format' => 'Y-m-d']);
    temporalLocalizedError(fn () => ScheduleRules::exceptions([array_replace($exception, ['is_closed' => false, 'intervals' => [['opens_at' => '22:00', 'closes_at' => '02:00']]])]),
        'exceptions.0.intervals', 'availability.errors.date_intervals', $locale);
})->with(['en', 'lt', 'ru']);

test('pause action localizes duration reason timezone and stale errors without persisting', function (string $locale): void {
    $this->seed(SystemPermissionsSeeder::class);
    app()->setLocale($locale);
    $branch = Branch::factory()->create(['timezone' => 'Europe/Vilnius']);
    $actor = OrganizationUser::factory()->forOrganization($branch->organization)->forSystemRole(SystemRole::Owner)->active()->create()->user;
    $action = app(UpdateBranchTemporaryClosureAction::class);
    foreach ([0, 10081] as $duration) {
        $rule = $duration === 0 ? 'min' : 'max';
        temporalLocalizedError(fn () => $action->handle($actor, $branch, true, 'Maintenance', null, 0, 'Europe/Vilnius', (string) Str::uuid(), $duration),
            'durationMinutes', 'validation.'.$rule.'.numeric', $locale, ['attribute' => __('availability.fields.duration'), $rule => $duration === 0 ? 1 : 10080]);
    }
    temporalLocalizedError(fn () => $action->handle($actor, $branch, true, '', null, 0, 'Europe/Vilnius', (string) Str::uuid()), 'reason', 'validation.required', $locale,
        ['attribute' => __('availability.fields.reason')]);
    temporalLocalizedError(fn () => $action->handle($actor, $branch, true, str_repeat('x', 256), null, 0, 'Europe/Vilnius', (string) Str::uuid()), 'reason', 'validation.max.string', $locale,
        ['attribute' => __('availability.fields.reason'), 'max' => 255]);
    temporalLocalizedError(fn () => $action->handle($actor, $branch, true, 'Maintenance', null, 0, 'Not/AZone', (string) Str::uuid()), 'expectedTimezone', 'validation.timezone', $locale,
        ['attribute' => __('availability.fields.timezone')]);
    temporalLocalizedError(fn () => $action->handle($actor, $branch, true, 'Maintenance', null, 1, 'Europe/Vilnius', (string) Str::uuid()), 'expectedVersion', 'availability.errors.stale', $locale);
    temporalLocalizedError(fn () => $action->handle($actor, $branch, true, 'Maintenance', null, 0, 'UTC', (string) Str::uuid()), 'expectedVersion', 'availability.errors.stale', $locale);
    expect($branch->fresh()->pause_version)->toBe(0)->and(AvailabilityCommand::query()->count())->toBe(0);
})->with(['en', 'lt', 'ru']);
