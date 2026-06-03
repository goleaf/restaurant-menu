<?php

namespace App\Enums;

enum KitchenDepartmentType: string
{
    case Kitchen = 'kitchen';
    case Bar = 'bar';
    case Dessert = 'dessert';
    case Hookah = 'hookah';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Kitchen => 'Kitchen',
            self::Bar => 'Bar',
            self::Dessert => 'Dessert',
            self::Hookah => 'Hookah',
            self::Custom => 'Custom',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Kitchen => 'orange',
            self::Bar => 'sky',
            self::Dessert => 'pink',
            self::Hookah => 'violet',
            self::Custom => 'zinc',
        };
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
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }

    /**
     * @return list<array{type: string, name: string, sort_order: int}>
     */
    public static function defaultSeedRows(): array
    {
        return [
            ['type' => self::Kitchen->value, 'name' => self::Kitchen->label(), 'sort_order' => 10],
            ['type' => self::Bar->value, 'name' => self::Bar->label(), 'sort_order' => 20],
            ['type' => self::Dessert->value, 'name' => self::Dessert->label(), 'sort_order' => 30],
            ['type' => self::Hookah->value, 'name' => self::Hookah->label(), 'sort_order' => 40],
        ];
    }
}
