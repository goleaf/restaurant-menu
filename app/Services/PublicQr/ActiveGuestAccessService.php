<?php

declare(strict_types=1);

namespace App\Services\PublicQr;

use App\Models\TableSessionGuest;

final class ActiveGuestAccessService
{
    public function __construct(private readonly PublicQrQueryService $publicQrQueries) {}

    public function findAuthorizedGuest(string $publicToken, int $tableSessionId, int $guestId): ?TableSessionGuest
    {
        if ($publicToken === '' || $tableSessionId < 1 || $guestId < 1) {
            return null;
        }

        if ($this->publicQrQueries->activeTableSessionForQr($publicToken, $tableSessionId) === null) {
            return null;
        }

        $guestToken = $this->guestToken($publicToken);

        if ($guestToken === null) {
            return null;
        }

        return $this->publicQrQueries->activeGuest($guestId, $tableSessionId, $guestToken);
    }

    private function guestToken(string $publicToken): ?string
    {
        $cookieName = 'guest_token_'.substr(hash('sha256', $publicToken), 0, 24);
        $guestToken = request()->cookie($cookieName);

        if (is_string($guestToken) && strlen($guestToken) === 64) {
            return $guestToken;
        }

        $guestToken = session('guest_entries.'.$publicToken.'.guest_token');

        return is_string($guestToken) && strlen($guestToken) === 64
            ? $guestToken
            : null;
    }
}
