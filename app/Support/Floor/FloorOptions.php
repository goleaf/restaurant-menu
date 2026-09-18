<?php

declare(strict_types=1);

namespace App\Support\Floor;

use App\Enums\AreaNodeType;
use App\Enums\ServicePointType;

final class FloorOptions
{
    /** @return list<string> */
    public static function icons(): array
    {
        return ['rectangle-group', 'building-office', 'cake', 'folder', 'building-office-2', 'home', 'sun', 'sparkles', 'beaker', 'squares-2x2', 'bookmark', 'shopping-bag', 'truck'];
    }

    /** @return array<string,string> */
    public static function iconOptions(): array
    {
        return [
            'rectangle-group' => __('floor.icon.hall'),
            'building-office' => __('floor.icon.building'),
            'cake' => __('floor.icon.cake'),
            'folder' => __('floor.icon.folder'),
            'building-office-2' => __('floor.icon.floor'),
            'home' => __('floor.icon.home'),
            'sun' => __('floor.icon.sun'),
            'sparkles' => __('floor.icon.sparkles'),
            'beaker' => __('floor.icon.beaker'),
            'squares-2x2' => __('floor.icon.table'),
            'bookmark' => __('floor.icon.bookmark'),
            'shopping-bag' => __('floor.icon.pickup'),
            'truck' => __('floor.icon.truck'),
        ];
    }

    /** @return list<array{value:string,label:string}> */
    public static function types(bool $area = false): array
    {
        return array_map(fn ($case): array => ['value' => $case->value, 'label' => __($case->label())], $area ? AreaNodeType::cases() : ServicePointType::cases());
    }
}
