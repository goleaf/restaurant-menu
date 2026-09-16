<?php

declare(strict_types=1);

namespace App\Support\Validation\Branches;

use App\Enums\AreaNodeType;
use App\Support\Validation\Common\EnumRules;
use App\Support\Validation\Common\RuleFields;
use Illuminate\Validation\Rule;

final class AreaRules
{
    /**
     * @param  list<string>  $iconValues
     * @return array<string, list<mixed>>
     */
    public static function areaNode(string $prefix = '', array $iconValues = []): array
    {
        return [
            RuleFields::name($prefix, 'name') => ['bail', 'required', 'string', 'max:160'],
            RuleFields::name($prefix, 'type') => ['required', 'string', Rule::in(AreaNodeType::values())],
            RuleFields::name($prefix, 'icon') => EnumRules::text($iconValues, required: true),
            RuleFields::name($prefix, 'sortOrder') => ['required', 'numeric', 'integer', 'min:0', 'max:9999'],
            RuleFields::name($prefix, 'isActive') => ['boolean'],
        ];
    }

    /**
     * @param  list<string>  $iconValues
     * @return array<string, list<mixed>>
     */
    public static function onboardingArea(array $iconValues): array
    {
        return [
            'areaName' => ['bail', 'required', 'string', 'max:160'],
            'areaType' => ['bail', 'required', 'string', Rule::in(AreaNodeType::values())],
            'areaIcon' => ['bail', 'required', 'string', Rule::in($iconValues)],
        ];
    }
}
