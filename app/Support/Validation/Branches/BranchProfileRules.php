<?php

declare(strict_types=1);

namespace App\Support\Validation\Branches;

use App\Enums\SupportedCurrency;
use App\Models\Branch;
use App\Support\RestaurantSetupOptions;
use App\Support\Validation\Common\RuleFields;
use Illuminate\Validation\Rule;

final class BranchProfileRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function branchBase(string $prefix = ''): array
    {
        return [
            RuleFields::name($prefix, 'name') => ['required', 'string', 'max:160'],
            RuleFields::name($prefix, 'address') => ['required', 'string', 'max:255'],
            RuleFields::name($prefix, 'city') => ['required', 'string', 'max:120'],
            RuleFields::name($prefix, 'country') => ['required', 'string', 'max:120'],
            RuleFields::name($prefix, 'timezone') => ['required', 'timezone', 'max:64'],
            RuleFields::name($prefix, 'currency') => ['required', 'string', 'size:3', Rule::in(SupportedCurrency::values())],
            RuleFields::name($prefix, 'isActive') => ['boolean'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function onboardingBranch(): array
    {
        return [
            'branchName' => ['bail', 'required', 'string', 'max:160'],
            'branchAddress' => ['bail', 'required', 'string', 'max:255'],
            'branchCity' => ['bail', 'required', 'string', 'max:120'],
            'branchCountryCode' => [
                'bail',
                'required',
                'string',
                'size:2',
                Rule::in(RestaurantSetupOptions::countryCodes()),
            ],
            'branchTimezone' => [
                'bail',
                'required',
                'string',
                'max:64',
                'timezone',
                Rule::in(array_keys(RestaurantSetupOptions::timezoneOptions())),
            ],
            'branchCurrency' => [
                'bail',
                'required',
                'string',
                'size:3',
                Rule::in(SupportedCurrency::values()),
            ],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function branchProfile(): array
    {
        return [
            'publicName' => ['nullable', 'string', 'max:160'],
            'publicDescription' => ['nullable', 'string', 'max:1200'],
            'phone' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'websiteUrl' => ['nullable', 'url', 'max:2048'],
            'instagramUrl' => ['nullable', 'url', 'max:2048'],
            'facebookUrl' => ['nullable', 'url', 'max:2048'],
            'tiktokUrl' => ['nullable', 'url', 'max:2048'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function branchId(string $field = 'branchId', ?int $organizationId = null): array
    {
        $rule = Rule::exists((new Branch)->getTable(), 'id');

        if ($organizationId !== null) {
            $rule->where(fn ($query) => $query->where('organization_id', $organizationId));
        }

        return [
            $field => ['required', 'integer', 'min:1', $rule],
        ];
    }
}
