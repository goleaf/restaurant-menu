<?php

declare(strict_types=1);

use App\Enums\McpAbility;
use App\Enums\MenuAllergen;
use App\Enums\MenuDietaryLabel;
use App\Enums\SupportedCurrency;
use App\Enums\SupportedLocale;
use App\Enums\TableSessionStatus;
use Tests\TestCase;

pest()->extend(TestCase::class);

test('enum values retain their public order and cannot be changed by callers', function (string $enum, array $expected): void {
    $values = $enum::values();

    expect($values)->toBe($expected)
        ->and(array_is_list($values))->toBeTrue();

    $values[0] = 'caller change';
    $values[] = 'caller addition';

    expect($enum::values())->toBe($expected);
})->with([
    'session statuses' => [TableSessionStatus::class, ['pending', 'active', 'waiting_waiter_confirmation', 'payment_requested', 'paid', 'closed', 'cancelled']],
    'languages' => [SupportedLocale::class, ['ru', 'en', 'lt']],
    'currencies' => [SupportedCurrency::class, ['EUR', 'USD', 'GBP', 'PLN', 'CZK', 'DKK', 'NOK', 'SEK', 'CHF', 'UAH', 'GEL', 'TRY', 'CAD', 'AUD']],
    'allergens' => [MenuAllergen::class, ['gluten', 'crustaceans', 'eggs', 'fish', 'peanuts', 'soybeans', 'milk', 'tree_nuts', 'celery', 'mustard', 'sesame', 'sulphites', 'lupin', 'molluscs']],
    'dietary labels' => [MenuDietaryLabel::class, ['vegetarian', 'vegan', 'gluten_free', 'lactose_free', 'halal', 'kosher']],
]);

test('session status projections preserve every allowed and denied workflow state', function (string $projection, string $predicate, array $expected): void {
    $values = TableSessionStatus::$projection();

    expect($values)->toBe($expected)
        ->and(array_is_list($values))->toBeTrue();

    foreach (TableSessionStatus::cases() as $status) {
        expect(in_array($status->value, $values, true))->toBe($status->$predicate());
    }
})->with([
    'guest visibility' => ['guestViewableValues', 'allowsGuestViewing', ['pending', 'active', 'waiting_waiter_confirmation', 'payment_requested', 'paid']],
    'table occupancy' => ['occupyingValues', 'occupiesServicePoint', ['active', 'waiting_waiter_confirmation', 'payment_requested']],
    'blocked new entry' => ['guestEntryBlockedValues', 'blocksNewGuestEntry', ['waiting_waiter_confirmation', 'payment_requested', 'paid']],
]);

test('default MCP abilities contain every read ability and no mutation', function (): void {
    $abilities = McpAbility::readOnly();

    expect($abilities)->toBe([
        'branch_context', 'list_menu_items', 'list_tables', 'list_orders', 'list_drafts',
        'list_waiter_calls', 'list_department_tickets', 'branch_report', 'list_audit_events', 'payment_summary',
    ])->and(array_is_list($abilities))->toBeTrue();

    foreach (McpAbility::cases() as $ability) {
        expect(in_array($ability->value, $abilities, true))->toBe(! $ability->isMutation());
    }

    $abilities[] = McpAbility::RecordPayment->value;
    expect(McpAbility::readOnly())->not->toContain(McpAbility::RecordPayment->value);
});

test('enum label maps preserve keys and follow each active locale without stale results', function (string $enum, string $projection): void {
    foreach (['en', 'lt', 'ru', 'en'] as $locale) {
        app()->setLocale($locale);
        $expected = [];
        foreach ($enum::cases() as $case) {
            $expected[$case->value] = $case->label();
        }

        $labels = $enum::$projection();
        expect($labels)->toBe($expected)
            ->and(array_keys($labels))->toBe($enum::values());

        $labels[array_key_first($labels)] = 'caller change';
        expect($enum::$projection())->toBe($expected);
    }
})->with([
    'session status options' => [TableSessionStatus::class, 'options'],
    'language labels' => [SupportedLocale::class, 'labels'],
    'currency labels' => [SupportedCurrency::class, 'labels'],
]);

test('menu label options retain their explicit locale and scalar values', function (string $enum): void {
    app()->setLocale('ru');
    foreach (['en', 'lt', 'ru'] as $locale) {
        $options = $enum::options($locale);
        expect(array_column($options, 'value'))->toBe($enum::values())
            ->and(array_is_list($options))->toBeTrue();
        foreach ($enum::cases() as $index => $case) {
            expect($options[$index])->toBe(['value' => $case->value, 'label' => $case->label($locale)]);
        }
    }
})->with([MenuAllergen::class, MenuDietaryLabel::class]);

test('locale and currency normalization retain configured fallbacks', function (?string $locale, ?string $localeFallback, string $expectedLocale, ?string $currency, ?string $currencyFallback, string $expectedCurrency): void {
    expect(SupportedLocale::normalize($locale, $localeFallback))->toBe($expectedLocale)
        ->and(SupportedCurrency::normalize($currency, $currencyFallback))->toBe($expectedCurrency);
})->with([
    [' EN_us ', 'ru', 'en', ' usd ', 'GBP', 'USD'],
    ['lt-LT', 'en', 'lt', 'pln', 'EUR', 'PLN'],
    ['de', 'ru_RU', 'ru', 'EURO', ' gbp ', 'GBP'],
    [null, null, 'en', null, null, 'EUR'],
    ['', 'bad', 'en', '', 'bad', 'EUR'],
]);
