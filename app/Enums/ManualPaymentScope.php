<?php

namespace App\Enums;

enum ManualPaymentScope: string
{
    case Table = 'table';
    case Guest = 'guest';

    public function label(): string
    {
        return match ($this) {
            self::Table => __('payments.scopes.table'),
            self::Guest => __('payments.scopes.guest'),
        };
    }
}
