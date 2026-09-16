<?php

declare(strict_types=1);

namespace App\Support\Validation\Menus;

use App\Enums\MenuStatus;
use App\Support\Validation\Common\RuleFields;
use Illuminate\Validation\Rule;

final class MenuRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function menu(string $prefix = ''): array
    {
        return [
            RuleFields::name($prefix, 'menuName') => ['bail', 'required', 'string', 'max:160'],
            RuleFields::name($prefix, 'menuStatus') => ['required', 'string', Rule::in(MenuStatus::values())],
            RuleFields::name($prefix, 'menuSortOrder') => ['required', 'numeric', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
