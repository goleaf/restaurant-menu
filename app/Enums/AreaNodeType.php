<?php

namespace App\Enums;

enum AreaNodeType: string
{
    case Group = 'group';
    case Floor = 'floor';
    case Hall = 'hall';
    case Terrace = 'terrace';
    case VipRoom = 'vip_room';
    case BarArea = 'bar_area';
    case BanquetHall = 'banquet_hall';
    case Room = 'room';
    case HotelArea = 'hotel_area';
    case PickupArea = 'pickup_area';
    case DeliveryArea = 'delivery_area';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Group => 'Group',
            self::Floor => 'Floor',
            self::Hall => 'Hall',
            self::Terrace => 'Terrace',
            self::VipRoom => 'VIP room',
            self::BarArea => 'Bar area',
            self::BanquetHall => 'Banquet hall',
            self::Room => 'Room',
            self::HotelArea => 'Hotel area',
            self::PickupArea => 'Pickup area',
            self::DeliveryArea => 'Delivery area',
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
}
