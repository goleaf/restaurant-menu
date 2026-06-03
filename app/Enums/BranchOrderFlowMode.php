<?php

namespace App\Enums;

enum BranchOrderFlowMode: string
{
    case WaiterConfirmation = 'waiter_confirmation';
    case StaffManaged = 'staff_managed';

    public function label(): string
    {
        return match ($this) {
            self::WaiterConfirmation => 'Waiter confirmation',
            self::StaffManaged => 'Staff managed',
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
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $mode): array => [
                'value' => $mode->value,
                'label' => $mode->label(),
            ],
            self::cases(),
        );
    }
}
