<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Support\Facades\Lang;

enum MenuItemVariantType: string
{
    case Variant = 'variant';
    case Portion = 'portion';

    public function translationKey(): string
    {
        return match ($this) {
            self::Variant => 'menu.variants.types.variant',
            self::Portion => 'menu.variants.types.portion',
        };
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
            fn (self $type): string => $type->value,
            self::cases(),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function options(?string $locale = null): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label($locale)])
            ->all();
    }
}
