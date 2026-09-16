<?php

declare(strict_types=1);

namespace App\Support\Validation\Branches;

use App\Enums\KitchenDepartmentType;
use App\Support\Validation\Common\RuleFields;
use Illuminate\Validation\Rule;

final class KitchenDepartmentRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function kitchenDepartment(string $prefix = ''): array
    {
        return [
            RuleFields::name($prefix, 'departmentName') => ['required', 'string', 'max:120'],
            RuleFields::name($prefix, 'departmentType') => ['required', 'string', Rule::in(KitchenDepartmentType::values())],
            RuleFields::name($prefix, 'departmentSortOrder') => ['required', 'integer', 'min:0', 'max:9999'],
            RuleFields::name($prefix, 'departmentIsActive') => ['boolean'],
        ];
    }
}
