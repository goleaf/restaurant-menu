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
            self::Minimal => 'qr.print.presets.minimal.label',
            self::Classic => 'qr.print.presets.classic.label',
            self::Restaurant => 'qr.print.presets.restaurant.label',
            self::Bar => 'qr.print.presets.bar.label',
            self::Hotel => 'qr.print.presets.hotel.label',
            self::Premium => 'qr.print.presets.premium.label',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Minimal => 'qr.print.presets.minimal.description',
            self::Classic => 'qr.print.presets.classic.description',
            self::Restaurant => 'qr.print.presets.restaurant.description',
            self::Bar => 'qr.print.presets.bar.description',
            self::Hotel => 'qr.print.presets.hotel.description',
            self::Premium => 'qr.print.presets.premium.description',
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
