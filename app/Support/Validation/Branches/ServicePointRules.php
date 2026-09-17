<?php

declare(strict_types=1);

namespace App\Support\Validation\Branches;

use App\Enums\ServicePointType;
use App\Support\Validation\Common\EnumRules;
use App\Support\Validation\Common\RuleFields;
use Illuminate\Validation\Rule;

final class ServicePointRules
{
    /**
     * @param  list<string>  $iconValues
     * @return array<string, list<mixed>>
     */
    public static function servicePoint(string $prefix = '', array $iconValues = []): array
    {
        return [
            RuleFields::name($prefix, 'type') => ['required', 'string', Rule::in(ServicePointType::values())],
            RuleFields::name($prefix, 'icon') => EnumRules::text($iconValues, required: true),
            RuleFields::name($prefix, 'name') => ['bail', 'required', 'string', 'max:160'],
            RuleFields::name($prefix, 'displayNumber') => ['nullable', 'string', 'max:80'],
            RuleFields::name($prefix, 'capacity') => ['required', 'numeric', 'integer', 'min:1', 'max:999'],
            RuleFields::name($prefix, 'isActive') => ['boolean'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function bulkServicePoint(): array
    {
        return [
            'bulkType' => ['required', 'string', Rule::in(ServicePointType::values())],
            'bulkPrefix' => ['bail', 'required', 'string', 'max:20', 'regex:/\A[A-Za-z0-9_-]+\z/'],
            'bulkFrom' => ['bail', 'required', 'numeric', 'integer', 'min:1', 'max:9999'],
            'bulkTo' => ['bail', 'required', 'numeric', 'integer', 'min:1', 'max:9999', 'gte:bulkFrom'],
            'bulkCapacity' => ['required', 'numeric', 'integer', 'min:1', 'max:999'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function onboardingServicePoints(): array
    {
        return [
            'tableCount' => ['bail', 'required', 'numeric', 'integer', 'min:1', 'max:20'],
            'tablePrefix' => ['bail', 'required', 'string', 'max:40'],
            'tableCapacity' => ['bail', 'required', 'numeric', 'integer', 'min:1', 'max:50'],
        ];
    }
}
