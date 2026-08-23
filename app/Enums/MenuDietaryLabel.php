<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Support\Facades\Lang;

enum MenuDietaryLabel: string
{
    case Vegetarian = 'vegetarian';
    case Vegan = 'vegan';
    case GlutenFree = 'gluten_free';
    case LactoseFree = 'lactose_free';
    case Halal = 'halal';
    case Kosher = 'kosher';

    public function translationKey(): string
    {
        return 'menu.dietary_labels.options.'.$this->value;
    }

    public function label(?string $locale = null): string
    {
        return (string) Lang::get($this->translationKey(), [], $locale);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            fn (self $label): string => $label->value,
            self::cases(),
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(?string $locale = null): array
    {
        return array_map(
            fn (self $label): array => [
                'value' => $label->value,
                'label' => $label->label($locale),
            ],
            self::cases(),
        );
    }
}
