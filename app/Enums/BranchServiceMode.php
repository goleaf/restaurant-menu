<?php

namespace App\Enums;

enum BranchServiceMode: string
{
    case DineIn = 'dine_in';
    case Pickup = 'pickup';
    case Delivery = 'delivery';
    case HotelRoomService = 'hotel_room_service';
    case BarOnly = 'bar_only';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::DineIn => 'Dine-in',
            self::Pickup => 'Pickup',
            self::Delivery => 'Delivery',
            self::HotelRoomService => 'Hotel room service',
            self::BarOnly => 'Bar only',
            self::Custom => 'Custom',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::DineIn => 'Use QR codes for tables, rooms, bar seats, and other on-site service points.',
            self::Pickup => 'Prepare this branch for pickup windows or pickup points.',
            self::Delivery => 'Keep delivery as a future-ready option without maps, couriers, or payments.',
            self::HotelRoomService => 'Allow hotel room service scenarios for hotel branches later.',
            self::BarOnly => 'Use this branch mainly for bar seats or counter service.',
            self::Custom => 'Reserve a custom local service mode for unusual branch operations.',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            fn (self $mode): string => $mode->value,
            self::cases(),
        );
    }

    /**
     * @return list<string>
     */
    public static function defaultValues(): array
    {
        return [self::DineIn->value];
    }

    /**
     * @param  list<string>|null  $values
     * @return list<string>
     */
    public static function normalizeList(?array $values): array
    {
        $selectedValues = collect($values ?? [])
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter(fn (string $value): bool => self::tryFrom($value) instanceof self)
            ->unique()
            ->values();

        if ($selectedValues->isEmpty()) {
            return self::defaultValues();
        }

        return collect(self::cases())
            ->map(fn (self $mode): string => $mode->value)
            ->filter(fn (string $value): bool => $selectedValues->contains($value))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $mode): array => [
                'value' => $mode->value,
                'label' => $mode->label(),
                'description' => $mode->description(),
            ],
            self::cases(),
        );
    }
}
