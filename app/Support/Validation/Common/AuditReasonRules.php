<?php

declare(strict_types=1);

namespace App\Support\Validation\Common;

final class AuditReasonRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function auditReason(string $field): array
    {
        return [
            $field => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
