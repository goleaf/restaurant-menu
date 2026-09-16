<?php

declare(strict_types=1);

namespace App\Support\Validation\Organizations;

final class OrganizationRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function organizationName(string $field = 'name'): array
    {
        return [
            $field => ['required', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function brandName(string $field = 'name'): array
    {
        return [
            $field => ['required', 'string', 'max:120'],
        ];
    }
}
