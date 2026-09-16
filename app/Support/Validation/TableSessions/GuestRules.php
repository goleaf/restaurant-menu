<?php

declare(strict_types=1);

namespace App\Support\Validation\TableSessions;

final class GuestRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function guestName(string $field = 'guestName'): array
    {
        return [
            $field => ['required', 'string', 'min:2', 'max:80'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function optionalGuestName(string $field = 'guestName'): array
    {
        return [
            $field => ['nullable', 'string', 'min:2', 'max:80'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function guestComment(string $field = 'itemComment'): array
    {
        return [
            $field => ['nullable', 'string', 'max:500'],
        ];
    }
}
