<?php

declare(strict_types=1);

namespace App\Support\Validation\Organizations;

use App\Support\PlainText;
use App\Support\Validation\Branches\BranchProfileRules;

final class RestaurantCreationRules
{
    /** @param array<string,mixed> $input @return array<string,mixed> */
    public static function normalize(array $input): array
    {
        foreach (['organizationName', 'brandName', 'branchName', 'branchAddress', 'branchCity'] as $key) {
            if (is_string($input[$key] ?? null)) {
                $input[$key] = PlainText::required($input[$key], 0, squish: true);
            }
        }
        foreach (['branchCountryCode', 'branchCurrency'] as $key) {
            if (is_string($input[$key] ?? null)) {
                $input[$key] = strtoupper(trim($input[$key]));
            }
        }

        return $input;
    }

    /** @return array<string,list<mixed>> */
    public static function rules(): array
    {
        return [
            ...BranchProfileRules::onboardingBranch(),
            'organizationId' => ['bail', 'required_with:brandId', 'nullable', 'numeric', 'integer', 'min:1'],
            'brandId' => ['bail', 'nullable', 'numeric', 'integer', 'min:1'],
            'organizationName' => ['bail', 'required_without:organizationId', 'nullable', 'string', 'max:120'],
            'brandName' => ['bail', 'required_without:brandId', 'nullable', 'string', 'max:120'],
        ];
    }

    /** @return array<string,string> */
    public static function attributes(): array
    {
        return ['organizationId' => __('center.organization'), 'brandId' => __('center.brand'),
            'organizationName' => __('center.organization_name'), 'brandName' => __('center.brand_name'),
            'branchName' => __('center.restaurant_name'), 'branchAddress' => __('center.address'),
            'branchCity' => __('center.city'), 'branchCountryCode' => __('center.country'),
            'branchTimezone' => __('center.timezone'), 'branchCurrency' => __('center.currency')];
    }
}
