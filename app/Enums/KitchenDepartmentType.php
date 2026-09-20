<?php

declare(strict_types=1);

namespace App\Enums;

enum KitchenDepartmentType: string
{
    case Kitchen = 'kitchen';
    case Bar = 'bar';
    case Dessert = 'dessert';
    case Hookah = 'hookah';
    case Custom = 'custom';

    /**
     * @return list<self>
     */
    public static function kitchenProductionTypes(): array
    {
        return [self::Kitchen, self::Dessert, self::Hookah, self::Custom];
    }

    /**
     * @return list<self>
     */
    public static function barProductionTypes(): array
    {
        return [self::Bar];
    }

    public function label(): string
    {
        return __(match ($this) {
            self::Kitchen => 'preparation.types.kitchen',
            self::Bar => 'preparation.types.bar',
            self::Dessert => 'preparation.types.dessert',
            self::Hookah => 'preparation.types.hookah',
            self::Custom => 'preparation.types.custom',
        });
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

    public function defaultName(): string
    {
        return match ($this) {
            self::Kitchen => 'Kitchen',
            self::Bar => 'Bar',
            self::Dessert => 'Dessert',
            self::Hookah => 'Hookah',
            self::Custom => 'Custom',
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
            ['type' => self::Kitchen->value, 'name' => self::Kitchen->defaultName(), 'sort_order' => 10],
            ['type' => self::Bar->value, 'name' => self::Bar->defaultName(), 'sort_order' => 20],
            ['type' => self::Dessert->value, 'name' => self::Dessert->defaultName(), 'sort_order' => 30],
            ['type' => self::Hookah->value, 'name' => self::Hookah->defaultName(), 'sort_order' => 40],
        ];
    }
}
