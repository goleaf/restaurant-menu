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
        return __($this->labelKey());
    }

    public function labelKey(): string
    {
        return match ($this) {
            self::Orders => 'reports.orders.title',
            self::Payments => 'reports.payments.title',
            self::Menu => 'reports.exports.menu',
            self::ServicePoints => 'reports.exports.tables',
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
