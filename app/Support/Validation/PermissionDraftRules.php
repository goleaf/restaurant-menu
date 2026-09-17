<?php

declare(strict_types=1);

namespace App\Support\Validation;

final class PermissionDraftRules
{
    /** @return array<string, list<string>> */
    public static function changes(): array
    {
        return [
            'changes' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'changes.*' => ['required', 'array:permission_id,state'],
            'changes.*.permission_id' => ['required', 'numeric', 'integer', 'min:1', 'distinct'],
            'changes.*.state' => ['required', 'string', 'in:default,allow,deny'],
        ];
    }

    /** @return array<string, list<string>> */
    public static function confirmation(bool $critical): array
    {
        return [
            'reason' => [$critical ? 'required' : 'nullable', 'string', 'min:3', 'max:500'],
            'confirmed' => [$critical ? 'accepted' : 'boolean'],
        ];
    }
}
