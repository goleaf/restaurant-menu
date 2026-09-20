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
        $key = 'ui.branch.service_modes.'.$this->value.'.label';

        return __($key);
    }

    public function description(): string
    {
        $key = 'ui.branch.service_modes.'.$this->value.'.description';

        return __($key);
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
