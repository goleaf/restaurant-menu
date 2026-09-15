<?php

declare(strict_types=1);

namespace App\Enums;

enum InvitationAccessState
{
    case Pending;
    case Expired;
    case Accepted;
    case Unavailable;
    case EmailMismatch;

    public function sessionValue(): string
    {
        return str($this->name)->snake()->toString();
    }

    public static function fromSession(mixed $value): self
    {
        if (! is_string($value)) {
            return self::Unavailable;
        }

        return match ($value) {
            'pending' => self::Pending,
            'expired' => self::Expired,
            'accepted' => self::Accepted,
            'email_mismatch' => self::EmailMismatch,
            default => self::Unavailable,
        };
    }
}
