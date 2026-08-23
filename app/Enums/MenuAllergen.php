<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Support\Facades\Lang;

enum MenuAllergen: string
{
    case Gluten = 'gluten';
    case Crustaceans = 'crustaceans';
    case Eggs = 'eggs';
    case Fish = 'fish';
    case Peanuts = 'peanuts';
    case Soybeans = 'soybeans';
    case Milk = 'milk';
    case TreeNuts = 'tree_nuts';
    case Celery = 'celery';
    case Mustard = 'mustard';
    case Sesame = 'sesame';
    case Sulphites = 'sulphites';
    case Lupin = 'lupin';
    case Molluscs = 'molluscs';

    public function translationKey(): string
    {
        return 'menu.allergens.options.'.$this->value;
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
            fn (self $allergen): string => $allergen->value,
            self::cases(),
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(?string $locale = null): array
    {
        return array_map(
            fn (self $allergen): array => [
                'value' => $allergen->value,
                'label' => $allergen->label($locale),
            ],
            self::cases(),
        );
    }
}
