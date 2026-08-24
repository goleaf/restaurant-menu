<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Models\Invitation;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class InvitationCredentialGenerator
{
    private const INVITE_TOKEN_LENGTH = 64;

    private const INVITE_CODE_LENGTH = 8;

    /** @return array{token: string, code: string} */
    public function generate(?string $token = null, ?string $code = null): array
    {
        return [
            'token' => $this->token($token),
            'code' => $this->code($code),
        ];
    }

    public function hash(string $credential): string
    {
        return hash('sha256', $credential);
    }

    private function token(?string $token): string
    {
        if ($token !== null) {
            $token = trim($token);

            if (strlen($token) !== self::INVITE_TOKEN_LENGTH || ! ctype_alnum($token)) {
                throw new InvalidArgumentException('Invitation token must be a 64 character random token.');
            }

            return $token;
        }

        do {
            $token = Str::random(self::INVITE_TOKEN_LENGTH);
        } while (Invitation::query()->where('invite_token_hash', $this->hash($token))->exists());

        return $token;
    }

    private function code(?string $code): string
    {
        if ($code !== null) {
            $code = Str::upper(trim($code));

            if (strlen($code) !== self::INVITE_CODE_LENGTH || ! ctype_alnum($code)) {
                throw new InvalidArgumentException('Invitation code must be 8 alphanumeric characters.');
            }

            return $code;
        }

        do {
            $code = Str::upper(Str::random(self::INVITE_CODE_LENGTH));
        } while (Invitation::query()->where('invite_code_hash', $this->hash($code))->exists());

        return $code;
    }
}
