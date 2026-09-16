<?php

declare(strict_types=1);

use App\Support\Validation\Branches\OpeningHoursRules;
use App\Support\Validation\NonOverlappingOpeningHours;
use Illuminate\Support\Facades\Validator;

test('opening hours overlap rule permits adjacent midnight intervals and ignores closed days', function (): void {
    $hours = [
        ['day_of_week' => 7, 'is_closed' => false, 'intervals' => [['opens_at' => '22:00', 'closes_at' => '00:00']]],
        ['day_of_week' => '1', 'is_closed' => '0', 'intervals' => [['opens_at' => '00:00', 'closes_at' => '02:00']]],
        ['day_of_week' => 1, 'is_closed' => true, 'intervals' => [['opens_at' => '00:00', 'closes_at' => '03:00']]],
    ];

    expect(Validator::make(['hours' => $hours], ['hours' => [new NonOverlappingOpeningHours]])->passes())->toBeTrue();
});

test('opening hours rules reject malformed transport values without throwing', function (mixed $hours): void {
    if (is_array($hours) && count($hours) <= 7) {
        $hours = array_map(static function (int $index) use ($hours): mixed {
            $defaults = ['day_of_week' => $index + 1, 'label' => 'Day', 'is_closed' => true, 'intervals' => []];

            if (! array_key_exists($index, $hours)) {
                return $defaults;
            }

            return is_array($hours[$index]) ? [...$defaults, 'is_closed' => false, ...$hours[$index]] : $hours[$index];
        }, range(0, 6));
    }

    $validator = Validator::make(['openingHoursConfigured' => true, 'openingHours' => $hours], OpeningHoursRules::openingHours(true));

    expect($validator->fails())->toBeTrue();
})->with([
    'scalar week' => ['invalid'],
    'oversized week' => [array_fill(0, 8, [])],
    'scalar day' => [[null]],
    'invalid weekday type' => [[['day_of_week' => []]]],
    'weekday out of range' => [[['day_of_week' => 8]]],
    'scalar intervals' => [[['day_of_week' => 1, 'intervals' => 'invalid']]],
    'oversized interval list' => [[['day_of_week' => 1, 'intervals' => array_fill(0, 5, [])]]],
    'scalar interval' => [[['day_of_week' => 1, 'intervals' => ['invalid']]]],
    'invalid time type' => [[['day_of_week' => 1, 'intervals' => [['opens_at' => []]]]]],
    'invalid clock time' => [[['day_of_week' => 1, 'intervals' => [['opens_at' => '25:00', 'closes_at' => '26:00']]]]],
    'unexpected day key' => [[['secret' => 'not allowed']]],
    'unexpected interval key' => [[['intervals' => [['opens_at' => '08:00', 'closes_at' => '09:00', 'secret' => 'not allowed']]]]],
    'boolean weekday' => [[['day_of_week' => true]]],
    'associative intervals' => [[['intervals' => ['unexpected' => ['opens_at' => '08:00', 'closes_at' => '09:00']]]]],
]);
