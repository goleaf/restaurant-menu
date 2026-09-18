<?php

declare(strict_types=1);

namespace App\Services\QrCodes;

use LogicException;

final class PublicQrUrl
{
    public function forToken(string $token): string
    {
        $origin = config('app.url');
        $parts = is_string($origin) ? parse_url($origin) : false;
        if (! is_string($origin) || ! is_array($parts)
            || ! in_array($parts['scheme'] ?? null, ['http', 'https'], true)
            || ! isset($parts['host']) || $parts['host'] === ''
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || preg_match('/[\x00-\x20\\\\]/', $origin) === 1 || filter_var($origin, FILTER_VALIDATE_URL) === false) {
            throw new LogicException('The configured public application URL must be a trusted HTTP origin and optional path.');
        }

        return rtrim($origin, '/').'/'.ltrim(route('public.qr.show', ['token' => $token], false), '/');
    }
}
