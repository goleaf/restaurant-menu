<?php

namespace App\Enums;

enum QrLabelPreset: string
{
    case Minimal = 'minimal';
    case Classic = 'classic';
    case Restaurant = 'restaurant';
    case Bar = 'bar';
    case Hotel = 'hotel';
    case Premium = 'premium';

    public function label(): string
    {
        return match ($this) {
            self::Minimal => 'Minimal',
            self::Classic => 'Classic',
            self::Restaurant => 'Restaurant',
            self::Bar => 'Bar',
            self::Hotel => 'Hotel',
            self::Premium => 'Premium',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Minimal => 'Clean black and white label for any venue.',
            self::Classic => 'Traditional framed label for table stickers.',
            self::Restaurant => 'Warm restaurant accent for dining rooms.',
            self::Bar => 'Compact bar-style label for counters and seats.',
            self::Hotel => 'Calm hotel-style label for rooms and service areas.',
            self::Premium => 'High-contrast premium label with a refined accent.',
        };
    }

    public function cssClass(): string
    {
        return 'qr-sticker-preset-'.$this->value;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            fn (self $preset): string => $preset->value,
            self::cases(),
        );
    }

    public static function fromValue(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Minimal;
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $preset): array => [
                'value' => $preset->value,
                'label' => $preset->label(),
                'description' => $preset->description(),
            ],
            self::cases(),
        );
    }
}
