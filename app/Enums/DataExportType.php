<?php

namespace App\Enums;

enum DataExportType: string
{
    case Orders = 'orders';
    case Payments = 'payments';
    case Menu = 'menu';
    case ServicePoints = 'service-points';

    public function label(): string
    {
        return match ($this) {
            self::Orders => 'Orders',
            self::Payments => 'Payments',
            self::Menu => 'Menu',
            self::ServicePoints => 'Tables',
        };
    }

    public function filenamePart(): string
    {
        return match ($this) {
            self::Orders => 'orders',
            self::Payments => 'payments',
            self::Menu => 'menu',
            self::ServicePoints => 'tables',
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
}
