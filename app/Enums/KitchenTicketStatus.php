<?php

namespace App\Enums;

enum KitchenTicketStatus: string
{
    case Sent = 'sent';

    public function label(): string
    {
        return __('statuses.kitchen_ticket.sent');
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            fn (self $status): string => $status->value,
            self::cases(),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])
            ->all();
    }
}
