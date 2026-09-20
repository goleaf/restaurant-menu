<?php

declare(strict_types=1);

namespace App\Support\Validation\Branches;

use App\Enums\AreaNodeType;
use App\Support\Floor\FloorOptions;
use App\Support\Validation\Common\EnumRules;
use App\Support\Validation\Common\RuleFields;
use Illuminate\Validation\Rule;

final class AreaRules
{
    /** @return array<string,list<mixed>> */
    public static function mutation(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:160'],
            'type' => ['bail', 'required', 'string', Rule::in(AreaNodeType::values())],
            'icon' => ['bail', 'nullable', 'string', Rule::in(FloorOptions::icons())],
            'parent_id' => ['bail', 'nullable', 'numeric', 'integer', 'min:1'],
            'sort_order' => ['bail', 'required', 'numeric', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<string,string> */
    public static function mutationAttributes(): array
    {
        return [
            'name' => __('ui.onboarding.restaurant_setup.nazvanie_zony'),
            'type' => __('ui.onboarding.restaurant_setup.cto_eto'),
            'icon' => __('ui.onboarding.restaurant_setup.ikonka'),
            'parent_id' => __('ui.organizations.brands.branches.area_node_row.gde_naxoditsia'),
            'sort_order' => __('ui.organizations.brands.branches.area_node_row.poriadok_v_spiske'),
            'is_active' => __('ui.organizations.brands.branches.area_node_row.ispolzovat_seicas'),
        ];
    }

    /**
     * @param  list<string>  $iconValues
     * @return array<string, list<mixed>>
     */
    public static function areaNode(string $prefix = '', array $iconValues = [], bool $iconRequired = true): array
    {
        return [
            RuleFields::name($prefix, 'name') => ['bail', 'required', 'string', 'max:160'],
            RuleFields::name($prefix, 'type') => ['required', 'string', Rule::in(AreaNodeType::values())],
            RuleFields::name($prefix, 'icon') => EnumRules::text($iconValues, required: $iconRequired),
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
