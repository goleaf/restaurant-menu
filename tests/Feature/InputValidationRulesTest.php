<?php

use App\Enums\AreaNodeType;
use App\Enums\ManualPaymentMethod;
use App\Enums\MenuStatus;
use App\Enums\ServicePointType;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Support\MoneyFormatter;
use App\Support\Validation\DecimalMoney;
use App\Support\Validation\RestaurantValidationRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

test('central rules reject unsafe main form input', function () {
    $rules = [
        ...RestaurantValidationRules::organizationName('organizationName'),
        ...RestaurantValidationRules::brandName('brandName'),
        ...RestaurantValidationRules::guestName('guestName'),
        ...RestaurantValidationRules::guestComment('guestComment'),
        ...RestaurantValidationRules::waiterRejectionReason('rejectionReason'),
        ...RestaurantValidationRules::price('price'),
        ...RestaurantValidationRules::manualPaymentAmount('paymentAmount'),
        ...RestaurantValidationRules::paymentMethod('paymentMethod'),
        ...RestaurantValidationRules::reportDateRange('reportFrom', 'reportTo'),
    ];

    $validator = Validator::make([
        'organizationName' => str_repeat('O', 121),
        'brandName' => '',
        'guestName' => 'A',
        'guestComment' => str_repeat('c', 501),
        'rejectionReason' => 'no',
        'price' => '-1.00',
        'paymentAmount' => '-0.01',
        'paymentMethod' => 'crypto',
        'reportFrom' => '2026-06-05',
        'reportTo' => '2026-06-01',
    ], $rules);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->keys())->toContain(
            'organizationName',
            'brandName',
            'guestName',
            'guestComment',
            'rejectionReason',
            'price',
            'paymentAmount',
            'paymentMethod',
            'reportTo',
        );
});

test('central rules allow valid enum and money inputs', function () {
    $rules = [
        ...RestaurantValidationRules::areaNode(iconValues: ['folder']),
        ...RestaurantValidationRules::servicePoint(prefix: 'servicePoint', iconValues: ['squares-2x2']),
        ...RestaurantValidationRules::menu(),
        ...RestaurantValidationRules::modifierOption(canChangePrices: true, canChangeAvailability: true),
        ...RestaurantValidationRules::paymentMethod(),
    ];

    $validator = Validator::make([
        'name' => 'Main hall',
        'type' => AreaNodeType::Hall->value,
        'icon' => 'folder',
        'sortOrder' => 0,
        'isActive' => true,
        'servicePointType' => ServicePointType::Table->value,
        'servicePointIcon' => 'squares-2x2',
        'servicePointName' => 'Table 1',
        'servicePointDisplayNumber' => 'T1',
        'servicePointCapacity' => 4,
        'servicePointIsActive' => true,
        'menuName' => 'Dinner',
        'menuStatus' => MenuStatus::Active->value,
        'menuSortOrder' => 10,
        'modifierOptionName' => 'No onions',
        'modifierOptionSortOrder' => 0,
        'modifierOptionPriceDelta' => '-1.50',
        'modifierOptionIsAvailable' => true,
        'paymentMethod' => ManualPaymentMethod::Cash->value,
    ], $rules);

    expect($validator->fails())->toBeFalse($validator->errors()->toJson());
});

test('central monetary rules reject values that cannot be converted exactly', function (mixed $value, string $boundary): void {
    $rules = match ($boundary) {
        'price' => RestaurantValidationRules::price()['price'],
        'payment' => RestaurantValidationRules::manualPaymentAmount()['tipsAmount'],
        'service charge' => RestaurantValidationRules::branchSettings()['serviceChargePercent'],
    };

    $validator = Validator::make(['amount' => $value], ['amount' => $rules]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->keys())->toContain('amount');
})->with([
    'missing whole digits' => '.50',
    'missing fraction digits' => '1.',
    'binary fraction' => 0.29,
    'whole number float' => 10.0,
])->with(['price', 'payment', 'service charge']);

test('decimal money rule rejects malformed types precision and overflow', function (mixed $value): void {
    $validator = Validator::make(['amount' => $value], ['amount' => ['required', new DecimalMoney]]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->keys())->toContain('amount');
})->with([
    'array' => [['0.29']],
    'object' => [new stdClass],
    'boolean' => true,
    'float' => 0.29,
    'non-finite' => INF,
    'null' => null,
    'empty' => '',
    'exponent' => '1e2',
    'excess precision' => '0.299',
    'decimal overflow' => str_repeat('9', 40),
    'integer overflow' => PHP_INT_MAX,
]);

test('decimal money rule accepts exact inputs supported by the money converter', function (string|int $value, int $cents): void {
    $validator = Validator::make(['amount' => $value], ['amount' => ['required', new DecimalMoney]]);

    expect($validator->passes())->toBeTrue()
        ->and(MoneyFormatter::decimalToCents($validator->validated()['amount']))->toBe($cents);
})->with([
    'integer zero' => [0, 0],
    'integer amount' => [12, 1200],
    'decimal string' => ['0.29', 29],
    'comma decimal' => ['0,29', 29],
    'signed amount' => ['+1.25', 125],
    'negative modifier' => ['-1.25', -125],
    'trimmed string' => [' 12.50 ', 1250],
    'maximum price' => ['999999.99', 99_999_999],
]);

test('decimal money validation errors use the selected locale and field label', function (string $locale, string $message): void {
    app()->setLocale($locale);
    $validator = Validator::make(
        ['amount' => '.50'],
        ['amount' => ['required', new DecimalMoney]],
        attributes: ['amount' => 'test amount'],
    );

    expect($validator->errors()->first('amount'))->toBe($message);
})->with([
    'en' => ['en', 'The test amount must be an exact decimal amount, such as 0.50.'],
    'lt' => ['lt', 'Lauke „test amount“ įveskite tikslią dešimtainę sumą, pavyzdžiui, 0.50.'],
    'ru' => ['ru', 'В поле «test amount» введите точную десятичную сумму, например 0.50.'],
]);

test('optional guest name accepts null and validates provided names', function (): void {
    $rules = RestaurantValidationRules::optionalGuestName('guestName');
    $emptyValidator = Validator::make(['guestName' => null], $rules);
    $validValidator = Validator::make(['guestName' => 'Ana'], $rules);
    $shortValidator = Validator::make(['guestName' => 'A'], $rules);

    expect($emptyValidator->passes())->toBeTrue()
        ->and($validValidator->passes())->toBeTrue()
        ->and($shortValidator->fails())->toBeTrue()
        ->and($shortValidator->errors()->keys())->toContain('guestName');
});

test('central image upload rules reject scriptable files', function () {
    $validator = Validator::make([
        'image' => UploadedFile::fake()->create('payload.svg', 10, 'image/svg+xml'),
    ], RestaurantValidationRules::imageUpload('image'));

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->keys())->toContain('image');
});

test('central branch rule scopes branch id to organization', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $otherBrand = Brand::factory()->for($otherOrganization)->create();
    $otherBranch = Branch::factory()
        ->for($otherOrganization)
        ->for($otherBrand)
        ->create();

    $validator = Validator::make([
        'branchId' => $otherBranch->id,
    ], RestaurantValidationRules::branchId('branchId', $organization->id));

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->keys())->toContain('branchId');
});
