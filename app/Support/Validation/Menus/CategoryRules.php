<?php

declare(strict_types=1);

namespace App\Support\Validation\Menus;

use App\Support\Validation\Common\EnumRules;
use App\Support\Validation\Common\RuleFields;

final class CategoryRules
{
    /**
     * @param  list<string>  $iconValues
     * @return array<string, list<mixed>>
     */
    public static function category(string $prefix = '', array $iconValues = []): array
    {
        return [
            RuleFields::name($prefix, 'categoryName') => ['bail', 'required', 'string', 'max:160'],
            RuleFields::name($prefix, 'categoryDescription') => ['nullable', 'string', 'max:1000'],
            RuleFields::name($prefix, 'categoryIcon') => EnumRules::text($iconValues, required: false),
            RuleFields::name($prefix, 'categorySortOrder') => ['required', 'numeric', 'integer', 'min:0', 'max:9999'],
            RuleFields::name($prefix, 'categoryIsActive') => ['boolean'],
        ];
    }
}
