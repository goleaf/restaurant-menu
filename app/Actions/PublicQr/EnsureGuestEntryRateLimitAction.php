<?php

declare(strict_types=1);

namespace App\Actions\PublicQr;

use Illuminate\Cache\RateLimiter;
use Illuminate\Validation\ValidationException;

final class EnsureGuestEntryRateLimitAction
{
    private const MAX_ATTEMPTS_PER_QR = 10;

    private const MAX_ATTEMPTS_PER_ADDRESS = 30;

    private const DECAY_SECONDS = 60;

    public function __construct(
        private readonly RateLimiter $rateLimiter,
    ) {}

    public function handle(string $qrToken, ?string $clientAddress): void
    {
        $clientAddress = trim((string) $clientAddress);
        $addressDigest = hash('sha256', $clientAddress === '' ? 'unknown' : $clientAddress);
        $qrDigest = hash('sha256', $qrToken);
        $addressKey = 'guest-entry:address:'.$addressDigest;
        $qrKey = 'guest-entry:qr:'.$qrDigest.':'.$addressDigest;

        if ($this->rateLimiter->tooManyAttempts($addressKey, self::MAX_ATTEMPTS_PER_ADDRESS)
            || $this->rateLimiter->tooManyAttempts($qrKey, self::MAX_ATTEMPTS_PER_QR)) {
            $seconds = max(
                $this->rateLimiter->availableIn($addressKey),
                $this->rateLimiter->availableIn($qrKey),
            );

            throw ValidationException::withMessages([
                'guestName' => __('guest.table.entry_rate_limited', ['seconds' => max(1, $seconds)]),
            ]);
        }

        $this->rateLimiter->hit($addressKey, self::DECAY_SECONDS);
        $this->rateLimiter->hit($qrKey, self::DECAY_SECONDS);
    }
}
