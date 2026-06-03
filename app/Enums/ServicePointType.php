<?php

namespace App\Enums;

enum ServicePointType: string
{
    case Table = 'table';
    case BarSeat = 'bar_seat';
    case VipTable = 'vip_table';
    case Room = 'room';
    case Booth = 'booth';
    case Sunbed = 'sunbed';
    case HotelRoom = 'hotel_room';
    case PickupWindow = 'pickup_window';
    case DeliveryPoint = 'delivery_point';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Table => 'Table',
            self::BarSeat => 'Bar seat',
            self::VipTable => 'VIP table',
            self::Room => 'Room',
            self::Booth => 'Booth',
            self::Sunbed => 'Sunbed',
            self::HotelRoom => 'Hotel room',
            self::PickupWindow => 'Pickup window',
            self::DeliveryPoint => 'Delivery point',
            self::Other => 'Other',
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
}
