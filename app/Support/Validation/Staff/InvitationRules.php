<?php

declare(strict_types=1);

namespace App\Support\Validation\Staff;

final class InvitationRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function staffInvitation(mixed $roleRule = null): array
    {
        $roleRules = ['bail', 'required', 'numeric', 'integer'];

        if ($roleRule !== null) {
            $roleRules[] = $roleRule;
        }

        return [
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'roleId' => $roleRules,
            'expiresInDays' => ['required', 'numeric', 'integer', 'between:1,30'],
        ];
    }
}
