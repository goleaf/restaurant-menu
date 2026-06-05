<?php

use App\Enums\AreaNodeType;
use App\Enums\ManualPaymentMethod;
use App\Enums\MenuStatus;
use App\Enums\ServicePointType;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
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
