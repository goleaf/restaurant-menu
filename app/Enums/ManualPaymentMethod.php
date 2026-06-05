<?php

namespace App\Enums;

enum ManualPaymentMethod: string
{
    case Cash = 'cash';
    case CardTerminal = 'card_terminal';
    case Other = 'other';

    public function translationKey(): string
    {
        return match ($this) {
            self::Cash => 'ui.payment_methods.cash',
            self::CardTerminal => 'ui.payment_methods.card_terminal',
            self::Other => 'ui.payment_methods.other',
        };
    }

    public function label(): string
    {
        return __($this->translationKey());
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            fn (self $method): string => $method->value,
            self::cases(),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $method): array => [$method->value => $method->label()])
            ->all();
    }
}
