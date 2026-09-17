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

    /** @return list<array{value:string,label:string}> */
    public static function types(bool $area = false): array
    {
        return array_map(fn ($case): array => ['value' => $case->value, 'label' => __($case->label())], $area ? AreaNodeType::cases() : ServicePointType::cases());
    }
}
